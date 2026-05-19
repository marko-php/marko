<?php

declare(strict_types=1);

use Marko\Database\Connection\ConnectionInterface;
use Marko\Database\Connection\StatementInterface;
use Marko\Database\Exceptions\InvalidColumnException;
use Marko\Database\MySql\Query\MySqlQueryBuilder;
use Marko\Database\PgSql\Query\PgSqlQueryBuilder;
use Marko\Database\Query\QueryBuilderInterface;

function makeRecordingConnection(): ConnectionInterface
{
    return new class () implements ConnectionInterface
    {
        public ?string $lastSql = null;

        /** @var array<int, mixed> */
        public array $lastBindings = [];

        public function query(string $sql, array $bindings = []): array
        {
            $this->lastSql = $sql;
            $this->lastBindings = $bindings;

            return [];
        }

        public function execute(string $sql, array $bindings = []): int
        {
            $this->lastSql = $sql;
            $this->lastBindings = $bindings;

            return 0;
        }

        public function connect(): void {}

        public function disconnect(): void {}

        public function isConnected(): bool
        {
            return true;
        }

        public function prepare(string $sql): StatementInterface
        {
            return new class () implements StatementInterface
            {
                public function execute(array $bindings = []): bool
                {
                    return true;
                }

                public function fetchAll(): array
                {
                    return [];
                }

                public function fetch(): ?array
                {
                    return null;
                }

                public function rowCount(): int
                {
                    return 0;
                }
            };
        }

        public function lastInsertId(): int
        {
            return 0;
        }
    };
}

function applyFixture(QueryBuilderInterface $builder): void
{
    $builder
        ->table('products')
        ->select('id')
        ->selectRaw('COALESCE(?, ?) AS resolved', ['a', 'b'])
        ->where('active', '=', true)
        ->whereRaw('price > ?', [100]);
}

it('MySqlQueryBuilder and PgSqlQueryBuilder produce identical bindings array for the selectRaw + where + whereRaw fixture', function (): void {
    $mysqlConnection = makeRecordingConnection();
    $pgsqlConnection = makeRecordingConnection();

    $mysql = new MySqlQueryBuilder($mysqlConnection);
    $pgsql = new PgSqlQueryBuilder($pgsqlConnection);

    applyFixture($mysql);
    applyFixture($pgsql);

    $mysql->get();
    $pgsql->get();

    expect($mysqlConnection->lastBindings)
        ->toBe(['a', 'b', true, 100])
        ->and($pgsqlConnection->lastBindings)
        ->toBe(['a', 'b', true, 100]);
});

it('the bindings array order is exactly [select-raw-bindings..., where-bindings..., where-raw-bindings...] in both drivers', function (): void {
    $mysqlConnection = makeRecordingConnection();
    $pgsqlConnection = makeRecordingConnection();

    $mysql = new MySqlQueryBuilder($mysqlConnection);
    $pgsql = new PgSqlQueryBuilder($pgsqlConnection);

    applyFixture($mysql);
    applyFixture($pgsql);

    $mysql->get();
    $pgsql->get();

    $expected = ['a', 'b', true, 100];

    expect($mysqlConnection->lastBindings[0])->toBe('a')
        ->and($mysqlConnection->lastBindings[1])->toBe('b')
        ->and($mysqlConnection->lastBindings[2])->toBeTrue()
        ->and($mysqlConnection->lastBindings[3])->toBe(100)
        ->and($pgsqlConnection->lastBindings[0])->toBe('a')
        ->and($pgsqlConnection->lastBindings[1])->toBe('b')
        ->and($pgsqlConnection->lastBindings[2])->toBeTrue()
        ->and($pgsqlConnection->lastBindings[3])->toBe(100)
        ->and($mysqlConnection->lastBindings)->toBe($expected)
        ->and($pgsqlConnection->lastBindings)->toBe($expected);
});

it('both drivers include the raw select expression in the SELECT list, after the regular columns', function (): void {
    $mysqlConnection = makeRecordingConnection();
    $pgsqlConnection = makeRecordingConnection();

    $mysql = new MySqlQueryBuilder($mysqlConnection);
    $pgsql = new PgSqlQueryBuilder($pgsqlConnection);

    applyFixture($mysql);
    applyFixture($pgsql);

    $mysql->get();
    $pgsql->get();

    $mysqlSql = $mysqlConnection->lastSql ?? '';
    $pgsqlSql = $pgsqlConnection->lastSql ?? '';

    expect(str_contains($mysqlSql, 'COALESCE(?, ?) AS resolved'))->toBeTrue()
        ->and(str_contains($pgsqlSql, 'COALESCE(?, ?) AS resolved'))->toBeTrue();

    $mysqlIdPos = strpos($mysqlSql, 'id');
    $mysqlRawPos = strpos($mysqlSql, 'COALESCE');
    $pgsqlIdPos = strpos($pgsqlSql, 'id');
    $pgsqlRawPos = strpos($pgsqlSql, 'COALESCE');

    expect($mysqlIdPos)->toBeLessThan($mysqlRawPos)
        ->and($pgsqlIdPos)->toBeLessThan($pgsqlRawPos);
});

