<?php

declare(strict_types=1);

namespace Marko\Database\Sqlite\Tests\Connection;

use Marko\Database\Config\DatabaseConfig;
use Marko\Database\Connection\ConnectionFactoryInterface;
use Marko\Database\Connection\ConnectionInterface;
use Marko\Database\Sqlite\Connection\SqliteConnection;
use Marko\Database\Sqlite\Connection\SqliteConnectionFactory;
use Marko\Database\Sqlite\Exceptions\ConnectionException;

function sqliteMemoryConnection(): SqliteConnection
{
    $connection = new SqliteConnection(DatabaseConfig::fromArray(['driver' => 'sqlite', 'database' => ':memory:']));
    $connection->execute('CREATE TABLE items (id INTEGER PRIMARY KEY, name TEXT, flag INTEGER, payload TEXT)');

    return $connection;
}

describe('SqliteConnection queries', function (): void {
    it('returns rows as associative arrays from query', function (): void {
        $connection = sqliteMemoryConnection();
        $connection->execute('INSERT INTO items (name, flag) VALUES (?, ?), (?, ?)', ['a', 1, 'b', 2]);

        $rows = $connection->query('SELECT name, flag FROM items WHERE flag >= :min ORDER BY id', ['min' => 1]);

        expect($connection)->toBeInstanceOf(ConnectionInterface::class)
            ->and($rows)->toBe([['name' => 'a', 'flag' => 1], ['name' => 'b', 'flag' => 2]]);
    });

    it('returns the affected row count from execute', function (): void {
        $connection = sqliteMemoryConnection();
        $connection->execute('INSERT INTO items (name) VALUES (?), (?), (?)', ['a', 'b', 'c']);

        expect($connection->execute('UPDATE items SET flag = 1 WHERE name != ?', ['a']))->toBe(2);
    });

    it('binds booleans as integers and arrays as json', function (): void {
        $connection = sqliteMemoryConnection();
        $connection->execute(
            'INSERT INTO items (name, flag, payload) VALUES (?, ?, ?)',
            [null, true, ['k' => 'é', 'n' => [1, 2]]],
        );

        $row = $connection->query('SELECT name, flag, typeof(flag) AS type, payload FROM items')[0];

        expect($row['name'])->toBeNull()
            ->and($row['flag'])->toBe(1)
            ->and($row['type'])->toBe('integer')
            ->and($row['payload'])->toBe('{"k":"é","n":[1,2]}')
            ->and(fn () => $connection->execute('INSERT INTO items (payload) VALUES (?)', [[NAN]]))
            ->toThrow(ConnectionException::class);
    });

    it('returns the last inserted rowid from lastInsertId', function (): void {
        $connection = sqliteMemoryConnection();
        $connection->execute('INSERT INTO items (name) VALUES (?)', ['a']);
        $connection->execute('INSERT INTO items (name) VALUES (?)', ['b']);

        expect($connection->lastInsertId())->toBe(2);
    });

    it('reports sqlite as the driver name and supports returning', function (): void {
        $connection = sqliteMemoryConnection();

        expect($connection->driverName())->toBe('sqlite')
            ->and($connection->supportsReturning())->toBeTrue()
            ->and($connection->quoteIdentifier('items.name'))->toBe('"items"."name"');
    });

    it('prepares a statement that executes and fetches rows', function (): void {
        $connection = sqliteMemoryConnection();
        $insert = $connection->prepare('INSERT INTO items (name) VALUES (?)');
        $insert->execute(['a']);
        $insert->execute(['b']);
        $select = $connection->prepare('SELECT name FROM items ORDER BY id');
        $select->execute();

        expect($insert->rowCount())->toBe(1)
            ->and($select->fetch())->toBe(['name' => 'a'])
            ->and($select->fetchAll())->toBe([['name' => 'b']])
            ->and($select->fetch())->toBeNull();
    });

    it('makes a SqliteConnection from the connection factory', function (): void {
        $factory = new SqliteConnectionFactory();
        $connection = $factory->make(DatabaseConfig::fromArray(['driver' => 'sqlite', 'database' => ':memory:']));

        expect($factory)->toBeInstanceOf(ConnectionFactoryInterface::class)
            ->and($connection)->toBeInstanceOf(SqliteConnection::class)
            ->and($connection->isConnected())->toBeFalse();
    });
});
