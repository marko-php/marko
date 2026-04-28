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

    public function execute(Input $input, Output $output): int
    {
        $force = $input->hasOption('force');
        $agentsArg = $input->getOption('agents');
        $driverArg = $input->getOption('docs-driver');
        $gitignoreArg = $input->hasOption('update-gitignore');

        $projectRoot = (string) getcwd();

        if ($agentsArg !== null && $driverArg !== null) {
            $context = new InstallationContext(
                selectedAgents: explode(',', $agentsArg),
                docsDriver: $driverArg,
                updateGitignore: $gitignoreArg,
            );
        } else {
            $detected = [];
            foreach ($this->registry->all($projectRoot) as $name => $agent) {
                if ($agent->isInstalled()) {
                    $detected[] = $name;
                }
            }
            $context = $this->promptUser($detected, $driverArg ?? 'vec', $gitignoreArg, $output);
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

    /** @param list<string> $detectedAgents */
    private function promptUser(
        array $detectedAgents,
        string $docsDriver,
        bool $updateGitignore,
        Output $output,
    ): InstallationContext {
        $output->writeLine('Detected agents: ' . implode(', ', $detectedAgents));
        $output->writeLine("Using docs driver: $docsDriver (default)");

        return new InstallationContext(
            selectedAgents: $detectedAgents,
            docsDriver: $docsDriver,
            updateGitignore: $updateGitignore,
        );
    }
}
