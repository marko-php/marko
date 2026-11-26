<?php

declare(strict_types=1);

namespace Marko\Database\Migration;

use Closure;
use Marko\Database\Connection\ConnectionInterface;
use Marko\Database\Exceptions\MigrationException;
use Throwable;

/**
 * Orchestrates data migration execution.
 *
 * Data migrations run in production and are tracked alongside
 * schema migrations in the same migrations table.
 */
class DataMigrator
{
    private bool $tableCreated = false;

    public function __construct(
        private readonly ConnectionInterface $connection,
        private readonly MigrationRepository $repository,
        private readonly DataMigrationDiscovery $discovery,
        private readonly MigrationTransaction $migrationTransaction = new MigrationTransaction(),
    ) {}

    /**
     * Run all pending data migrations.
     *
     * @return array<string> List of applied migration names
     * @throws MigrationException If a migration fails
     */
    public function migrate(): array
    {
        $this->ensureTable();

        $pending = $this->getPendingInternal();

        if (empty($pending)) {
            return [];
        }

        $batch = $this->repository->getNextBatchNumber($this->connection);
        $applied = [];

        foreach ($pending as $migration) {
            $this->runMigration(
                $migration,
                'up',
                fn () => $this->repository->record($this->connection, $migration['name'], $batch),
            );
            $applied[] = $migration['name'];
        }

        return $applied;
    }

    /**
     * Rollback the last batch of migrations.
     *
     * @return array<string> List of rolled back migration names
     * @throws MigrationException If a rollback fails
     */
    public function rollback(): array
    {
        $this->ensureTable();

        $migrationNames = $this->repository->getLastBatchMigrations($this->connection);
        $rolledBack = [];

        // Get all discovered migrations for path lookup
        $discovered = $this->discovery->discover();
        $migrationsByName = [];

        foreach ($discovered as $migration) {
            $migrationsByName[$migration['name']] = $migration;
        }

        foreach ($migrationNames as $name) {
            if (!isset($migrationsByName[$name])) {
                throw MigrationException::migrationNotFound($name);
            }

            $this->runMigration(
                $migrationsByName[$name],
                'down',
                fn () => $this->repository->delete($this->connection, $name),
            );
            $rolledBack[] = $name;
        }

        return $rolledBack;
    }

    /**
     * Get all applied data migrations.
     *
     * @return array<string>
     */
    public function getApplied(): array
    {
        $this->ensureTable();

        return $this->repository->getApplied($this->connection);
    }

    /**
     * Get all pending data migrations.
     *
     * @return array<array{name: string, path: string, source: string}>
     */
    public function getPending(): array
    {
        $this->ensureTable();

        return $this->getPendingInternal();
    }

    /**
     * Ensure the migrations table exists.
     */
    private function ensureTable(): void
    {
        if ($this->tableCreated) {
            return;
        }

        $this->repository->createTable($this->connection);
        $this->tableCreated = true;
    }

    /**
     * Internal method to get pending migrations (assumes table exists).
     *
     * @return array<array{name: string, path: string, source: string}>
     */
    private function getPendingInternal(): array
    {
        $applied = $this->repository->getApplied($this->connection);
        $all = $this->discovery->discover();

        $pending = array_filter(
            $all,
            fn (array $migration): bool => !in_array($migration['name'], $applied, true),
        );

        return array_values($pending);
    }

    /**
     * Run a single migration.
     *
     * @param array{name: string, path: string, source: string} $migration
     * @param Closure(): void $bookkeeping Runs after the migration, in the same transaction when there is one
     * @throws MigrationException If migration fails
     */
    private function runMigration(
        array $migration,
        string $direction,
        Closure $bookkeeping,
    ): void {
        $name = $migration['name'];
        $instance = $this->loadMigration($migration);

        $this->migrationTransaction->run(
            $name,
            $instance,
            $this->connection,
            function () use ($instance, $direction, $bookkeeping): void {
                if ($direction === 'up') {
                    $instance->up($this->connection);
                } else {
                    $instance->down($this->connection);
                }

                $bookkeeping();
            },
        );
    }

    /**
     * Require the migration file and check it returns a data migration.
     *
     * @param array{name: string, path: string, source: string} $migration
     * @throws MigrationException If the file is missing, fails to load or returns something else
     */
    private function loadMigration(
        array $migration,
    ): DataMigration {
        $name = $migration['name'];

        if (!file_exists($migration['path'])) {
            throw MigrationException::migrationNotFound($name);
        }

        try {
            $instance = require $migration['path'];
        } catch (Throwable $e) {
            throw MigrationException::migrationFailed($name, $e->getMessage(), $e);
        }

        if (!$instance instanceof DataMigration) {
            throw MigrationException::invalidMigration($name);
        }

        return $instance;
    }
}
