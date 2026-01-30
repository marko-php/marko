<?php

declare(strict_types=1);

namespace Marko\Database\Migration;

use Marko\Core\Path\ProjectPaths;
use Marko\Database\Connection\ConnectionInterface;
use Marko\Database\Exceptions\MigrationException;
use Throwable;

/**
 * Orchestrates migration execution.
 */
class Migrator
{
    private bool $tableCreated = false;

    private readonly string $migrationsPath;

    public function __construct(
        private readonly ConnectionInterface $connection,
        private readonly MigrationRepository $repository,
        ProjectPaths $paths,
    ) {
        $this->migrationsPath = $paths->database . '/migrations';
    }

    /**
     * Run all pending migrations.
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

        foreach ($pending as $name) {
            $this->runMigration($name, 'up');
            $this->repository->record($this->connection, $name, $batch);
            $applied[] = $name;
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

        $migrations = $this->repository->getLastBatchMigrations($this->connection);
        $rolledBack = [];

        foreach ($migrations as $name) {
            $this->runMigration($name, 'down');
            $this->repository->delete($this->connection, $name);
            $rolledBack[] = $name;
        }

        return $rolledBack;
    }

    /**
     * Reset the database by rolling back all migrations.
     *
     * @return array<string> List of rolled back migration names
     * @throws MigrationException If a rollback fails
     */
    public function reset(): array
    {
        $this->ensureTable();

        $applied = $this->repository->getApplied($this->connection);
        $rolledBack = [];

        // Roll back in reverse order
        $migrations = array_reverse($applied);

        foreach ($migrations as $name) {
            $this->runMigration($name, 'down');
            $this->repository->delete($this->connection, $name);
            $rolledBack[] = $name;
        }

        return $rolledBack;
    }

    /**
     * Get all applied migrations.
     *
     * @return array<string>
     */
    public function getApplied(): array
    {
        $this->ensureTable();

        return $this->repository->getApplied($this->connection);
    }

    /**
     * Get all pending migrations.
     *
     * @return array<string>
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
     * @return array<string>
     */
    private function getPendingInternal(): array
    {
        $applied = $this->repository->getApplied($this->connection);
        $all = $this->getMigrationFiles();

        $pending = array_diff($all, $applied);

        // Sort to ensure order
        sort($pending);

        return array_values($pending);
    }

    /**
     * Get all migration file names from the migrations directory.
     *
     * @return array<string>
     */
    private function getMigrationFiles(): array
    {
        if (!is_dir($this->migrationsPath)) {
            return [];
        }

        $files = glob($this->migrationsPath . '/*.php');

        if ($files === false) {
            return [];
        }

        $names = array_map(
            fn (string $file): string => pathinfo($file, PATHINFO_FILENAME),
            $files,
        );

        sort($names);

        return $names;
    }

    /**
     * Run a single migration.
     *
     * @throws MigrationException If migration fails
     */
    private function runMigration(
        string $name,
        string $direction,
    ): void {
        $path = $this->migrationsPath . '/' . $name . '.php';

        if (!file_exists($path)) {
            throw MigrationException::migrationNotFound($name);
        }

        try {
            $migration = require $path;

            if (!$migration instanceof Migration) {
                throw MigrationException::invalidMigration($name);
            }

            if ($direction === 'up') {
                $migration->up($this->connection);
            } else {
                $migration->down($this->connection);
            }
        } catch (MigrationException $e) {
            throw $e;
        } catch (Throwable $e) {
            throw MigrationException::migrationFailed($name, $e->getMessage());
        }
    }
}
