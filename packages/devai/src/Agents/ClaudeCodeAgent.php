<?php

declare(strict_types=1);

namespace Marko\DevAi\Agents;

use Marko\DevAi\Contract\AgentInterface;
use Marko\DevAi\Contract\ReportsInstallNoticesInterface;
use Marko\DevAi\Exceptions\DevAiInstallException;
use Marko\DevAi\Installation\InstallationContext;
use Marko\DevAi\Installation\IntelephenseEnsurerInterface;
use Marko\DevAi\Process\CommandRunnerInterface;
use Marko\DevAi\ValueObject\GuidelinesContent;
use Marko\DevAi\Writing\GuidelinesWriter;

class ClaudeCodeAgent implements AgentInterface, ReportsInstallNoticesInterface
{
    /**
     * Git ref the GitHub plugin marketplace is pinned to when nothing more specific
     * is known (see AgentRegistry). Claude Code installs marketplace plugins on this
     * machine and on every teammate's, so tracking the default branch would run
     * whatever lands on it next.
     */
    public const string DEFAULT_MARKETPLACE_REF = '0.9.0';

    /**
     * The Claude Code plugins devai enables and installs, as `name@marketplace` ids.
     */
    public const array PLUGINS = ['marko-skills@marko', 'marko-lsp@marko', 'marko-mcp@marko'];

    /** @var list<string> */
    private array $notices = [];

    public function __construct(
        private readonly CommandRunnerInterface $commandRunner,
        private readonly ?IntelephenseEnsurerInterface $intelephenseEnsurer = null,
        private readonly string $marketplaceRef = self::DEFAULT_MARKETPLACE_REF,
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
        return $this->commandRunner->isOnPath('claude');
    }

    /**
     * @throws DevAiInstallException
     */
    public function install(
        InstallationContext $ctx,
        string $projectRoot,
    ): void {
        $this->writeGuidelines($ctx->guidelines, $projectRoot);
        $settings = $this->writeSettings($projectRoot, $ctx->force);
        $this->ensureLspDeps($ctx->skipLspDeps);
        $this->installPlugins($projectRoot, $settings);
    }

    /**
     * @return list<string>
     */
    public function takeNotices(): array
    {
        $notices = $this->notices;
        $this->notices = [];

        return $notices;
    }

    private function writeGuidelines(
        GuidelinesContent $content,
        string $projectRoot,
    ): void {
        GuidelinesWriter::write($projectRoot . '/AGENTS.md', $content->body);
        GuidelinesWriter::write($projectRoot . '/CLAUDE.md', $this->buildClaudeMd());
    }

    /**
     * Write (or merge) .claude/settings.json with the Marko marketplace registration
     * and enabled-plugin list. Cleans up any legacy LSP/MCP artifacts.
     *
     * @return array<string, mixed> the settings that were written
     * @throws DevAiInstallException when the marketplace is already registered and --force is not passed
     */
    private function writeSettings(
        string $projectRoot,
        bool $force,
    ): array {
        $this->cleanupLegacyLspFile($projectRoot);
        $this->cleanupLegacyMcpServer();

        $existing = $this->readExistingSettings($this->settingsPath($projectRoot));

        $this->assertNotAlreadyRegistered($existing, $projectRoot, $force);

        $merged = $this->mergeSettings($existing, $projectRoot);
        $this->writeSettingsFile($projectRoot, $merged);

        return $merged;
    }

    /**
     * @param array<string, mixed> $settings
     */
    private function writeSettingsFile(
        string $projectRoot,
        array $settings,
    ): void {
        $claudeDir = $projectRoot . '/.claude';
        if (!is_dir($claudeDir)) {
            mkdir($claudeDir, 0755, true);
        }

        file_put_contents(
            $this->settingsPath($projectRoot),
            json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
        );
    }

    private function settingsPath(string $projectRoot): string
    {
        return $projectRoot . '/.claude/settings.json';
    }

    /**
     * Install the Marko plugins with the claude CLI and verify they landed.
     *
     * Enabling them in .claude/settings.json is not enough on its own: Claude Code
     * only installs enabled plugins at the folder-trust prompt, which shows once and
     * can be skipped, leaving them enabled but never installed (no MCP, LSP or
     * skills, and no error). Installing explicitly closes that gap for external
     * projects. The monorepo's local marketplace (source path ".") is left to the
     * trust prompt, as before.
     *
     * The CLI rewrites settings.json on every project-scope install, so the settings
     * devai built are written again afterwards to keep the file's output stable.
     *
     * @param array<string, mixed> $settings
     * @throws DevAiInstallException when `claude plugin list` does not show every plugin enabled
     */
    private function installPlugins(
        string $projectRoot,
        array $settings,
    ): void {
        if ($this->isMonorepo($projectRoot)) {
            return;
        }

        if (!$this->commandRunner->isOnPath('claude')) {
            $this->notices[] = 'claude CLI not found on PATH, so the Marko plugins are enabled in'
                . ' .claude/settings.json but not installed. Install Claude Code, then run '
                . implode(', ', $this->installCommands())
                . ' from the project root and restart Claude Code.';

            return;
        }

        $failures = [];
        foreach (self::PLUGINS as $plugin) {
            $result = $this->commandRunner->run('claude', ['plugin', 'install', $plugin, '--scope', 'project']);
            if ($result['exitCode'] !== 0) {
                $failures[] = "`claude plugin install $plugin` exited " . $result['exitCode']
                    . ': ' . trim($result['stderr'] . ' ' . $result['stdout']);
            }
        }

        $this->writeSettingsFile($projectRoot, $settings);

        $this->verifyPluginsInstalled($failures);
    }