it('both drivers AND-combine the raw where expression with the regular where condition', function (): void {
    $mysqlConnection = makeRecordingConnection();
    $pgsqlConnection = makeRecordingConnection();

    $mysql = new MySqlQueryBuilder($mysqlConnection);
    $pgsql = new PgSqlQueryBuilder($pgsqlConnection);

    applyFixture($mysql);
    applyFixture($pgsql);

    $mysql->get();
    $pgsql->get();

    $mysqlSql = $mysqlConnection->lastSql ?? '';
    $pgsqlSql = $pgsqlConnection->lastSql ?? '';

    expect(str_contains($mysqlSql, 'AND price > ?'))->toBeTrue()
        ->and(str_contains($pgsqlSql, 'AND price > ?'))->toBeTrue();
});

it('both drivers throw InvalidColumnException for each denylist input (semicolon, --, /*, */, backtick) — parameterized via Pest\'s it(...)->with([...])', function (string $dangerous): void {
    $mysqlConnection = makeRecordingConnection();
    $pgsqlConnection = makeRecordingConnection();

    $mysql = new MySqlQueryBuilder($mysqlConnection);
    $pgsql = new PgSqlQueryBuilder($pgsqlConnection);

    expect(fn () => $mysql->selectRaw($dangerous))->toThrow(InvalidColumnException::class)
        ->and(fn () => $pgsql->selectRaw($dangerous))->toThrow(InvalidColumnException::class);

    $mysql2 = new MySqlQueryBuilder($mysqlConnection);
    $pgsql2 = new PgSqlQueryBuilder($pgsqlConnection);

    expect(fn () => $mysql2->whereRaw($dangerous))->toThrow(InvalidColumnException::class)
        ->and(fn () => $pgsql2->whereRaw($dangerous))->toThrow(InvalidColumnException::class);
})->with([';', '--', '/*', '*/', '`']);

it('the emitted SQL contains the same SELECT-list ordering across both drivers (raw expression appended after regular columns)', function (): void {
    $mysqlConnection = makeRecordingConnection();
    $pgsqlConnection = makeRecordingConnection();

    $mysql = new MySqlQueryBuilder($mysqlConnection);
    $pgsql = new PgSqlQueryBuilder($pgsqlConnection);

    applyFixture($mysql);
    applyFixture($pgsql);

    $mysql->get();
    $pgsql->get();

    $mysqlSql = $mysqlConnection->lastSql ?? '';
    $pgsqlSql = $pgsqlConnection->lastSql ?? '';

    $mysqlNormalized = str_replace(['`', '"'], '', $mysqlSql);
    $pgsqlNormalized = str_replace(['`', '"'], '', $pgsqlSql);

    $mysqlSelectList = (string) preg_replace('/SELECT\s+(.*?)\s+FROM.*/s', '$1', $mysqlNormalized);
    $pgsqlSelectList = (string) preg_replace('/SELECT\s+(.*?)\s+FROM.*/s', '$1', $pgsqlNormalized);

    expect($mysqlSelectList)->toBe($pgsqlSelectList);
});

it('the emitted SQL contains the same WHERE-clause ordering across both drivers (regular where first, then whereRaw, AND-combined)', function (): void {
    $mysqlConnection = makeRecordingConnection();
    $pgsqlConnection = makeRecordingConnection();

    $mysql = new MySqlQueryBuilder($mysqlConnection);
    $pgsql = new PgSqlQueryBuilder($pgsqlConnection);

    applyFixture($mysql);
    applyFixture($pgsql);

    $mysql->get();
    $pgsql->get();

    $mysqlSql = $mysqlConnection->lastSql ?? '';
    $pgsqlSql = $pgsqlConnection->lastSql ?? '';

    $mysqlNormalized = str_replace(['`', '"'], '', $mysqlSql);
    $pgsqlNormalized = str_replace(['`', '"'], '', $pgsqlSql);

    $mysqlWherePos = strpos($mysqlNormalized, 'WHERE');
    $pgsqlWherePos = strpos($pgsqlNormalized, 'WHERE');

    $mysqlWhere = $mysqlWherePos !== false ? substr($mysqlNormalized, $mysqlWherePos) : '';
    $pgsqlWhere = $pgsqlWherePos !== false ? substr($pgsqlNormalized, $pgsqlWherePos) : '';

    expect($mysqlWhere)->toBe($pgsqlWhere);
});
