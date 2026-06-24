<?php

declare(strict_types=1);

namespace Marko\DevAi\Installation;

use DateTimeInterface;
use Marko\DevAi\Exceptions\DevAiInstallException;
use Marko\DevAi\Guidelines\GuidelinesAggregator;
use Marko\DevAi\Process\CommandRunnerInterface;
use Marko\DevAi\Rendering\AgentsMdRenderer;
use Marko\DevAi\Skills\SkillsDistributor;
use Marko\DevAi\ValueObject\McpRegistration;
use Marko\DevAi\ValueObject\SkillBundle;
use Marko\DevAi\Writing\GuidelinesWriter;

class InstallationOrchestrator
{
    /** @var list<string> */
    public array $log = [];

    public function __construct(
        private AgentRegistry $registry,
        private AgentsMdRenderer $agentsRenderer,
        private GuidelinesAggregator $guidelinesAggregator,
        private SkillsDistributor $skillsDistributor,
        private CommandRunnerInterface $runner,
        private DocsDriverResolver $docsDriverResolver = new DocsDriverResolver(),
    ) {}

    /**
     * @return array{status: string, message?: string, log?: list<string>}
     * @throws DevAiInstallException when an agent rejects the install (e.g. already-registered settings without --force)
     */
    public function install(
        InstallationContext $ctx,
        string $projectRoot,
        ?callable $onProgress = null,
    ): array {
        $progress = $onProgress ?? static function (string $message): void {};
        $marker = $projectRoot . '/.marko/devai.json';
        if (is_file($marker) && !$ctx->force) {
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

        $installCtx = $ctx->withInstallData($agentsMd, $skills, $previouslyShipped, $mcp);

        foreach ($ctx->selectedAgents as $agentName) {
            if (!isset($agents[$agentName])) {
                continue;
            }

            $progress('Configuring agent: ' . $agentName . '…');
            $agents[$agentName]->install($installCtx, $projectRoot);
            $this->log[] = "[$agentName] configured";
        }

        foreach (GuidelinesWriter::takeNotices() as $notice) {
            $this->log[] = $notice;
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

        $this->warmFrameworkCaches($projectRoot, $markoBin, $progress);
        $this->buildDocsIndex($projectRoot, $markoBin, $progress);

        return ['status' => 'installed', 'log' => $this->log];
    }

    /**
     * Compile discovery cache and rebuild the code index so the first
     * `mcp:serve` handshake hits the fast path instead of lazily compiling.
     *
     * Failures are non-fatal: a missing command (e.g. marko/codeindexer is not
     * installed) exits non-zero, which is logged as a helpful warning and
     * does not abort the install.
     */
    private function warmFrameworkCaches(
        string $projectRoot,
        string $markoBin,
        callable $progress,
    ): void {
        $commands = [
            'discovery:cache' => '[discovery] compiled discovery cache',
            'indexer:rebuild' => '[indexer] rebuilt code index',
        ];

        foreach ($commands as $command => $successMessage) {
            $progress('Running marko ' . $command . '…');
            $result = $this->runner->run($markoBin, [$command]);

            if (($result['exitCode'] ?? 1) === 0) {
                $this->log[] = $successMessage;
            } else {
                $stderr = trim((string) ($result['stderr'] ?? ''));
                $hint = $stderr === '' ? '' : " ($stderr)";
                $this->log[] = "[cache] $command failed$hint — re-run `marko $command` manually";
            }
        }
    }

    /**
     * Build the docs search index so search_docs is wired and queryable
     * by the time devai:install returns.
     *
     * devai requires only the marko/docs contract — the search driver is a
     * separate package. When a known driver is installed we build its index so
     * search_docs works immediately. When no driver is installed we skip
     * gracefully and tell the user how to add one — a bare install is valid,
     * just without docs search.
     */
    private function buildDocsIndex(
        string $projectRoot,
        string $markoBin,
        callable $progress,
    ): void {
        $package = $this->docsDriverResolver->installedDriver($projectRoot);

        if ($package === null) {
            $this->log[] = '[docs] no search driver installed — run `composer require marko/docs-fts`'
                . ' then `marko docs-fts:build` to enable search_docs';

            return;
        }

        $command = $this->docsDriverResolver->buildCommand($package);
        $driver = substr($package, (int) strpos($package, '/') + 1);

        $progress("Building docs search index ($driver)…");
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
     * @param list<SkillBundle> $bundles
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
     * MCP servers are spawned by the agent (Codex, Cursor, etc.) whose working
     * directory is not guaranteed to be the project root — and the project root
     * has no `marko` file (the binary lives in `vendor/bin/marko`). Registering
     * an absolute path makes the spawn reliable regardless of cwd or PATH.
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
