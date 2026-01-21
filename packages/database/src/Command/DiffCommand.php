<?php

declare(strict_types=1);

namespace Marko\Database\Command;

use Marko\Core\Attributes\Command;
use Marko\Core\Command\CommandInterface;
use Marko\Core\Command\Input;
use Marko\Core\Command\Output;
use Marko\Database\Diff\DiffCalculator;
use Marko\Database\Diff\SchemaDiff;
use Marko\Database\Diff\TableDiff;
use Marko\Database\Entity\EntityDiscovery;
use Marko\Database\Entity\EntityMetadataFactory;
use Marko\Database\Entity\SchemaBuilder;
use Marko\Database\Introspection\IntrospectorInterface;
use Marko\Database\Schema\Table;

#[Command(name: 'db:diff', description: 'Show differences between entity schema and database')]
class DiffCommand implements CommandInterface
{
    public function __construct(
        private EntityDiscovery $discovery,
        private IntrospectorInterface $introspector,
        private EntityMetadataFactory $metadataFactory,
        private SchemaBuilder $schemaBuilder,
        private DiffCalculator $diffCalculator,
        private string $vendorPath,
        private string $modulesPath,
        private string $appPath,
    ) {}

    public function execute(
        Input $input,
        Output $output,
    ): int {
        // Discover all entities
        $entityClasses = array_merge(
            $this->discovery->discoverInVendor($this->vendorPath),
            $this->discovery->discoverInModules($this->modulesPath),
            $this->discovery->discoverInApp($this->appPath),
        );

        // Build entity schema
        $entitySchema = $this->buildEntitySchema($entityClasses);

        // Get database schema
        $databaseSchema = $this->getDatabaseSchema();

        // Calculate diff
        $diff = $this->diffCalculator->calculate($entitySchema, $databaseSchema);

        // Display results
        if ($diff->isEmpty()) {
            $output->writeLine('No changes detected.');

            return 0;
        }

        $this->displayDiff($diff, $output);

        return 1;
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
     * Display the diff output.
     */
    private function displayDiff(
        SchemaDiff $diff,
        Output $output,
    ): void {
        $hasDestructive = $diff->hasDestructiveChanges();

        if ($hasDestructive) {
            $output->writeLine('[DESTRUCTIVE] The following changes will cause data loss:');
            $output->writeLine('');
        }

        // Display tables to create
        foreach ($diff->tablesToCreate as $table) {
            $output->writeLine("Create table: {$table->name}");
            $this->displayTableColumns($table, $output);
        }

        // Display tables to drop (destructive)
        foreach ($diff->tablesToDrop as $table) {
            $output->writeLine("[DESTRUCTIVE] Drop table: {$table->name}");
        }

        // Display tables to alter
        foreach ($diff->tablesToAlter as $tableName => $tableDiff) {
            $output->writeLine("Alter table: {$tableName}");
            $this->displayTableDiff($tableDiff, $output);
        }
    }

    /**
     * Display columns for a new table.
     */
    private function displayTableColumns(
        Table $table,
        Output $output,
    ): void {
        foreach ($table->columns as $column) {
            $output->writeLine("  Add column: {$column->name}");
        }

        foreach ($table->indexes as $index) {
            $output->writeLine("  Add index: {$index->name}");
        }
    }

    /**
     * Display changes for an altered table.
     */
    private function displayTableDiff(
        TableDiff $diff,
        Output $output,
    ): void {
        // Columns to add
        foreach ($diff->columnsToAdd as $column) {
            $output->writeLine("  Add column: {$column->name}");
        }

        // Columns to drop (destructive)
        foreach ($diff->columnsToDrop as $column) {
            $output->writeLine("  [DESTRUCTIVE] Drop column: {$column->name}");
        }

        // Columns to modify
        foreach ($diff->columnsToModify as $column) {
            $output->writeLine("  Modify column: {$column->name}");
        }

        // Indexes to add
        foreach ($diff->indexesToAdd as $index) {
            $output->writeLine("  Add index: {$index->name}");
        }

        // Indexes to drop
        foreach ($diff->indexesToDrop as $index) {
            $output->writeLine("  Drop index: {$index->name}");
        }

        // Foreign keys to add
        foreach ($diff->foreignKeysToAdd as $foreignKey) {
            $output->writeLine("  Add foreign key: {$foreignKey->name}");
        }

        // Foreign keys to drop
        foreach ($diff->foreignKeysToDrop as $foreignKey) {
            $output->writeLine("  Drop foreign key: {$foreignKey->name}");
        }
    }
}
