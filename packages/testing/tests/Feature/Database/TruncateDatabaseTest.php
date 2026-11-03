<?php

declare(strict_types=1);

use Marko\Core\Application;
use Marko\Database\Connection\ConnectionInterface;
use Marko\Database\Introspection\IntrospectorInterface;
use Marko\Testing\Database\TestDatabase;
use Marko\Testing\Database\TruncateDatabase;
use Marko\Testing\Exceptions\DatabaseTestException;
use Marko\Testing\Tests\DatabaseApp\RecordingConnection;
use Marko\Testing\Tests\DatabaseApp\RecordingIntrospector;

use function Marko\Testing\Tests\databaseAppPath;
use function Marko\Testing\Tests\withAppEnv;

/**
 * @return array{truncate: TruncateDatabase, connection: RecordingConnection, introspector: RecordingIntrospector}
 */
function makeTruncateDatabase(): array
{
    $application = Application::boot(databaseAppPath());
    /** @var RecordingConnection $connection */
    $connection = $application->container->get(ConnectionInterface::class);
    /** @var RecordingIntrospector $introspector */
    $introspector = $application->container->get(IntrospectorInterface::class);

    return [
        'truncate' => new TruncateDatabase(new TestDatabase($application)),
        'connection' => $connection,
        'introspector' => $introspector,
    ];
}

