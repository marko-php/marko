<?php

declare(strict_types=1);

namespace Marko\DevAi\Installation;

use DateTimeInterface;
use Marko\DevAi\Contract\SupportsGuidelines;
use Marko\DevAi\Contract\SupportsLsp;
use Marko\DevAi\Contract\SupportsMcp;
use Marko\DevAi\Contract\SupportsSkills;
use Marko\DevAi\Guidelines\GuidelinesAggregator;
use Marko\DevAi\Process\CommandRunnerInterface;
use Marko\DevAi\Rendering\AgentsMdRenderer;
use Marko\DevAi\Rendering\ClaudeMdRenderer;
use Marko\DevAi\Skills\SkillsDistributor;
use Marko\DevAi\ValueObject\LspRegistration;
use Marko\DevAi\ValueObject\McpRegistration;

class InstallationOrchestrator
{
    /** @var list<string> */
    public array $log = [];

    public function __construct(
        private AgentRegistry $registry,
        private AgentsMdRenderer $agentsRenderer,
        private ClaudeMdRenderer $claudeRenderer,
        private GuidelinesAggregator $guidelinesAggregator,
        private SkillsDistributor $skillsDistributor,
        private CommandRunnerInterface $runner,
    ) {}

    /** @return array{status: string, message?: string, log?: list<string>} */
    public function install(
        InstallationContext $ctx,
        string $projectRoot,
        bool $force,
    ): array
    {
        $marker = $projectRoot . '/.marko/devai.json';
        if (is_file($marker) && !$force) {
            return [
                'status' => 'skipped',
                'message' => 'Prior install detected at .marko/devai.json. Use `marko devai:update` to update, or pass --force to re-run.',
            ];
        }

        $guidelines = $this->guidelinesAggregator->aggregate();
        $agentsMd = $this->agentsRenderer->render([
            'projectName' => basename($projectRoot),
            'guidelines' => $guidelines,
        ]);

        $skills = $this->skillsDistributor->collect();
        $previouslyShipped = $this->readPreviouslyShipped($marker);
        $currentlyShipped = $this->extractSkillNames($skills);

        $agents = $this->registry->all($projectRoot);
        $markoBin = $this->resolveMarkoBin($projectRoot);
        $mcp = new McpRegistration(serverName: 'marko-mcp', command: $markoBin, args: ['mcp:serve']);
        $lsp = new LspRegistration(serverName: 'marko-lsp', command: $markoBin, args: ['lsp:serve']);

        foreach ($ctx->selectedAgents as $agentName) {
            if (!isset($agents[$agentName])) {
                continue;
            }
            $agent = $agents[$agentName];

            if ($agent instanceof SupportsGuidelines) {
                $agent->writeGuidelines($agentsMd, $projectRoot);
                $this->log[] = "[$agentName] wrote guidelines";
            }
            if ($agent instanceof SupportsMcp) {
                $agent->registerMcpServer($mcp, $projectRoot);
                $this->log[] = "[$agentName] registered MCP server";
            }
            if ($agent instanceof SupportsLsp) {
                $agent->registerLspServer($lsp, $projectRoot);
                $this->log[] = "[$agentName] registered LSP server";
            }
            if ($agent instanceof SupportsSkills) {
                $agent->distributeSkills($skills, $projectRoot, $previouslyShipped);
                $this->log[] = "[$agentName] distributed " . count($skills) . ' skills';
            }
        }

        $markerDir = $projectRoot . '/.marko';
        if (!is_dir($markerDir)) {
            mkdir($markerDir, 0755, true);
        }
        file_put_contents($marker, json_encode([
            'agents' => $ctx->selectedAgents,
            'shippedSkills' => $currentlyShipped,
            'installedAt' => date(DateTimeInterface::ATOM),
        ], JSON_PRETTY_PRINT));

        if ($ctx->updateGitignore) {
            $this->updateGitignore($projectRoot);
        }

        $this->buildDocsIndex($projectRoot, $markoBin);

        return ['status' => 'installed', 'log' => $this->log];
    }

    /**
     * Build the docs search index so search_docs is wired and queryable
     * by the time devai:install returns.
     *
     * marko/devai hard-requires marko/docs-fts, so a fresh install always has
     * fts present. Users who upgrade to marko/docs-vec swap fts out via
     * Composer's replace mechanism, so the build target shifts to vec.
     * Only one driver is ever installed at a time — no precedence games.
     */
    private function buildDocsIndex(
        string $projectRoot,
        string $markoBin,
    ): void
    {
        if (is_dir($projectRoot . '/vendor/marko/docs-vec')) {
            $command = 'docs-vec:build';
            $driver = 'docs-vec';
        } elseif (is_dir($projectRoot . '/vendor/marko/docs-fts')) {
            $command = 'docs-fts:build';
            $driver = 'docs-fts';
        } else {
            return;
        }

        $result = $this->runner->run($markoBin, [$command]);

        if (($result['exitCode'] ?? 1) === 0) {
            $this->log[] = "[$driver] built docs search index";

            return;
        }

        $stderr = trim((string) ($result['stderr'] ?? ''));
        $hint = $stderr === '' ? '' : " ($stderr)";
        $this->log[] = "[$driver] index build failed$hint — re-run `marko $command` manually";
    }

    /**
     * Read the previously-shipped skill names from the install marker.
     * Returns empty list on first install or if the marker is malformed.
     *
     * @return list<string>
     */
    private function readPreviouslyShipped(string $markerPath): array
    {
        if (!is_file($markerPath)) {
            return [];
        }
        $decoded = json_decode((string) file_get_contents($markerPath), true);
        if (!is_array($decoded) || !isset($decoded['shippedSkills']) || !is_array($decoded['shippedSkills'])) {
            return [];
        }

        return array_values(array_filter($decoded['shippedSkills'], 'is_string'));
    }

    /**
     * Extract top-level skill names (matching skill directory names) from the
     * collected bundles. The bundle's `skills` map uses keys like
     * "skill-name/SKILL.md" or "skill-name/examples/foo.php" — we want the
     * unique first segment.
     *
     * @param list<\Marko\DevAi\ValueObject\SkillBundle> $bundles
     * @return list<string>
     */
    private function extractSkillNames(array $bundles): array
    {
        $names = [];
        foreach ($bundles as $bundle) {
            foreach (array_keys($bundle->skills) as $relativePath) {
                $names[explode('/', $relativePath, 2)[0]] = true;
            }
        }

        return array_keys($names);
    }

    /**
     * Resolve the absolute path to the marko CLI binary for this project.
     *
     * MCP servers and LSP servers are spawned by the agent (Claude Code, Cursor,
     * etc.) whose working directory is not guaranteed to be the project root —
     * and the project root has no `marko` file (the binary lives in
     * `vendor/bin/marko`). Registering an absolute path makes the spawn
     * reliable regardless of cwd or PATH.
     */
    private function resolveMarkoBin(string $projectRoot): string
    {
        return $projectRoot . '/vendor/bin/marko';
    }

    private function updateGitignore(string $projectRoot): void
    {
        $path = $projectRoot . '/.gitignore';
        $existing = is_file($path) ? (string) file_get_contents($path) : '';
        $lines = ['# marko/devai generated files', '.marko/'];
        $additions = '';
        foreach ($lines as $l) {
            if (str_contains($existing, $l)) {
                continue;
            }
            $additions .= $l . "\n";
        }
        if ($additions !== '') {
            $separator = ($existing !== '' && !str_ends_with($existing, "\n")) ? "\n" : '';
            file_put_contents($path, $existing . $separator . $additions);
        }
    }
}
