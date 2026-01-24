<?php

declare(strict_types=1);

namespace Marko\Database\Command;

use Marko\Core\Attributes\Command;
use Marko\Core\Command\CommandInterface;
use Marko\Core\Command\Input;
use Marko\Core\Command\Output;
use Marko\Database\Connection\ConnectionInterface;
use Marko\Database\Migration\DataMigrator;
use Marko\Database\Migration\MigrationRepository;
use Marko\Database\Migration\Migrator;

/** @noinspection PhpUnused */
#[Command(name: 'db:status', description: 'Show migration status')]
readonly class StatusCommand implements CommandInterface
{
    public function __construct(
        private Migrator $migrator,
        private DataMigrator $dataMigrator,
        private MigrationRepository $repository,
        private ConnectionInterface $connection,
    ) {}

    public function execute(
        Input $input,
        Output $output,
    ): int {
        // Schema migrations
        $schemaApplied = $this->repository->getAppliedWithBatch($this->connection);
        $schemaPending = $this->migrator->getPending();

        // Data migrations
        $dataApplied = $this->dataMigrator->getApplied();
        $dataPending = $this->dataMigrator->getPending();

        $schemaAppliedCount = count($schemaApplied);
        $schemaPendingCount = count($schemaPending);
        $dataAppliedCount = count($dataApplied);
        $dataPendingCount = count($dataPending);

        $totalApplied = $schemaAppliedCount + $dataAppliedCount;
        $totalPending = $schemaPendingCount + $dataPendingCount;

        // No migrations at all
        if ($totalApplied === 0 && $totalPending === 0) {
            $output->writeLine('No migrations found.');

            return 0;
        }

        // Show applied schema migrations
        if ($schemaAppliedCount > 0) {
            $output->writeLine('Applied Schema Migrations:');

            foreach ($schemaApplied as $migration) {
                $output->writeLine("  [{$migration['batch']}] {$migration['name']}");
            }

            $output->writeLine('');
        }

        // Show pending schema migrations
        if ($schemaPendingCount > 0) {
            $output->writeLine('Pending Schema Migrations:');

            foreach ($schemaPending as $name) {
                $output->writeLine("  $name");
            }

            $output->writeLine('');
        }

        // Show applied data migrations
        if ($dataAppliedCount > 0) {
            $output->writeLine('Applied Data Migrations:');

            foreach ($dataApplied as $name) {
                $output->writeLine("  $name");
            }

            $output->writeLine('');
        }

        // Show pending data migrations
        if ($dataPendingCount > 0) {
            $output->writeLine('Pending Data Migrations:');

            foreach ($dataPending as $migration) {
                $output->writeLine("  {$migration['name']}");
            }

            $output->writeLine('');
        }

        // Show summary
        if ($totalPending === 0) {
            $output->writeLine('All migrations applied.');
            $output->writeLine('');
        }

        $output->writeLine("Schema: $schemaAppliedCount applied, $schemaPendingCount pending");
        $output->writeLine("Data: $dataAppliedCount applied, $dataPendingCount pending");

        return 0;
    }
}
