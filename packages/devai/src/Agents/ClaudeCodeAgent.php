<?php

declare(strict_types=1);

namespace Marko\DevAi\Agents;

use Marko\DevAi\Contract\SupportsGuidelines;
use Marko\DevAi\Contract\SupportsSettings;
use Marko\DevAi\Exceptions\DevAiInstallException;
use Marko\DevAi\Installation\IntelephenseEnsurerInterface;
use Marko\DevAi\Process\CommandRunnerInterface;
use Marko\DevAi\ValueObject\GuidelinesContent;

class ClaudeCodeAgent extends AbstractAgent implements SupportsGuidelines, SupportsSettings
{
    public function __construct(
        private CommandRunnerInterface $runner,
        private ?IntelephenseEnsurerInterface $intelephenseEnsurer = null,
    ) {}

    public function name(): string
    {
        return 'claude-code';
    }

    public function displayName(): string
    {
        return 'Claude Code';
    }

    public function isInstalled(): bool
    {
        return $this->runner->isOnPath('claude');
    }

    public function writeGuidelines(
        GuidelinesContent $content,
        string $projectRoot,
    ): void {
        file_put_contents($projectRoot . '/AGENTS.md', $content->body);
        file_put_contents($projectRoot . '/CLAUDE.md', $this->buildClaudeMd());
    }

