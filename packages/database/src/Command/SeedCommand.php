<?php

declare(strict_types=1);

namespace Marko\Database\Command;

use Marko\Core\Attributes\Command;
use Marko\Core\Command\CommandInterface;
use Marko\Core\Command\Input;
use Marko\Core\Command\Output;
use Marko\Database\Connection\ConnectionInterface;
use Marko\Database\Exceptions\SeederException;
use Marko\Database\Seed\SeederDefinition;
use Marko\Database\Seed\SeederDiscovery;
use Marko\Database\Seed\SeederRunner;

#[Command(name: 'db:seed', description: 'Run database seeders')]
class SeedCommand implements CommandInterface
{
    public function __construct(
        private SeederDiscovery $discovery,
        private SeederRunner $runner,
        private ConnectionInterface $connection,
        private string $vendorPath,
        private string $modulesPath,
        private string $appPath,
        private bool $isProduction = false,
    ) {}

    public function execute(
        Input $input,
        Output $output,
    ): int {
        // Block in production - no --force flag support
        if ($this->isProduction) {
            $output->writeLine('Error: Seeders cannot be run in production environment.');
            $output->writeLine('Seeders are meant for development and testing only.');

            return 1;
        }

        // Discover all seeders
        $definitions = array_merge(
            $this->discovery->discoverInVendor($this->vendorPath),
            $this->discovery->discoverInModules($this->modulesPath),
            $this->discovery->discoverInApp($this->appPath),
        );

        if ($definitions === []) {
            $output->writeLine('No seeders found.');

            return 0;
        }

        // Sort definitions by order
        usort($definitions, fn ($a, $b) => $a->order <=> $b->order);

        // Check for --class option
        $specificClass = $this->parseClassOption($input);

        if ($specificClass !== null) {
            return $this->runSpecificSeeder($specificClass, $definitions, $output);
        }

        return $this->runAllSeeders($definitions, $output);
    }

    /**
     * Parse the --class option from input arguments.
     */
    private function parseClassOption(
        Input $input,
    ): ?string {
        foreach ($input->getArguments() as $arg) {
            if (str_starts_with($arg, '--class=')) {
                return substr($arg, 8);
            }
        }

        return null;
    }

    /**
     * Run a specific seeder by name.
     *
     * @param array<SeederDefinition> $definitions
     */
    private function runSpecificSeeder(
        string $name,
        array $definitions,
        Output $output,
    ): int {
        try {
            $output->writeLine("Running seeder: $name");
            $this->runner->runByName($name, $definitions, $this->connection);
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
        Output $output,
    ): int {
        try {
            foreach ($definitions as $definition) {
                $output->writeLine("Running seeder: $definition->name");
            }

            $this->runner->runAll($definitions, $this->connection);
            $output->writeLine('All seeders completed successfully.');

            return 0;
        } catch (SeederException $e) {
            $output->writeLine("Error: {$e->getMessage()}");

            return 1;
        }
    }
}
