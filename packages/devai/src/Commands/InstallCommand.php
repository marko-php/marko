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
    ): int {
        $force = $input->hasOption('force');
        $agentsArg = $input->getOption('agents');
        $gitignoreArg = $input->hasOption('update-gitignore');
        $skipLspDeps = $input->hasOption('skip-lsp-deps');

        $projectRoot = (string) getcwd();

        if ($agentsArg !== null) {
            $context = new InstallationContext(
                selectedAgents: explode(',', $agentsArg),
                updateGitignore: $gitignoreArg,
                skipLspDeps: $skipLspDeps,
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
                : 'Detected agents: ' . implode(', ', $detectedAgents),
        );
        $output->writeLine('Pass --agents=<name,name> to override.');

        return new InstallationContext(
            selectedAgents: $detectedAgents,
            updateGitignore: $updateGitignore,
            skipLspDeps: false,
        );
    }
}
