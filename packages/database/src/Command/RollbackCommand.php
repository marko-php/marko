<?php

declare(strict_types=1);

namespace Marko\Database\Command;

use Marko\Core\Attributes\Command;
use Marko\Core\Command\CommandInterface;
use Marko\Core\Command\Input;
use Marko\Core\Command\Output;
use Marko\Database\Exceptions\MigrationException;
use Marko\Database\Migration\Migrator;

#[Command(name: 'db:rollback', description: 'Rollback the last batch of migrations')]
class RollbackCommand implements CommandInterface
{
    public function __construct(
        private Migrator $migrator,
        private string $migrationsPath,
        private bool $isProduction = false,
    ) {}

    public function execute(
        Input $input,
        Output $output,
    ): int {
        // Block in production - no --force flag support
        if ($this->isProduction) {
            $output->writeLine('Error: Rollback cannot be run in production environment.');
            $output->writeLine('Rollback is never allowed in production, even with --force.');

            return 1;
        }

        // Parse --step option
        $steps = $this->parseStepOption($input);

        // Run rollback for each step
        $totalRolledBack = [];

        for ($i = 0; $i < $steps; $i++) {
            try {
                $rolledBack = $this->migrator->rollback();

                if ($rolledBack === []) {
                    break;
                }

                foreach ($rolledBack as $migration) {
                    $output->writeLine("Rolling back: $migration");
                    $totalRolledBack[] = $migration;
                }
            } catch (MigrationException $e) {
                $output->writeLine("Error: {$e->getMessage()}");

                return 1;
            }
        }

        if ($totalRolledBack === []) {
            $output->writeLine('Nothing to rollback.');

            return 0;
        }

        $count = count($totalRolledBack);
        $output->writeLine('');
        $output->writeLine("Rolled back $count migration(s) successfully.");
        $output->writeLine('');
        $output->writeLine('Note: If you have uncommitted migration files that should be deleted,');
        $output->writeLine('you can remove them manually or use git to discard them.');
        $output->writeLine('');
        $output->writeLine('Warning: Your entity schema may now be out of sync with the database.');
        $output->writeLine('Run db:diff to check for schema differences.');

        return 0;
    }

    /**
     * Parse the --step option from input arguments.
     */
    private function parseStepOption(
        Input $input,
    ): int {
        foreach ($input->getArguments() as $arg) {
            if (str_starts_with($arg, '--step=')) {
                $value = (int) substr($arg, 7);

                return max(1, $value);
            }
        }

        return 1;
    }
}
