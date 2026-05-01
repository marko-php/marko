<?php

declare(strict_types=1);

namespace Marko\DevAi\Commands;

use DateTimeInterface;
use Marko\Core\Attributes\Command;
use Marko\Core\Command\CommandInterface;
use Marko\Core\Command\Input;
use Marko\Core\Command\Output;
use Marko\DevAi\Guidelines\GuidelinesAggregator;
use Marko\DevAi\Installation\InstallationContext;
use Marko\DevAi\Installation\InstallationOrchestrator;

#[Command(name: 'devai:update', description: 'Re-run Marko devai install using prior agent selection')]
class UpdateCommand implements CommandInterface
{
    public function __construct(
        private readonly InstallationOrchestrator $orchestrator,
        private readonly GuidelinesAggregator $aggregator,
    ) {}

    public function execute(
        Input $input,
        Output $output,
    ): int
    {
        $projectRoot = (string) getcwd();
        $marker = $projectRoot . '/.marko/devai.json';

        if (!is_file($marker)) {
            $output->writeLine('No prior install found at .marko/devai.json');
            $output->writeLine('Suggestion: Run `marko devai:install` first to set up agent configuration');

            return 1;
        }

        $config = json_decode((string) file_get_contents($marker), true);

        if (!is_array($config) || !isset($config['agents'])) {
            $output->writeLine('Invalid .marko/devai.json — missing agents');
            $output->writeLine('Suggestion: Re-run `marko devai:install --force` to recreate');

            return 1;
        }

        $currentGuidelines = array_keys($this->aggregator->aggregate());
        $previousGuidelines = $config['guidelines'] ?? [];
        $newPackages = array_diff($currentGuidelines, $previousGuidelines);

        $context = new InstallationContext(
            selectedAgents: $config['agents'],
            updateGitignore: false,
        );

        $result = $this->orchestrator->install($context, $projectRoot, force: true);

        $config['guidelines'] = $currentGuidelines;
        $config['updatedAt'] = date(DateTimeInterface::ATOM);
        file_put_contents($marker, json_encode($config, JSON_PRETTY_PRINT));

        $output->writeLine('Update summary:');
        foreach ($result['log'] ?? [] as $line) {
            $output->writeLine("  - $line");
        }

        if ($newPackages !== []) {
            $output->writeLine('');
            $output->writeLine('New package contributions:');
            foreach ($newPackages as $pkg) {
                $output->writeLine("  + $pkg");
            }
        }

        return 0;
    }
}