describe('TruncateDatabase', function (): void {
    it('lists only entity tables that exist', function (): void {
        ['truncate' => $truncate] = makeTruncateDatabase();

        expect($truncate->tables())->toBe(['shows', 'venues']);
    });

    it('never includes the migrations table', function (): void {
        ['truncate' => $truncate, 'introspector' => $introspector] = makeTruncateDatabase();
        $introspector->tables = ['migrations', 'shows', 'tickets', 'venues'];

        expect($truncate->tables())->toBe(['shows', 'tickets', 'venues'])
            ->not->toContain('migrations');
    });

    it('issues one TRUNCATE RESTART IDENTITY CASCADE on pgsql', function (): void {
        ['truncate' => $truncate, 'connection' => $connection] = makeTruncateDatabase();

        withAppEnv('testing', fn () => $truncate->truncate());

        expect($connection->statements)->toBe(['TRUNCATE TABLE "shows", "venues" RESTART IDENTITY CASCADE']);
    });

    it('disables and re-enables foreign key checks around per-table TRUNCATE on mysql', function (): void {
        ['truncate' => $truncate, 'connection' => $connection] = makeTruncateDatabase();
        $connection->driver = 'mysql';

        withAppEnv('testing', fn () => $truncate->truncate());

        expect($connection->statements)->toBe([
            'SET FOREIGN_KEY_CHECKS = 0',
            'TRUNCATE TABLE `shows`',
            'TRUNCATE TABLE `venues`',
            'SET FOREIGN_KEY_CHECKS = 1',
        ]);
    });

    it("quotes pgsql table names with the connection's delimiter", function (): void {
        ['truncate' => $truncate, 'connection' => $connection] = makeTruncateDatabase();
        $connection->identifierDelimiter = '`';

        withAppEnv('testing', fn () => $truncate->truncate());

        expect($connection->statements)->toBe(['TRUNCATE TABLE `shows`, `venues` RESTART IDENTITY CASCADE']);
    })->issue(338);

    it("quotes mysql table names with the connection's delimiter", function (): void {
        ['truncate' => $truncate, 'connection' => $connection] = makeTruncateDatabase();
        $connection->driver = 'mysql';
        $connection->identifierDelimiter = '"';

        withAppEnv('testing', fn () => $truncate->truncate());

        expect($connection->statements)->toBe([
            'SET FOREIGN_KEY_CHECKS = 0',
            'TRUNCATE TABLE "shows"',
            'TRUNCATE TABLE "venues"',
            'SET FOREIGN_KEY_CHECKS = 1',
        ]);
    })->issue(338);

    it('re-enables foreign key checks on mysql even when a truncate fails', function (): void {
        ['truncate' => $truncate, 'connection' => $connection] = makeTruncateDatabase();
        $connection->driver = 'mysql';
        $connection->failures['`venues`'] = 'lock wait timeout';

        try {
            withAppEnv('testing', fn () => $truncate->truncate());
        } catch (RuntimeException $e) {
            $error = $e->getMessage();
        }

        expect($error ?? null)->toBe('lock wait timeout')
            ->and($connection->statements)->toBe([
                'SET FOREIGN_KEY_CHECKS = 0',
                'TRUNCATE TABLE `shows`',
                'SET FOREIGN_KEY_CHECKS = 1',
            ]);
    });

    it(
        'deletes every entity table with foreign keys off and resets autoincrement on sqlite when sqlite_sequence exists',
        function (): void {
            ['truncate' => $truncate, 'connection' => $connection] = makeTruncateDatabase();
            $connection->driver = 'sqlite';
            $connection->results[TruncateDatabase::SQLITE_SEQUENCE_LOOKUP] = [['name' => 'sqlite_sequence']];

            withAppEnv('testing', fn () => $truncate->truncate());

            expect($connection->statements)->toBe([
                'PRAGMA foreign_keys = OFF',
                'DELETE FROM "shows"',
                'DELETE FROM "venues"',
                TruncateDatabase::SQLITE_SEQUENCE_LOOKUP,
                'DELETE FROM sqlite_sequence WHERE name IN (?, ?)',
                'PRAGMA foreign_keys = ON',
            ]);
        },
    );

    it('skips the autoincrement reset on sqlite when sqlite_sequence does not exist', function (): void {
        ['truncate' => $truncate, 'connection' => $connection] = makeTruncateDatabase();
        $connection->driver = 'sqlite';

        withAppEnv('testing', fn () => $truncate->truncate());

        expect($connection->statements)->toBe([
            'PRAGMA foreign_keys = OFF',
            'DELETE FROM "shows"',
            'DELETE FROM "venues"',
            TruncateDatabase::SQLITE_SEQUENCE_LOOKUP,
            'PRAGMA foreign_keys = ON',
        ]);
    });

    it('turns foreign keys back on in sqlite even when a delete fails', function (): void {
        ['truncate' => $truncate, 'connection' => $connection] = makeTruncateDatabase();
        $connection->driver = 'sqlite';
        $connection->failures['"venues"'] = 'database is locked';

        try {
            withAppEnv('testing', fn () => $truncate->truncate());
        } catch (RuntimeException $e) {
            $error = $e->getMessage();
        }

        expect($error ?? null)->toBe('database is locked')
            ->and($connection->statements)->toBe([
                'PRAGMA foreign_keys = OFF',
                'DELETE FROM "shows"',
                'PRAGMA foreign_keys = ON',
            ]);
    });

    it('runs no SQL when there are no entity tables', function (): void {
        ['truncate' => $truncate, 'connection' => $connection, 'introspector' => $introspector] = makeTruncateDatabase();
        $introspector->tables = ['migrations'];

        withAppEnv('testing', fn () => $truncate->truncate());

        expect($connection->statements)->toBeEmpty();
    });

    it('refuses to truncate inside an open transaction', function (): void {
        ['truncate' => $truncate, 'connection' => $connection] = makeTruncateDatabase();
        $connection->beginTransaction();

        withAppEnv('testing', fn () => $truncate->truncate());
    })->throws(DatabaseTestException::class, 'Cannot truncate while a transaction is open (transaction level 1)');

    it('refuses to truncate in staging before running any SQL', function (): void {
        ['truncate' => $truncate, 'connection' => $connection] = makeTruncateDatabase();

        expect(fn () => withAppEnv('staging', fn () => $truncate->truncate()))
            ->toThrow(
                DatabaseTestException::class,
                "Refusing to truncate the entity tables in the 'staging' environment",
            )
            ->and($connection->statements)->toBeEmpty();
    });

    it('throws for an unsupported driver', function (): void {
        ['truncate' => $truncate, 'connection' => $connection] = makeTruncateDatabase();
        $connection->driver = 'oracle';

        withAppEnv('testing', fn () => $truncate->truncate());
    })->throws(DatabaseTestException::class, "does not support the 'oracle' driver");
});
