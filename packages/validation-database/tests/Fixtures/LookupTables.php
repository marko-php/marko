<?php

declare(strict_types=1);

namespace Marko\Validation\Database\Tests\Fixtures;

use Marko\Database\Connection\ConnectionInterface;
use Marko\Database\Diff\SchemaDiff;
use Marko\Database\Diff\SqlGeneratorInterface;
use Marko\Database\Entity\EntityHydrator;
use Marko\Database\Entity\EntityMetadataFactory;
use Marko\Database\Entity\SchemaBuilder;
use Marko\Database\Exceptions\EntityException;
use Marko\Validation\Database\EntityRecordLookup;
use Marko\Validation\Rules\RecordRules;

/**
 * Builds the lookup_channels and lookup_accounts tables on a real server the way db:migrate does (from the
 * fixture entities, through the driver's generator), seeds them, and wires RecordRules to an EntityRecordLookup
 * on the connection.
 */
class LookupTables
{
    private const array ENTITIES = [
        'lookup_channels' => LookupChannel::class,
        'lookup_accounts' => LookupAccount::class,
    ];

    /**
     * @throws EntityException
     */
    public static function create(
        ConnectionInterface $connection,
        SqlGeneratorInterface $generator,
    ): void {
        $metadataFactory = new EntityMetadataFactory();
        $tables = array_map(
            static fn (string $entity) => new SchemaBuilder()->build($metadataFactory->parse($entity)),
            self::ENTITIES,
        );

        foreach ($generator->generateUp(new SchemaDiff(tablesToCreate: $tables)) as $sql) {
            $connection->execute($sql);
        }

        self::insert($connection, 'lookup_channels', [
            ['id' => 1, 'slug' => 'news', 'display_name' => 'News', 'archived' => false, 'status' => 'live'],
            ['id' => 2, 'slug' => 'old', 'display_name' => 'Old', 'archived' => true, 'status' => 'paused'],
        ]);
        self::insert($connection, 'lookup_accounts', [
            ['id' => 1, 'email_address' => 'Ada@example.com', 'tenant_ref' => 1],
            ['id' => 2, 'email_address' => 'grace@example.com', 'tenant_ref' => 2],
        ]);
    }

    public static function drop(
        ConnectionInterface $connection,
    ): void {
        foreach (array_keys(self::ENTITIES) as $table) {
            $connection->execute('DROP TABLE IF EXISTS ' . $connection->quoteIdentifier($table));
        }
    }

    public static function recordRules(
        ConnectionInterface $connection,
    ): RecordRules {
        $metadataFactory = new EntityMetadataFactory();

        return new RecordRules(
            new EntityRecordLookup($connection, $metadataFactory, new EntityHydrator($metadataFactory)),
        );
    }

    /**
     * @param list<array<string, mixed>> $rows
     */
    private static function insert(
        ConnectionInterface $connection,
        string $table,
        array $rows,
    ): void {
        foreach ($rows as $row) {
            $connection->execute(
                sprintf(
                    'INSERT INTO %s (%s) VALUES (%s)',
                    $connection->quoteIdentifier($table),
                    implode(', ', array_map($connection->quoteIdentifier(...), array_keys($row))),
                    implode(', ', array_fill(0, count($row), '?')),
                ),
                array_values($row),
            );
        }
    }
}
