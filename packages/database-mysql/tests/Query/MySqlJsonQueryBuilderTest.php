<?php

declare(strict_types=1);

namespace Marko\Database\MySql\Tests\Query;

use Marko\Database\Connection\ConnectionInterface;
use Marko\Database\Connection\StatementInterface;
use Marko\Database\MySql\Query\MySqlQueryBuilder;
use RuntimeException;

/**
 * Mock connection that records SQL/bindings for JSON operator assertion.
 */
class MySqlMockConnection implements ConnectionInterface
{
    public string $lastQuerySql = '';

    /** @var array */
    public array $lastQueryBindings = [];

    /**
     * @param array<array<string, mixed>> $queryReturn
     */
    public function __construct(
        private readonly array $queryReturn = [],
    ) {}

    public function connect(): void {}

    public function disconnect(): void {}

    public function isConnected(): bool
    {
        return true;
    }

    public function query(string $sql, array $bindings = []): array
    {
        $this->lastQuerySql = $sql;
        $this->lastQueryBindings = $bindings;

        return $this->queryReturn;
    }

    public function execute(string $sql, array $bindings = []): int
    {
        return 0;
    }

    public function prepare(string $sql): StatementInterface
    {
        throw new RuntimeException('Not implemented');
    }

    public function lastInsertId(): int
    {
        return 0;
    }
}