    /**
     * @param list<string> $installFailures
     * @throws DevAiInstallException
     */
    private function verifyPluginsInstalled(array $installFailures): void
    {
        $result = $this->commandRunner->run('claude', ['plugin', 'list', '--json']);
        $details = $installFailures;

        if ($result['exitCode'] !== 0) {
            $details[] = '`claude plugin list --json` exited ' . $result['exitCode'] . ': ' . trim($result['stderr']);
        }

        $enabled = $result['exitCode'] === 0 ? $this->enabledPluginIds($result['stdout']) : [];
        $missing = array_values(array_diff(self::PLUGINS, $enabled));

        if ($missing !== []) {
            throw DevAiInstallException::claudePluginsNotInstalled($missing, self::PLUGINS, implode("\n", $details));
        }
    }

    /**
     * Plugin ids `claude plugin list --json` reports as enabled. The list also
     * carries project-scope installs made for other folders (with enabled=false),
     * so a plugin only counts when it is enabled for this project.
     *
     * @return list<string>
     */
    private function enabledPluginIds(string $listJson): array
    {
        $decoded = json_decode($listJson, true);
        if (!is_array($decoded)) {
            return [];
        }

        $ids = [];
        foreach ($decoded as $plugin) {
            if (is_array($plugin) && is_string($plugin['id'] ?? null) && ($plugin['enabled'] ?? false) === true) {
                $ids[] = $plugin['id'];
            }
        }

        return $ids;
    }

    /**
     * @return list<string>
     */
    private function installCommands(): array
    {
        return array_map(
            static fn (string $plugin): string => "`claude plugin install $plugin --scope project`",
            self::PLUGINS,
        );
    }

    /**
     * Ensure intelephense is installed globally via npm, or skip if requested.
     *
     * @throws DevAiInstallException
     */
    private function ensureLspDeps(bool $skipLspDeps): void
    {
        $this->intelephenseEnsurer?->ensure($skipLspDeps);
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

Marko ships task-oriented capabilities through three Claude Code plugins (installed by `marko devai:install`; teammates who clone the repo get them at the folder-trust prompt, or by running `claude plugin install <name>@marko --scope project`):

- `marko-skills@marko` — scaffolding skills (e.g. `/marko-skills:create-module`, `/marko-skills:create-plugin`)
- `marko-lsp@marko` — Marko-aware language server providing real-time diagnostics
- `marko-mcp@marko` — MCP server exposing codebase introspection (`search_docs`, `find_event_observers`, `validate_module`, etc.)

## Working with Marko skills

When a Marko skill loads, **the skill is the canonical specification.** Do not infer module/plugin structure from sibling code in this project — siblings may have drifted from spec. Use the skill's bundled templates verbatim, substituting only the placeholders the skill calls out (e.g. `{{vendor}}`, `{{name}}`).

After writing or editing files, expect LSP diagnostics from `marko-lsp` to surface in the same turn. Resolve all reported diagnostics before declaring the task complete — diagnostics are the verification gate, not optional warnings.

There is no build, compile, or reindex step. Routes, modules, observers, plugins, preferences, and config are discovered live from your source files on every request — add a controller with a `#[Route]` and it resolves on the next request, no command required. The `marko-mcp`/`marko-lsp` symbol index auto-rebuilds whenever it is stale, so you never need to run `indexer:rebuild` (or any "reindex") to make new code take effect. If you are reaching for a reindex after scaffolding, that is a Magento reflex Marko does not have — skip it.

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

**Trust your own writes.** The module or route exists because you just wrote it — the runtime serves source files live, so it is available on the next request without any intermediate step. The introspection tools are for discovering pre-existing code, not for confirming code you just created. Do not call `list_modules` or `validate_module` to verify scaffolding you wrote in the same turn — call them when you need to understand what already exists in the project.
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
    private function assertNotAlreadyRegistered(
        array $existing,
        string $projectRoot,
        bool $force,
    ): void {
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
    private function mergeSettings(
        array $existing,
        string $projectRoot,
    ): array {
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
        foreach (self::PLUGINS as $plugin) {
            $currentPlugins[$plugin] = true;
        }
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

        return ['source' => ['source' => 'github', 'repo' => 'marko-php/marko', 'ref' => $this->marketplaceRef]];
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
        $listResult = $this->commandRunner->run('claude', ['mcp', 'list']);
        if ($this->mcpListContainsServer($listResult['stdout'], 'marko-mcp')) {
            $this->commandRunner->run('claude', ['mcp', 'remove', 'marko-mcp']);
        }
    }

    /**
     * Match the server name as a leading line token in `claude mcp list` output,
     * not a naive substring — otherwise "marko-mcp" would false-positive on
     * "marko-mcp-staging".
     */
    private function mcpListContainsServer(
        string $listStdout,
        string $serverName,
    ): bool {
        $pattern = '/^' . preg_quote($serverName, '/') . '(?:\s|:|$)/m';

        return preg_match($pattern, $listStdout) === 1;
    }
}
