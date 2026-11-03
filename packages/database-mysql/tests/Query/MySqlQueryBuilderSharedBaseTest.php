<?php

declare(strict_types=1);

namespace Marko\Database\MySql\Tests\Query;

use Marko\Database\MySql\Connection\MySqlServer;
use Marko\Database\MySql\Query\MySqlQueryBuilder;
use Marko\Database\Query\SqlQueryBuilder;

describe('MySqlQueryBuilder on the shared base', function (): void {
    it('extends the shared SqlQueryBuilder base', function (): void {
        expect(new MySqlQueryBuilder(new RecordingTransactionalConnection()))->toBeInstanceOf(SqlQueryBuilder::class);
    });

    it('compiles mysql backtick quoting through the identifier hook', function (): void {
        $connection = new RecordingTransactionalConnection();

        (new MySqlQueryBuilder($connection))->table('users')->select('name')->get();

        expect($connection->lastQuerySql)->toBe('SELECT `name` FROM `users`');
    });

    it('compiles mysql json path expressions through the json hook', function (): void {
        $connection = new RecordingTransactionalConnection();

        (new MySqlQueryBuilder($connection))->table('users')->where('data->>name', '=', 'a')->get();

        expect($connection->lastQuerySql)
            ->toBe("SELECT * FROM `users` WHERE JSON_UNQUOTE(JSON_EXTRACT(`data`, '\$.name')) = ?");
    });

    it('compiles ON DUPLICATE KEY UPDATE through the upsert hook', function (): void {
        $connection = new RecordingTransactionalConnection();

        (new MySqlQueryBuilder($connection))->table('users')->upsert([['id' => 1, 'name' => 'a']], ['id'], ['name']);

        expect($connection->lastExecuteSql)->toContain('ON DUPLICATE KEY UPDATE `name` = VALUES(`name`)');
    });

    it('compiles mariadb-specific lock modifiers through the lock hook', function (): void {
        $maria = new RecordingTransactionalConnection(serverVersion: '11.8.7-MariaDB');
        $mysql = new RecordingTransactionalConnection();

        (new MySqlQueryBuilder($maria, new MySqlServer($maria)))->table('jobs')->sharedLock()->skipLocked()->get();
        (new MySqlQueryBuilder($mysql, new MySqlServer($mysql)))->table('jobs')->sharedLock()->skipLocked()->get();

        expect($maria->lastQuerySql)->toBe('SELECT * FROM `jobs` LOCK IN SHARE MODE SKIP LOCKED')
            ->and($mysql->lastQuerySql)->toBe('SELECT * FROM `jobs` FOR SHARE SKIP LOCKED');
    });
});