    /**
     * Write (or merge) .claude/settings.json with the Marko marketplace registration
     * and enabled-plugin list. Cleans up any legacy LSP/MCP artifacts.
     *
     * @throws DevAiInstallException when the marketplace is already registered and --force is not passed
     */
    public function writeSettings(string $projectRoot, bool $force): void
    {
        $this->cleanupLegacyLspFile($projectRoot);
        $this->cleanupLegacyMcpServer();

        $settingsPath = $projectRoot . '/.claude/settings.json';
        $existing = $this->readExistingSettings($settingsPath);

        $this->assertNotAlreadyRegistered($existing, $projectRoot, $force);

        $merged = $this->mergeSettings($existing, $projectRoot);

        $claudeDir = $projectRoot . '/.claude';
        if (!is_dir($claudeDir)) {
            mkdir($claudeDir, 0755, true);
        }

        file_put_contents($settingsPath, json_encode($merged, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    /**
     * Ensure intelephense is installed globally via npm, or skip if requested.
     * Returns a log/warning line to be surfaced in the install summary.
     *
     * @throws DevAiInstallException
     */
    public function ensureLspDeps(bool $skipLspDeps = false): string
    {
        if ($this->intelephenseEnsurer === null) {
            return '';
        }

        $result = $this->intelephenseEnsurer->ensure($skipLspDeps);

        if ($result->isSkipped()) {
            return '[claude-code] skipped intelephense install (--skip-lsp-deps; general PHP diagnostics will be unavailable)';
        }

        if ($result->isInstalled()) {
            return '[claude-code] installed intelephense';
        }

        return '[claude-code] verified intelephense (already on PATH)';
    }

    // -----------------------------------------------------------------------
    // Private helpers
    // -----------------------------------------------------------------------

    private function buildClaudeMd(): string
    {
        return <<<'CLAUDE'
# Project Instructions

@AGENTS.md

## Marko AI tooling

Marko ships task-oriented capabilities through three Claude Code plugins (installed automatically when you trust this project folder):

- `marko-skills@marko` — scaffolding skills (e.g. `/marko-skills:create-module`, `/marko-skills:create-plugin`)
- `marko-lsp@marko` — Marko-aware language server providing real-time diagnostics
- `marko-mcp@marko` — MCP server exposing codebase introspection (`search_docs`, `find_event_observers`, `validate_module`, etc.)

## Working with Marko skills

When a Marko skill loads, **the skill is the canonical specification.** Do not infer module/plugin structure from sibling code in this project — siblings may have drifted from spec. Use the skill's bundled templates verbatim, substituting only the placeholders the skill calls out (e.g. `{{vendor}}`, `{{name}}`).

After writing or editing files, expect LSP diagnostics from `marko-lsp` to surface in the same turn. Resolve all reported diagnostics before declaring the task complete — diagnostics are the verification gate, not optional warnings.

## Working with Marko docs and MCP tools

When the user asks about Marko framework concepts, package APIs, configuration options, or "how does X work" — call `search_docs` from `marko-mcp` first. The MCP returns authoritative content from the Marko docs index. Do NOT infer answers from `vendor/marko/*` source files when `search_docs` can answer.

Other `marko-mcp` tools to use proactively:

- `list_modules` — when you need to know what packages/modules are installed in this project
- `validate_module` — after creating or editing a module, before declaring it done
- `find_event_observers` — when tracing event flow for a given event class
- `find_plugins_targeting` — when tracing plugin (intercept) chains for a target class
- `resolve_preference` — when checking what concrete class a Preference resolves to
- `check_config_key` — when verifying a config key exists before referencing it in code

These tools answer faster and more accurately than `grep` over `vendor/`, and they reflect runtime resolution (preferences, plugin order) that grep cannot see.
CLAUDE;
    }

    /**
     * @return array<string, mixed>
     */
    private function readExistingSettings(string $settingsPath): array
    {
        if (!is_file($settingsPath)) {
            return [];
        }
        $decoded = json_decode((string) file_get_contents($settingsPath), true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param array<string, mixed> $existing
     * @throws DevAiInstallException
     */
    private function assertNotAlreadyRegistered(array $existing, string $projectRoot, bool $force): void
    {
        if ($force) {
            return;
        }

        $hasMarketplace = isset($existing['extraKnownMarketplaces']['marko']);
        $hasPlugin = $this->hasAnyMarkoPlugin($existing);

        if ($hasMarketplace || $hasPlugin) {
            throw DevAiInstallException::alreadyRegistered($projectRoot);
        }
    }

    /**
     * @param array<string, mixed> $existing
     */
    private function hasAnyMarkoPlugin(array $existing): bool
    {
        $plugins = $existing['enabledPlugins'] ?? [];
        if (!is_array($plugins)) {
            return false;
        }
        foreach (array_keys($plugins) as $key) {
            if (str_ends_with((string) $key, '@marko')) {
                return true;
            }
        }

        return false;
    }

    /**
     * Merge marko keys into existing settings, preserving all unrelated keys.
     * When force is in effect, marko-prefixed plugin keys and the marko marketplace
     * entry are replaced; everything else is preserved.
     *
     * @param array<string, mixed> $existing
     * @return array<string, mixed>
     */
    private function mergeSettings(array $existing, string $projectRoot): array
    {
        $merged = $existing;

        // Marketplace
        $merged['extraKnownMarketplaces'] = $existing['extraKnownMarketplaces'] ?? [];
        $merged['extraKnownMarketplaces']['marko'] = $this->buildMarketplaceEntry($projectRoot);

        // enabledPlugins — keep unrelated plugins, replace *@marko ones
        $currentPlugins = $existing['enabledPlugins'] ?? [];
        if (!is_array($currentPlugins)) {
            $currentPlugins = [];
        }
        // Remove old marko plugin entries
        foreach (array_keys($currentPlugins) as $key) {
            if (str_ends_with((string) $key, '@marko')) {
                unset($currentPlugins[$key]);
            }
        }
        // Add canonical marko plugin entries
        $currentPlugins['marko-skills@marko'] = true;
        $currentPlugins['marko-lsp@marko'] = true;
        $currentPlugins['marko-mcp@marko'] = true;
        $merged['enabledPlugins'] = $currentPlugins;

        return $merged;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildMarketplaceEntry(string $projectRoot): array
    {
        if ($this->isMonorepo($projectRoot)) {
            return ['source' => ['source' => 'local', 'path' => '.']];
        }

        return ['source' => ['source' => 'github', 'repo' => 'marko-php/marko']];
    }

    private function isMonorepo(string $projectRoot): bool
    {
        return is_dir($projectRoot . '/packages/claude-plugins');
    }

    private function cleanupLegacyLspFile(string $projectRoot): void
    {
        $lspFile = $projectRoot . '/.claude/plugins/marko/.lsp.json';
        if (is_file($lspFile)) {
            unlink($lspFile);
        }

        // Remove directory if now empty
        $lspDir = $projectRoot . '/.claude/plugins/marko';
        if (is_dir($lspDir) && count(scandir($lspDir)) === 2) {
            rmdir($lspDir);
        }
    }

    private function cleanupLegacyMcpServer(): void
    {
        $listResult = $this->runner->run('claude', ['mcp', 'list']);
        if ($this->mcpListContainsServer($listResult['stdout'], 'marko-mcp')) {
            $this->runner->run('claude', ['mcp', 'remove', 'marko-mcp']);
        }
    }

    /**
     * Match the server name as a leading line token in `claude mcp list` output,
     * not a naive substring — otherwise "marko-mcp" would false-positive on
     * "marko-mcp-staging".
     */
    private function mcpListContainsServer(string $listStdout, string $serverName): bool
    {
        $pattern = '/^' . preg_quote($serverName, '/') . '(?:\s|:|$)/m';

        return preg_match($pattern, $listStdout) === 1;
    }
}
