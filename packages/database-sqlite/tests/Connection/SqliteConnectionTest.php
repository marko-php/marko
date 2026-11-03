<?php

declare(strict_types=1);

namespace Marko\Database\Sqlite\Tests\Connection;

use FilesystemIterator;
use Marko\Database\Config\DatabaseConfig;
use Marko\Database\Sqlite\Exceptions\ConnectionException;
use Marko\Database\Sqlite\Tests\Fixtures\CapturingSqliteConnection;
use PDO;
use Pdo\Sqlite;
use PDOException;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * @param array<string, mixed> $sqlite
 */
function sqliteTestConnection(
    string $database,
    array $sqlite = [],
): CapturingSqliteConnection {
    $config = ['driver' => 'sqlite', 'database' => $database];

    if ($sqlite !== []) {
        $config['sqlite'] = $sqlite;
    }

    return new CapturingSqliteConnection(DatabaseConfig::fromArray($config));
}

function sqliteTestDir(): string
{
    return sys_get_temp_dir() . '/marko-sqlite-' . bin2hex(random_bytes(8));
}

function sqliteRemoveDir(
    string $dir,
): void {
    if (!is_dir($dir)) {
        return;
    }

    $items = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST,
    );

    foreach ($items as $item) {
        $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
    }

    rmdir($dir);
}

function sqlitePragma(
    CapturingSqliteConnection $connection,
    string $name,
): string {
    return (string) $connection->pdoCreated->query("PRAGMA $name")->fetchColumn();
}

describe('SqliteConnection setup', function (): void {
    beforeEach(function (): void {
        $this->dir = sqliteTestDir();
    });

    afterEach(function (): void {
        sqliteRemoveDir($this->dir);
    });

    it('connects to the file at the configured path and to an in-memory database for :memory:', function (): void {
        mkdir($this->dir);
        $path = $this->dir . '/app.sqlite';
        $file = sqliteTestConnection($path);
        $memory = sqliteTestConnection(':memory:');

        expect($file->isConnected())->toBeFalse()
            ->and($file->getDsn())->toBe("sqlite:$path")
            ->and($memory->getDsn())->toBe('sqlite::memory:');

        $file->connect();
        $memory->connect();

        expect($file->isConnected())->toBeTrue()
            ->and($memory->isConnected())->toBeTrue()
            ->and(file_exists($path))->toBeTrue();

        $file->disconnect();

        expect($file->isConnected())->toBeFalse();
    });

    it('creates the database file and its directory when missing and create_if_missing is true', function (): void {
        $path = $this->dir . '/nested/deeper/app.sqlite';
        $connection = sqliteTestConnection($path);

        $connection->connect();

        expect(is_dir($this->dir . '/nested/deeper'))->toBeTrue()
            ->and(file_exists($path))->toBeTrue();
    });

    it('throws a connection exception when the file is missing and create_if_missing is false', function (): void {
        $path = $this->dir . '/app.sqlite';
        $connection = sqliteTestConnection($path, ['create_if_missing' => false]);

        try {
            $connection->connect();
            $this->fail('Expected a ConnectionException');
        } catch (ConnectionException $e) {
            expect($e->getMessage())->toContain($path)
                ->and($e->getSuggestion())->toContain('create_if_missing')
                ->and($connection->isConnected())->toBeFalse()
                ->and(file_exists($path))->toBeFalse();
        }
    });

    it('enables sqlite extended result codes on connect', function (): void {
        $connection = sqliteTestConnection(':memory:');
        $connection->connect();
        $pdo = $connection->pdoCreated;
        $pdo->exec('CREATE TABLE t (v TEXT UNIQUE)');
        $pdo->exec("INSERT INTO t VALUES ('a')");

        try {
            $pdo->exec("INSERT INTO t VALUES ('a')");
            $this->fail('Expected a PDOException');
        } catch (PDOException $e) {
            expect($e->errorInfo[1])->toBe(2067)
                ->and($pdo->getAttribute(PDO::ATTR_ERRMODE))->toBe(PDO::ERRMODE_EXCEPTION)
                ->and(Sqlite::ATTR_EXTENDED_RESULT_CODES)->toBeInt();
        }
    });

    it('applies the default pragmas on connect and lets config override each one', function (): void {
        mkdir($this->dir);
        $default = sqliteTestConnection($this->dir . '/a.sqlite');
        $default->connect();

        expect(sqlitePragma($default, 'journal_mode'))->toBe('wal')
            ->and(sqlitePragma($default, 'busy_timeout'))->toBe('5000')
            ->and(sqlitePragma($default, 'synchronous'))->toBe('1')
            ->and(sqlitePragma($default, 'foreign_keys'))->toBe('1')
            ->and(sqlitePragma($default, 'temp_store'))->toBe('2');

        $custom = sqliteTestConnection($this->dir . '/b.sqlite', [
            'pragmas' => [
                'journal_mode' => 'DELETE',
                'busy_timeout' => 250,
                'synchronous' => 'FULL',
                'foreign_keys' => 'OFF',
                'temp_store' => 'FILE',
            ],
        ]);
        $custom->connect();

        expect(sqlitePragma($custom, 'journal_mode'))->toBe('delete')
            ->and(sqlitePragma($custom, 'busy_timeout'))->toBe('250')
            ->and(sqlitePragma($custom, 'synchronous'))->toBe('2')
            ->and(sqlitePragma($custom, 'foreign_keys'))->toBe('0')
            ->and(sqlitePragma($custom, 'temp_store'))->toBe('1');

        $memory = sqliteTestConnection(':memory:');
        $memory->connect();

        expect(sqlitePragma($memory, 'journal_mode'))->toBe('memory');
    });

    it(
        'rejects an unknown pragma name or an unsafe pragma value from config with a connection exception',
        function (): void {
            $unknown = sqliteTestConnection(':memory:', ['pragmas' => ['writable_schema' => 1]]);
            $unsafe = sqliteTestConnection(':memory:', ['pragmas' => ['synchronous' => 'NORMAL; DROP TABLE x']]);

            expect(fn () => $unknown->connect())->toThrow(ConnectionException::class, 'writable_schema')
                ->and(fn () => $unsafe->connect())->toThrow(ConnectionException::class, 'synchronous')
                ->and($unknown->isConnected())->toBeFalse()
                ->and($unsafe->isConnected())->toBeFalse();
        },
    );

    it('throws a connection exception when the sqlite library is older than 3.39', function (): void {
        $connection = sqliteTestConnection(':memory:');
        $connection->versionOverride = '3.38.5';

        try {
            $connection->connect();
            $this->fail('Expected a ConnectionException');
        } catch (ConnectionException $e) {
            expect($e->getMessage())->toContain('3.38.5')->toContain('3.39.0')
                ->and($connection->isConnected())->toBeFalse();
        }
    });
});
