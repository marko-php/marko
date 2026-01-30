<?php

declare(strict_types=1);

namespace Marko\Database\Command;

use Marko\Core\Attributes\Command;
use Marko\Core\Command\CommandInterface;
use Marko\Core\Command\Input;
use Marko\Core\Command\Output;
use Marko\Database\Exceptions\MigrationException;
use Marko\Database\Migration\Migrator;

/** @noinspection PhpUnused */
#[Command(name: 'db:rebuild', description: 'Reset and re-run all migrations (clean slate)')]
readonly class RebuildCommand implements CommandInterface
{
    public function __construct(
        private Migrator $migrator,
        private bool $isProduction = false,
    ) {}

    public function execute(
        Input $input,
        Output $output,
    ): int {
        // Block in production - no --force flag support
        if ($this->isProduction) {
            $output->writeLine('Error: Rebuild cannot be run in production environment.');
            $output->writeLine('This command drops all tables and is never allowed in production.');

            return 1;
        }

        try {
            // Reset (rollback all migrations)
            $output->writeLine('Resetting database...');
            $rolledBack = $this->migrator->reset();

            foreach ($rolledBack as $migration) {
                $output->writeLine("  Rolled back: $migration");
            }

            if ($rolledBack === []) {
                $output->writeLine('  No migrations to rollback.');
            }

            // Re-run all migrations
            $output->writeLine('');
            $output->writeLine('Running migrations...');
            $applied = $this->migrator->migrate();

            foreach ($applied as $migration) {
                $output->writeLine("  Migrated: $migration");
            }

            if ($applied === []) {
                $output->writeLine('  No migrations to run.');
            }

            $output->writeLine('');
            $output->writeLine('Database rebuilt successfully.');

            return 0;
        } catch (MigrationException $e) {
            $output->writeLine("Error: {$e->getMessage()}");

            return 1;
        }
    }
}
