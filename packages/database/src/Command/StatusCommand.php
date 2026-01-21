<?php

declare(strict_types=1);

namespace Marko\Database\Command;

use Marko\Core\Attributes\Command;
use Marko\Core\Command\CommandInterface;
use Marko\Core\Command\Input;
use Marko\Core\Command\Output;
use Marko\Database\Connection\ConnectionInterface;
use Marko\Database\Migration\MigrationRepository;
use Marko\Database\Migration\Migrator;

#[Command(name: 'db:status', description: 'Show migration status')]
class StatusCommand implements CommandInterface
{
    public function __construct(
        private Migrator $migrator,
        private MigrationRepository $repository,
        private ConnectionInterface $connection,
    ) {}

    public function execute(
        Input $input,
        Output $output,
    ): int {
        $applied = $this->repository->getAppliedWithBatch($this->connection);
        $pending = $this->migrator->getPending();

        $appliedCount = count($applied);
        $pendingCount = count($pending);

        // No migrations at all
        if ($appliedCount === 0 && $pendingCount === 0) {
            $output->writeLine('No migrations found.');

            return 0;
        }

        // Show applied migrations
        if ($appliedCount > 0) {
            $output->writeLine('Applied Migrations:');

            foreach ($applied as $migration) {
                $output->writeLine("  [{$migration['batch']}] {$migration['name']}");
            }

            $output->writeLine('');
        }

        // Show pending migrations
        if ($pendingCount > 0) {
            $output->writeLine('Pending Migrations:');

            foreach ($pending as $name) {
                $output->writeLine("  $name");
            }

            $output->writeLine('');
        } else {
            $output->writeLine('All migrations applied.');
            $output->writeLine('');
        }

        // Show summary
        $output->writeLine("Applied: $appliedCount");
        $output->writeLine("Pending: $pendingCount");

        return 0;
    }
}
