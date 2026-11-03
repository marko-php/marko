<?php

declare(strict_types=1);

namespace Marko\Database\Command;

use Marko\Core\Attributes\Command;
use Marko\Core\Command\CommandInterface;
use Marko\Core\Command\Input;
use Marko\Core\Command\Output;
use Marko\Core\Path\ProjectPaths;
use Marko\Database\Exceptions\SeederException;
use Marko\Database\Seed\SeederDefinition;
use Marko\Database\Seed\SeederDiscoveryInterface;
use Marko\Database\Seed\SeederRunner;

/** @noinspection PhpUnused */
#[Command(
    name: 'db:seed',
    description: 'Run database seeders',
    flags: ['force'],
    destructive: true,
    options: ['class'],
)]
readonly class SeedCommand implements CommandInterface
{
    public function __construct(
        private SeederDiscoveryInterface $discovery,
        private SeederRunner $runner,
        private ProjectPaths $paths,
        private DestructiveCommandGuard $destructiveCommandGuard,
    ) {}

    public function execute(
        Input $input,
        Output $output,
    ): int {
        $refusal = $this->destructiveCommandGuard->check(
            'db:seed',
            'writes seed data to the database',
            $input,
            $output,
        );

        if ($refusal !== null) {
            return $refusal;
        }

        // Discover all seeders
        $definitions = array_merge(
            $this->discovery->discoverInVendor($this->paths->vendor),
            $this->discovery->discoverInModules($this->paths->modules),
            $this->discovery->discoverInApp($this->paths->app),
        );

        if ($definitions === []) {
            $output->writeLine('No seeders found.');

            return 0;
        }

        // Sort definitions by order
        usort($definitions, fn ($a, $b) => $a->order <=> $b->order);

        // Check for --class option
        $specificClass = $this->parseClassOption($input);

        // The guard has already approved the environment; --force carries through to the runner's own check
        $force = $input->hasOption('force');

        if ($specificClass !== null) {
            return $this->runSpecificSeeder($specificClass, $definitions, $force, $output);
        }

        return $this->runAllSeeders($definitions, $force, $output);
    }

    /**
     * Parse the --class option from input arguments.
     */
    private function parseClassOption(
        Input $input,
    ): ?string {
        return $input->getOption('class');
    }

    /**
     * Run a specific seeder by name.
     *
     * @param array<SeederDefinition> $definitions
     */
    private function runSpecificSeeder(
        string $name,
        array $definitions,
        bool $force,
        Output $output,
    ): int {
        try {
            $output->writeLine("Running seeder: $name");
            $this->runner->runByName($name, $definitions, $force);
            $output->writeLine('Seeder completed successfully.');

            return 0;
        } catch (SeederException $e) {
            $output->writeLine("Error: {$e->getMessage()}");

            return 1;
        }
    }

    /**
     * Run all discovered seeders in order.
     *
     * @param array<SeederDefinition> $definitions
     */
    private function runAllSeeders(
        array $definitions,
        bool $force,
        Output $output,
    ): int {
        try {
            foreach ($definitions as $definition) {
                $output->writeLine("Running seeder: $definition->name");
            }

            $this->runner->runAll($definitions, $force);
            $output->writeLine('All seeders completed successfully.');

            return 0;
        } catch (SeederException $e) {
            $output->writeLine("Error: {$e->getMessage()}");

            return 1;
        }
    }
}
