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
use Marko\Database\Exceptions\MigrationException;
use Marko\Database\Introspection\IntrospectorInterface;
use Marko\Database\Migration\MigrationGenerator;
use Marko\Database\Migration\Migrator;
use Marko\Database\Schema\Table;

#[Command(name: 'db:migrate', description: 'Apply database migrations')]
class MigrateCommand implements CommandInterface
{
    public function __construct(
        private Migrator $migrator,
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
        $pending = $this->migrator->getPending();

        // Nothing to do
        if (empty($pending)) {
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

        // Show migrations being applied
        foreach ($pending as $migration) {
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

        // Apply migrations
        try {
            $applied = $this->migrator->migrate();

            $count = count($applied);
            $output->writeLine('');
            $output->writeLine("Applied $count migration(s) successfully.");

            return 0;
        } catch (MigrationException $e) {
            $output->writeLine('');
            $output->writeLine("Error: {$e->getMessage()}");

            return 1;
        }
    }

    /**
     * Generate migrations from entity/database diff in development mode.
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

            $output->writeLine('');
        }
    }

    /**
     * Calculate the diff between entities and database.
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
        foreach ($input->getArguments() as $arg) {
            if ($arg === '--verbose' || $arg === '-v') {
                return true;
            }
        }

        return false;
    }
}
