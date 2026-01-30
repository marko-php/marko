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
#[Command(name: 'db:reset', description: 'Rollback all database migrations')]
readonly class ResetCommand implements CommandInterface
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
            $output->writeLine('Error: Reset cannot be run in production environment.');
            $output->writeLine('This command rolls back all migrations and is never allowed in production.');

            return 1;
        }

        try {
            $output->writeLine('Rolling back all migrations...');
            $rolledBack = $this->migrator->reset();

            foreach ($rolledBack as $migration) {
                $output->writeLine("  Rolled back: $migration");
            }

            if ($rolledBack === []) {
                $output->writeLine('Nothing to rollback.');

                return 0;
            }

            $count = count($rolledBack);
            $output->writeLine('');
            $output->writeLine("Reset $count migration(s) successfully.");

            return 0;
        } catch (MigrationException $e) {
            $output->writeLine("Error: {$e->getMessage()}");

            return 1;
        }
    }
}
