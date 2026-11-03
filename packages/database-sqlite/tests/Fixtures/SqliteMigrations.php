<?php

declare(strict_types=1);

namespace Marko\Database\Sqlite\Tests\Fixtures;

use Marko\Database\Config\DatabaseTimezoneConfig;
use Marko\Database\Diff\DiffCalculator;
use Marko\Database\Diff\SchemaDiff;
use Marko\Database\Entity\EntityMetadataFactory;
use Marko\Database\Entity\SchemaBuilder;
use Marko\Database\Schema\Table as SchemaTable;
use Marko\Database\Sqlite\Introspection\SqliteIntrospector;
use Marko\Database\Sqlite\Sql\SqliteGenerator;

/**
 * Migrates entity-built schema tables onto a SQLite test database the way db:migrate does.
 */
class SqliteMigrations
{
    public static function schema(
        object $entity,
    ): SchemaTable {
        return new SchemaBuilder()->build(new EntityMetadataFactory()->parse($entity::class));
    }

    /**
     * Migrate the entity table onto the database and return the diff that was applied.
     */
    public static function migrate(
        SqliteTestDatabase $database,
        SchemaTable $entityTable,
    ): SchemaDiff {
        $generator = new SqliteGenerator(DatabaseTimezoneConfig::fromName('UTC'));
        $diff = self::diff($database, $entityTable);

        foreach ($generator->generateUp($diff) as $statement) {
            $database->connection->execute($statement);
        }

        return $diff;
    }

    public static function diff(
        SqliteTestDatabase $database,
        SchemaTable $entityTable,
    ): SchemaDiff {
        $current = new SqliteIntrospector($database->connection)->getTable($entityTable->name);

        return new DiffCalculator()->calculate(
            [$entityTable->name => $entityTable],
            $current !== null ? [$entityTable->name => $current] : [],
        );
    }
}
