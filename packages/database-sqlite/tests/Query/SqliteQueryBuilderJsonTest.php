<?php

declare(strict_types=1);

namespace Marko\Database\Sqlite\Tests\Query;

use Marko\Database\Config\DatabaseConfig;
use Marko\Database\Sqlite\Connection\SqliteConnection;
use Marko\Database\Sqlite\Exceptions\UnsupportedFeatureException;
use Marko\Database\Sqlite\Query\SqliteQueryBuilder;
use Marko\Database\Sqlite\Query\SqliteQueryBuilderFactory;

function sqliteJsonConnection(): SqliteConnection
{
    $connection = new SqliteConnection(DatabaseConfig::fromArray(['driver' => 'sqlite', 'database' => ':memory:']));
    $connection->execute('CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT, tags TEXT, data TEXT)');
    $rows = [
        [
            1,
            'Alice',
            '["premium","beta"]',
            '{"user":{"name":"Alice"},"roles":["admin","editor"],"middle_name":"M","active":true}',
        ],
        [2, 'Bob', '["beta"]', '{"user":{"name":"Bob"},"roles":["editor"],"middle_name":null}'],
        [3, 'Cara', '[]', '{"user":{"name":"Cara"},"roles":"admin"}'],
    ];

    $connection->execute('CREATE TABLE bans (user_id INTEGER)');
    $connection->execute('INSERT INTO bans (user_id) VALUES (2)');

    foreach ($rows as $row) {
        $connection->execute('INSERT INTO users (id, name, tags, data) VALUES (?, ?, ?, ?)', $row);
    }

    return $connection;
}

/**
 * @return list<int>
 */
function sqliteIds(
    SqliteQueryBuilder $builder,
): array {
    return array_map(static fn (array $row): int => $row['id'], $builder->orderBy('id')->get());
}

describe('SqliteQueryBuilder JSON', function (): void {
    it('filters rows by a json path value with json_extract', function (): void {
        $connection = sqliteJsonConnection();
        $recording = new SqliteRecordingConnection();

        new SqliteQueryBuilder($recording)->table('users')->select('data->>user->>name AS n')
            ->where('data->>user->>name', '=', 'Bob')->get();

        expect($recording->lastQuerySql)->toContain('"data"->\'user\'->>\'name\' = ?')
            ->and(
                sqliteIds(
                    new SqliteQueryBuilder($connection)->table('users')->where('data->>user->>name', '=', 'Bob'),
                ),
            )
            ->toBe([2])
            ->and(
                new SqliteQueryBuilder($connection)->table('users')->select('data->>user->>name AS n')
                    ->orderBy('id')->first(),
            )
            ->toBe(['n' => 'Alice'])
            ->and(sqliteIds(new SqliteQueryBuilder($connection)->table('users')->where('data->>active', '=', true)))
            ->toBe([1]);
    });

    it(
        'filters rows whose json array contains a scalar or every scalar of a list and throws for an object needle',
        function (): void {
            $connection = sqliteJsonConnection();
            $builder = static fn (): SqliteQueryBuilder => new SqliteQueryBuilder($connection)->table('users');

            expect(sqliteIds($builder()->whereJsonContains('tags', 'premium')))->toBe([1])
                ->and(sqliteIds($builder()->whereJsonContains('tags', 'beta')))->toBe([1, 2])
                ->and(sqliteIds($builder()->whereJsonContains('tags', ['premium', 'beta'])))->toBe([1])
                ->and(sqliteIds($builder()->whereJsonContains('data->roles', 'admin')))->toBe([1, 3])
                ->and(sqliteIds($builder()->whereJsonContains('data->active', true)))->toBe([1])
                ->and(sqliteIds($builder()->whereJsonContains('tags', 'missing')))->toBeEmpty()
                ->and(fn () => $builder()->whereJsonContains('data', ['user' => ['name' => 'Bob']])->get())
                ->toThrow(UnsupportedFeatureException::class)
                ->and(fn () => $builder()->whereJsonContains('tags', [['nested']])->get())
                ->toThrow(UnsupportedFeatureException::class);

            try {
                $builder()->whereJsonContains('data', ['a' => 1])->get();
            } catch (UnsupportedFeatureException $exception) {
                expect($exception->getMessage())->toContain('whereJsonContains')->toContain('SQLite')
                    ->and($exception->getSuggestion())->toContain('whereRaw');
            }
        },
    );

    it('filters rows where a json path exists and where it is missing', function (): void {
        $connection = sqliteJsonConnection();
        $builder = static fn (): SqliteQueryBuilder => new SqliteQueryBuilder($connection)->table('users');

        expect(sqliteIds($builder()->whereJsonExists('data->middle_name')))->toBe([1, 2])
            ->and(sqliteIds($builder()->whereJsonMissing('data->middle_name')))->toBe([3])
            ->and(sqliteIds($builder()->whereJsonExists('data->user->name')))->toBe([1, 2, 3]);
    });

    it(
        'returns the same rows as the other drivers for unions subqueries and aggregates on shared fixture data',
        function (): void {
            $connection = sqliteJsonConnection();
            $factory = new SqliteQueryBuilderFactory($connection);
            $builder = static fn (): SqliteQueryBuilder => new SqliteQueryBuilder($connection)->table('users');

            $union = $builder()->select('name')->where('id', '=', 1)
                ->union($builder()->select('name')->where('id', '=', 3))->orderBy('name')->get();
            $exists = $builder()->whereExists(
                fn ($sub) => $sub->table('bans')->whereColumn('bans.user_id', '=', 'users.id'),
            )->get();

            expect($union)->toBe([['name' => 'Alice'], ['name' => 'Cara']])
                ->and($exists)->toHaveCount(1)
                ->and($exists[0]['name'])->toBe('Bob')
                ->and($builder()->count())->toBe(3)
                ->and($builder()->sum('id'))->toBe(6)
                ->and($builder()->max('id'))->toBe(3)
                ->and($builder()->min('id'))->toBe(1)
                ->and($factory->create()->table('users')->whereIn('id', [1, 2])->count())->toBe(2);
        },
    );

    it('paginates with limit and offset', function (): void {
        $connection = sqliteJsonConnection();
        $builder = static fn (): SqliteQueryBuilder => new SqliteQueryBuilder($connection)->table('users');

        expect(sqliteIds($builder()->limit(2)->offset(1)))->toBe([2, 3])
            ->and(sqliteIds($builder()->limit(2)))->toBe([1, 2])
            ->and(sqliteIds($builder()->offset(2)))->toBe([3]);
    });

    it('makes a SqliteQueryBuilder bound to the connection from the factory', function (): void {
        $connection = sqliteJsonConnection();
        $builder = new SqliteQueryBuilderFactory($connection)->create();

        expect($builder)->toBeInstanceOf(SqliteQueryBuilder::class)
            ->and($builder->table('users')->count())->toBe(3);
    });
});
