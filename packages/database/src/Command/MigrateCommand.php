<?php

declare(strict_types=1);

namespace Marko\Database\Command;

use Marko\Core\Attributes\Command;
use Marko\Core\Command\CommandInterface;
use Marko\Core\Command\Input;
use Marko\Core\Command\Output;
use Marko\Core\Path\ProjectPaths;
use Marko\Database\Diff\DiffCalculator;
use Marko\Database\Diff\SchemaDiff;
use Marko\Database\Diff\SqlGeneratorInterface;
use Marko\Database\Entity\EntityDiscovery;
use Marko\Database\Entity\EntityMetadataFactory;
use Marko\Database\Entity\SchemaBuilder;
use Marko\Database\Exceptions\EntityException;
use Marko\Database\Exceptions\MigrationException;
use Marko\Database\Introspection\IntrospectorInterface;
use Marko\Database\Migration\DataMigrator;
use Marko\Database\Migration\MigrationGenerator;
use Marko\Database\Migration\Migrator;
use Marko\Database\Schema\Table;

/** @noinspection PhpUnused */
#[Command(name: 'db:migrate', description: 'Apply database migrations')]
readonly class MigrateCommand implements CommandInterface
{
    public function __construct(
        private Migrator $migrator,
        private DataMigrator $dataMigrator,
        private MigrationGenerator $migrationGenerator,
        private EntityDiscovery $entityDiscovery,
        private IntrospectorInterface $introspector,
        private EntityMetadataFactory $metadataFactory,
        private SchemaBuilder $schemaBuilder,
        private DiffCalculator $diffCalculator,
        private SqlGeneratorInterface $sqlGenerator,
        private ProjectPaths $paths,
        private bool $isProduction = false,
    ) {}

    /**
     * @throws EntityException
     */
    public function execute(
        Input $input,
        Output $output,
    ): int {
        $verbose = $this->isVerbose($input);
        $noGenerate = $this->hasFlag($input, '--no-generate');

        // Get pending migrations
        $schemaPending = $this->migrator->getPending();
        $dataPending = $this->dataMigrator->getPending();

        $schemaCount = 0;
        $dataCount = 0;

        // Apply existing schema migrations first
        if (!empty($schemaPending)) {
            foreach ($schemaPending as $migration) {
                $output->writeLine("Migrating: $migration");
            }

            // Show SQL statements in verbose mode
            if ($verbose) {
                $diff = $this->calculateDiff();
                $statements = $this->sqlGenerator->generateUp($diff);

                if (!empty($statements)) {
                    $output->writeLine('');
                    $output->writeLine('SQL statements:');

                    foreach ($statements as $sql) {
                        $output->writeLine("  $sql");
                    }

                    $output->writeLine('');
                }
            }

            try {
                $applied = $this->migrator->migrate();
                $schemaCount = count($applied);

                if ($schemaCount > 0) {
                    $output->writeLine("Applied $schemaCount schema migration(s).");
                }
            } catch (MigrationException $e) {
                $output->writeLine('');
                $output->writeLine("Error: {$e->getMessage()}");

                return 1;
            }
        }

        // Apply data migrations
        if (!empty($dataPending)) {
            if ($schemaCount > 0) {
                $output->writeLine('');
            }

            foreach ($dataPending as $migration) {
                $output->writeLine("Data migrating: {$migration['name']}");
            }

            try {
                $dataApplied = $this->dataMigrator->migrate();
                $dataCount = count($dataApplied);

                if ($dataCount > 0) {
                    $output->writeLine("Applied $dataCount data migration(s).");
                }
            } catch (MigrationException $e) {
                $output->writeLine('');
                $output->writeLine("Error: {$e->getMessage()}");

                return 1;
            }
        }

        // After running existing migrations, check for entity diffs in development mode
        // Skip if --no-generate flag is passed
        if (!$this->isProduction && !$noGenerate) {
            $generatedPaths = $this->generateMigrationsFromDiff($output, $verbose);

            // If new migrations were generated, run them
            if (!empty($generatedPaths)) {
                $newPending = $this->migrator->getPending();
                if (!empty($newPending)) {
                    foreach ($newPending as $migration) {
                        $output->writeLine("Migrating: $migration");
                    }

                    try {
                        $newApplied = $this->migrator->migrate();
                        $newCount = count($newApplied);

                        if ($newCount > 0) {
                            $output->writeLine("Applied $newCount schema migration(s).");
                            $schemaCount += $newCount;
                        }
                    } catch (MigrationException $e) {
                        $output->writeLine('');
                        $output->writeLine("Error: {$e->getMessage()}");

                        return 1;
                    }
                }
            }
        }

        // Nothing was done
        if ($schemaCount === 0 && $dataCount === 0) {
            // Check if there are entity diffs in production mode
            if ($this->isProduction) {
                $diff = $this->calculateDiff();
                if (!$diff->isEmpty()) {
                    $output->writeLine('Warning: Entity schema differs from database.');
                    $output->writeLine('Run db:migrate in development to generate migrations.');
                }
            }

            $output->writeLine('Nothing to migrate.');

            return 0;
        }

        $output->writeLine('');
        $output->writeLine('Migration complete.');

        return 0;
    }

    /**
     * Generate migrations from entity/database diff in development mode.
     *
     * @return array<string> Paths to generated migration files
     * @throws EntityException
     */
    private function generateMigrationsFromDiff(
        Output $output,
        bool $verbose,
    ): array {
        $diff = $this->calculateDiff();

        if ($diff->isEmpty()) {
            return [];
        }

        $paths = $this->migrationGenerator->generate($diff);

        if (!empty($paths)) {
            foreach ($paths as $path) {
                $filename = basename($path);
                $output->writeLine("Generated: $filename");
            }

            if ($verbose) {
                $statements = $this->sqlGenerator->generateUp($diff);

                if (!empty($statements)) {
                    $output->writeLine('');
                    $output->writeLine('SQL statements:');

                    foreach ($statements as $sql) {
                        $output->writeLine("  $sql");
                    }
                }
            }

            $output->writeLine('');
        }

        return $paths;
    }

    /**
     * Calculate the diff between entities and database.
     *
     * @throws EntityException
     */
    private function calculateDiff(): SchemaDiff
    {
        // Discover entities
        $entityClasses = array_merge(
            $this->entityDiscovery->discoverInVendor($this->paths->vendor),
            $this->entityDiscovery->discoverInModules($this->paths->modules),
            $this->entityDiscovery->discoverInApp($this->paths->app),
        );

        // Build entity schema
        $entitySchema = $this->buildEntitySchema($entityClasses);

        // Get database schema
        $databaseSchema = $this->getDatabaseSchema();

        // Calculate diff
        return $this->diffCalculator->calculate($entitySchema, $databaseSchema);
    }

    /**
     * Build schema from entity classes.
     *
     * @param array<class-string> $entityClasses
     * @return array<string, Table>
     * @throws EntityException
     */
    private function buildEntitySchema(
        array $entityClasses,
    ): array {
        $schema = [];

        foreach ($entityClasses as $entityClass) {
            $metadata = $this->metadataFactory->parse($entityClass);
            $table = $this->schemaBuilder->build($metadata);
            $schema[$table->name] = $table;
        }

        return $schema;
    }

    /**
     * Framework tables to exclude from diff (not entity-managed).
     */
    private const array EXCLUDED_TABLES = [
        'migrations',
    ];

    /**
     * Get current database schema.
     *
     * @return array<string, Table>
     */
    private function getDatabaseSchema(): array
    {
        $schema = [];

        foreach ($this->introspector->getTables() as $tableName) {
            // Skip framework tables that aren't entity-managed
            if (in_array($tableName, self::EXCLUDED_TABLES, true)) {
                continue;
            }

            $table = $this->introspector->getTable($tableName);
            if ($table !== null) {
                $schema[$tableName] = $table;
            }
        }

        return $schema;
    }

    /**
     * Check if verbose flag is set.
     */
    private function isVerbose(
        Input $input,
    ): bool {
        return $this->hasFlag($input, '--verbose') || $this->hasFlag($input, '-v');
    }

    /**
     * Check if a flag is present in the input arguments.
     */
    private function hasFlag(
        Input $input,
        string $flag,
    ): bool {
        return array_any($input->getArguments(), fn ($arg) => $arg === $flag);
    }
}
