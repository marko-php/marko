<?php

declare(strict_types=1);

namespace Marko\Database\PgSql\Tests\Integration;

use Marko\Database\PgSql\Connection\PgSqlConnection;
use Marko\Database\PgSql\Introspection\PgSqlIntrospector;
use Marko\Database\PgSql\Tests\Fixtures\IntegrationDatabase;

/*
 * Foreign key introspection against a real PostgreSQL server: composite keys come back with their columns paired in
 * order, and constraints that share a name on two tables stay apart. The tests create and drop the fkintro_* tables.
 *
 * Settings come from tests/Fixtures/IntegrationDatabase. Part of the integration-services group.
 */

pest()->group('integration-services');

beforeEach(function (): void {
    $config = IntegrationDatabase::config();

    if ($config === null) {
        $this->markTestSkipped(IntegrationDatabase::SKIP_REASON);
    }

    $this->connection = new PgSqlConnection($config);
    $this->dropTables = function (): void {
        foreach (['fkintro_items', 'fkintro_notes', 'fkintro_orders'] as $table) {
            $this->connection->execute("DROP TABLE IF EXISTS $table CASCADE");
        }
    };
    ($this->dropTables)();

    $this->introspector = new PgSqlIntrospector($this->connection);
});

afterEach(function (): void {
    if (isset($this->dropTables)) {
        ($this->dropTables)();
    }
});

it('introspects a multi-column foreign key with columns and referenced columns paired in order', function (): void {
    $this->connection->execute(
        'CREATE TABLE fkintro_orders (id INT NOT NULL, tenant INT NOT NULL, PRIMARY KEY (tenant, id))',
    );
    $this->connection->execute(
        'CREATE TABLE fkintro_items (
            id INT PRIMARY KEY,
            order_id INT NOT NULL,
            order_tenant INT NOT NULL,
            CONSTRAINT fkintro_items_order_fk FOREIGN KEY (order_tenant, order_id)
                REFERENCES fkintro_orders (tenant, id) ON DELETE CASCADE ON UPDATE RESTRICT
        )',
    );

    $foreignKeys = $this->introspector->getForeignKeys('fkintro_items');

    expect($foreignKeys)->toHaveCount(1)
        ->and($foreignKeys[0]->name)->toBe('fkintro_items_order_fk')
        ->and($foreignKeys[0]->columns)->toBe(['order_tenant', 'order_id'])
        ->and($foreignKeys[0]->referencedTable)->toBe('fkintro_orders')
        ->and($foreignKeys[0]->referencedColumns)->toBe(['tenant', 'id'])
        ->and($foreignKeys[0]->onDelete)->toBe('CASCADE')
        ->and($foreignKeys[0]->onUpdate)->toBe('RESTRICT');
});

it('does not mix foreign key columns of two tables whose constraints share a name', function (): void {
    $this->connection->execute('CREATE TABLE fkintro_orders (id INT PRIMARY KEY, other INT UNIQUE)');
    $this->connection->execute(
        'CREATE TABLE fkintro_items (
            id INT PRIMARY KEY,
            order_id INT NOT NULL,
            CONSTRAINT shared_fk FOREIGN KEY (order_id) REFERENCES fkintro_orders (id)
        )',
    );
    $this->connection->execute(
        'CREATE TABLE fkintro_notes (
            id INT PRIMARY KEY,
            other_id INT NOT NULL,
            CONSTRAINT shared_fk FOREIGN KEY (other_id) REFERENCES fkintro_orders (other)
        )',
    );

    $itemKeys = $this->introspector->getForeignKeys('fkintro_items');
    $noteKeys = $this->introspector->getForeignKeys('fkintro_notes');

    expect($itemKeys)->toHaveCount(1)
        ->and($itemKeys[0]->columns)->toBe(['order_id'])
        ->and($itemKeys[0]->referencedColumns)->toBe(['id'])
        ->and($noteKeys)->toHaveCount(1)
        ->and($noteKeys[0]->columns)->toBe(['other_id'])
        ->and($noteKeys[0]->referencedColumns)->toBe(['other']);
});
