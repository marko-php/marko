<?php

declare(strict_types=1);

namespace Marko\DevAi\Commands;

use Marko\Core\Attributes\Command;
use Marko\Core\Command\CommandInterface;
use Marko\Core\Command\Input;
use Marko\Core\Command\Output;
use Marko\DevAi\Installation\AgentRegistry;
use Marko\DevAi\Installation\InstallationContext;
use Marko\DevAi\Installation\InstallationOrchestrator;

#[Command(name: 'devai:install', description: 'Install Marko AI development tooling for selected agents')]
readonly class InstallCommand implements CommandInterface
{
    public function __construct(
        private InstallationOrchestrator $orchestrator,
        private AgentRegistry $registry,
    ) {}

    public function execute(
        Input $input,
        Output $output,
    ): int
    {
        $force = $input->hasOption('force');
        $agentsArg = $input->getOption('agents');
        $gitignoreArg = $input->hasOption('update-gitignore');

        $projectRoot = (string) getcwd();

        if ($agentsArg !== null) {
            $context = new InstallationContext(
                selectedAgents: explode(',', $agentsArg),
                updateGitignore: $gitignoreArg,
            );
        } else {
            $detected = [];
            foreach ($this->registry->all($projectRoot) as $name => $agent) {
                if ($agent->isInstalled()) {
                    $detected[] = $name;
                }
            }
            $context = $this->buildContextFromDetection($detected, $gitignoreArg, $output);
        }

        $result = $this->orchestrator->install($context, $projectRoot, $force);

        if ($result['status'] === 'skipped') {
            $output->writeLine($result['message'] ?? '');

            return 0;
        }

        $output->writeLine('Installation summary:');
        foreach ($result['log'] ?? [] as $line) {
            $output->writeLine("  - $line");
        }

        $this->printDocsDriverHintIfMissing($projectRoot, $output);

        return 0;
    }

    /**
     * Auto-build an installation context from detected agent CLIs on PATH.
     *
     * This is a non-interactive fallback used when --agents wasn't supplied.
     * It announces what it picked and how to override.
     *
     * @param list<string> $detectedAgents
     */
    private function buildContextFromDetection(
        array $detectedAgents,
        bool $updateGitignore,
        Output $output,
    ): InstallationContext {
        $output->writeLine(
            $detectedAgents === []
                ? 'No agent CLIs detected on PATH.'
                : 'Detected agents: ' . implode(', ', $detectedAgents)
        );
        $output->writeLine('Pass --agents=<name,name> to override.');

        return new InstallationContext(
            selectedAgents: $detectedAgents,
            updateGitignore: $updateGitignore,
        );
    }

    /**
     * If the project has no docs driver installed, print a clear hint pointing
     * at the two options and recommending one. This is the deliberate
     * replacement for the old `--docs-driver` picker — picking a driver is the
     * user's call (explicit composer require), not the installer's.
     */
    private function printDocsDriverHintIfMissing(
        string $projectRoot,
        Output $output,
    ): void {
        $hasFts = is_dir($projectRoot . '/vendor/marko/docs-fts');
        $hasVec = is_dir($projectRoot . '/vendor/marko/docs-vec');

        if ($hasFts || $hasVec) {
            return;
        }

        $output->writeLine('');
        $output->writeLine('Tip: install a docs driver to enable the search_docs MCP tool.');
        $output->writeLine('  composer require --dev marko/docs-fts   [recommended] lexical search, no extra setup');
        $output->writeLine(
            '  composer require --dev marko/docs-vec   semantic search (needs sqlite-vec extension + ONNX model)'
        );
    }
}