describe('MySqlQueryBuilder JSON operators', function (): void {
    it('filters rows by a nested JSON path value via where()', function (): void {
        $connection = new MySqlMockConnection(
            queryReturn: [['id' => 1, 'name' => 'Bob']],
        );
        $builder = new MySqlQueryBuilder($connection);

        $result = $builder
            ->table('users')
            ->where('data->name', '=', 'Bob')
            ->get();

        expect($connection->lastQuerySql)
            ->toContain('JSON_EXTRACT(`data`, \'$.name\')')
            ->and($connection->lastQueryBindings)->toBe(['Bob'])
            ->and($result)->toBe([['id' => 1, 'name' => 'Bob']]);
    });

    it('selects a nested JSON path as an aliased column', function (): void {
        $connection = new MySqlMockConnection(
            queryReturn: [['user_name' => 'Bob']],
        );
        $builder = new MySqlQueryBuilder($connection);

        $result = $builder
            ->table('users')
            ->select('data->name as user_name')
            ->get();

        expect($connection->lastQuerySql)
            ->toContain('JSON_EXTRACT(`data`, \'$.name\')')
            ->toContain('AS `user_name`')
            ->and($result)->toBe([['user_name' => 'Bob']]);
    });

    it('returns rows whose JSON array contains a value via whereJsonContains()', function (): void {
        $connection = new MySqlMockConnection(
            queryReturn: [['id' => 1, 'tags' => '["premium","vip"]']],
        );
        $builder = new MySqlQueryBuilder($connection);

        $result = $builder
            ->table('users')
            ->whereJsonContains('tags', 'premium')
            ->get();

        expect($connection->lastQuerySql)
            ->toContain('JSON_CONTAINS(`tags`, ?)')
            ->and($connection->lastQueryBindings[0])->toBe('"premium"')
            ->and($result)->toHaveCount(1);
    });

    it('returns rows whose JSON object contains a nested value via whereJsonContains() with a path', function (): void {
        $connection = new MySqlMockConnection(
            queryReturn: [['id' => 2]],
        );
        $builder = new MySqlQueryBuilder($connection);

        $result = $builder
            ->table('users')
            ->whereJsonContains('data->roles', 'admin')
            ->get();

        expect($connection->lastQuerySql)
            ->toContain("JSON_CONTAINS(JSON_EXTRACT(`data`, '$.roles'), ?)")
            ->and($connection->lastQueryBindings[0])->toBe('"admin"')
            ->and($result)->toHaveCount(1);
    });

    it('returns rows where a JSON path exists via whereJsonExists()', function (): void {
        $connection = new MySqlMockConnection(
            queryReturn: [['id' => 1]],
        );
        $builder = new MySqlQueryBuilder($connection);

        $result = $builder
            ->table('users')
            ->whereJsonExists('data->middle_name')
            ->get();

        expect($connection->lastQuerySql)
            ->toContain("JSON_CONTAINS_PATH(`data`, 'one', '$.middle_name')")
            ->and($result)->toHaveCount(1);
    });

    it('returns rows where a JSON path does NOT exist via whereJsonMissing()', function (): void {
        $connection = new MySqlMockConnection(
            queryReturn: [['id' => 2]],
        );
        $builder = new MySqlQueryBuilder($connection);

        $result = $builder
            ->table('users')
            ->whereJsonMissing('data->middle_name')
            ->get();

        expect($connection->lastQuerySql)
            ->toContain("NOT JSON_CONTAINS_PATH(`data`, 'one', '$.middle_name')")
            ->and($result)->toHaveCount(1);
    });

    it('parameterizes JSON query values safely (no injection via the value side)', function (): void {
        $connection = new MySqlMockConnection();
        $builder = new MySqlQueryBuilder($connection);

        $maliciousValue = "'; DROP TABLE users; --";

        $builder
            ->table('users')
            ->where('data->name', '=', $maliciousValue)
            ->get();

        // The value must be a binding, not inlined in SQL
        expect($connection->lastQuerySql)->not->toContain($maliciousValue)
            ->and($connection->lastQueryBindings)->toBe([$maliciousValue]);
    });

    it('emits correct MySQL SQL for every JSON operator (JSON_EXTRACT / JSON_UNQUOTE / JSON_CONTAINS / JSON_CONTAINS_PATH)', function (): void {
        $connection = new MySqlMockConnection();
        $builder = new MySqlQueryBuilder($connection);

        // JSON_EXTRACT via -> in WHERE
        $builder->table('users')->where('data->user->name', '=', 'Bob')->get();
        expect($connection->lastQuerySql)
            ->toContain("JSON_EXTRACT(`data`, '$.user.name')");

        // JSON_UNQUOTE via ->> in WHERE
        $builder2 = new MySqlQueryBuilder($connection);
        $builder2->table('users')->where('data->>user', '=', 'Bob')->get();
        expect($connection->lastQuerySql)
            ->toContain("JSON_UNQUOTE(JSON_EXTRACT(`data`, '$.user'))");

        // JSON_CONTAINS on plain column
        $builder3 = new MySqlQueryBuilder($connection);
        $builder3->table('users')->whereJsonContains('tags', 'premium')->get();
        expect($connection->lastQuerySql)
            ->toContain('JSON_CONTAINS(`tags`, ?)');

        // JSON_CONTAINS_PATH existence
        $builder4 = new MySqlQueryBuilder($connection);
        $builder4->table('users')->whereJsonExists('data->addr')->get();
        expect($connection->lastQuerySql)
            ->toContain("JSON_CONTAINS_PATH(`data`, 'one', '$.addr')");

        // NOT JSON_CONTAINS_PATH missing
        $builder5 = new MySqlQueryBuilder($connection);
        $builder5->table('users')->whereJsonMissing('data->addr')->get();
        expect($connection->lastQuerySql)
            ->toContain("NOT JSON_CONTAINS_PATH(`data`, 'one', '$.addr')");
    });

    it('composes JSON path operators with WHERE, GROUP BY, HAVING, ORDER BY, and LIMIT correctly', function (): void {
        $connection = new MySqlMockConnection();
        $builder = new MySqlQueryBuilder($connection);

        $builder
            ->table('users')
            ->select('data->name as user_name')
            ->where('data->age', '>', 18)
            ->whereJsonExists('data->email')
            ->orderBy('id', 'DESC')
            ->limit(10)
            ->get();

        $sql = $connection->lastQuerySql;

        expect($sql)
            ->toContain("JSON_EXTRACT(`data`, '$.name')")
            ->toContain("JSON_EXTRACT(`data`, '$.age')")
            ->toContain("JSON_CONTAINS_PATH(`data`, 'one', '$.email')")
            ->toContain('ORDER BY `id` DESC')
            ->toContain('LIMIT 10');
    });
});
