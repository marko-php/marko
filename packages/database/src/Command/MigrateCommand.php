<?php

declare(strict_types=1);

namespace Marko\Database\Command;

use Marko\Core\Attributes\Command;
use Marko\Core\Command\CommandInterface;
use Marko\Core\Command\Input;
use Marko\Core\Command\Output;
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
        private string $vendorPath,
        private string $modulesPath,
        private string $appPath,
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

        // In development mode, check for entity diffs and generate migrations
        if (!$this->isProduction) {
            $this->generateMigrationsFromDiff($output, $verbose);
        }

        // Get pending migrations
        $schemaPending = $this->migrator->getPending();
        $dataPending = $this->dataMigrator->getPending();

        // Nothing to do
        if (empty($schemaPending) && empty($dataPending)) {
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

        $schemaCount = 0;
        $dataCount = 0;

        // Apply schema migrations
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

        $output->writeLine('');
        $output->writeLine('Migration complete.');

        return 0;
    }

    /**
     * Generate migrations from entity/database diff in development mode.
     *
     * @throws EntityException
     */
    private function generateMigrationsFromDiff(
        Output $output,
        bool $verbose,
    ): void {
        $diff = $this->calculateDiff();

        if ($diff->isEmpty()) {
            return;
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
            $this->entityDiscovery->discoverInVendor($this->vendorPath),
            $this->entityDiscovery->discoverInModules($this->modulesPath),
            $this->entityDiscovery->discoverInApp($this->appPath),
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
     * Get current database schema.
     *
     * @return array<string, Table>
     */
    private function getDatabaseSchema(): array
    {
        $schema = [];

        foreach ($this->introspector->getTables() as $tableName) {
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
        return array_any($input->getArguments(), fn ($arg) => $arg === '--verbose' || $arg === '-v');
    }
}
