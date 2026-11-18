<?php

declare(strict_types=1);

namespace Marko\Queue\Database\Tests\Fixtures;

use Marko\Database\Connection\ConnectionInterface;
use Marko\Database\Diff\DiffCalculator;
use Marko\Database\Diff\ExpressionDefaultCanonicalizer;
use Marko\Database\Diff\SchemaDiff;
use Marko\Database\Diff\SqlGeneratorInterface;
use Marko\Database\Entity\EntityMetadataFactory;
use Marko\Database\Entity\SchemaBuilder;
use Marko\Database\Exceptions\EntityException;
use Marko\Database\Exceptions\MigrationException;
use Marko\Database\Introspection\IntrospectorInterface;
use Marko\Database\Schema\Table;
use Marko\Queue\Database\Entity\DatabaseFailedJob;
use Marko\Queue\Database\Entity\DatabaseJob;

/**
 * Builds the jobs and failed_jobs tables on a real server the way db:migrate does: from the entities, through
 * SchemaBuilder and the driver's generator.
 */
class QueueTables
{
    /** @var list<class-string> */
    public const array ENTITIES = [DatabaseJob::class, DatabaseFailedJob::class];

    /** @var list<string> */
    public const array TABLES = ['jobs', 'failed_jobs'];

    public static function drop(
        ConnectionInterface $connection,
    ): void {
        foreach (self::TABLES as $table) {
            $connection->execute('DROP TABLE IF EXISTS ' . $connection->quoteIdentifier($table));
        }
    }

    public static function create(
        ConnectionInterface $connection,
        SqlGeneratorInterface $generator,
    ): void {
        foreach (self::entityTables() as $name => $table) {
            foreach ($generator->generateUp(new SchemaDiff(tablesToCreate: [$name => $table])) as $statement) {
                $connection->execute($statement);
            }
        }
    }

    /**
     * Create the tables with the DDL the docs and the removed migration classes gave, as an application that
     * set them up before the entities existed has them.
     */
    public static function createFromDocumentedDdl(
        ConnectionInterface $connection,
    ): void {
        $connection->execute(<<<'SQL'
            CREATE TABLE jobs (
                id VARCHAR(36) PRIMARY KEY,
                queue VARCHAR(255) NOT NULL DEFAULT 'default',
                payload TEXT NOT NULL,
                attempts INT NOT NULL DEFAULT 0,
                reserved_at TIMESTAMP NULL,
                available_at TIMESTAMP NOT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
            )
            SQL);
        $connection->execute('CREATE INDEX jobs_queue_available_at_index ON jobs (queue, available_at)');
        $connection->execute(<<<'SQL'
            CREATE TABLE failed_jobs (
                id VARCHAR(36) PRIMARY KEY,
                queue VARCHAR(255) NOT NULL,
                payload TEXT NOT NULL,
                exception TEXT NOT NULL,
                failed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
            )
            SQL);
    }

    /**
     * How the database's jobs and failed_jobs tables differ from the entities, computed as db:diff does.
     *
     * @throws EntityException|MigrationException
     */
    public static function diff(
        IntrospectorInterface $introspector,
    ): SchemaDiff {
        $entityTables = self::entityTables();
        $databaseTables = [];

        foreach (array_keys($entityTables) as $name) {
            $table = $introspector->getTable($name);

            if ($table !== null) {
                $databaseTables[$name] = $table;
            }
        }

        return new DiffCalculator()->calculate(
            new ExpressionDefaultCanonicalizer($introspector)->canonicalize($entityTables, $databaseTables),
            $databaseTables,
        );
    }

    /**
     * @return array<string, Table>
     */
    private static function entityTables(): array
    {
        $metadataFactory = new EntityMetadataFactory();
        $schemaBuilder = new SchemaBuilder();
        $tables = [];

        foreach (self::ENTITIES as $entityClass) {
            $table = $schemaBuilder->build($metadataFactory->parse($entityClass));
            $tables[$table->name] = $table;
        }

        return $tables;
    }
}
