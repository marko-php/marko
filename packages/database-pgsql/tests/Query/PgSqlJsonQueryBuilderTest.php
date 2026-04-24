<?php

declare(strict_types=1);

namespace Marko\Database\PgSql\Tests\Query;

use Marko\Database\PgSql\Query\PgSqlQueryBuilder;

describe('PgSqlQueryBuilder JSON operators', function (): void {
    it('emits correct PostgreSQL SQL for every JSON operator (-> / ->> / @> / jsonb_path_exists)', function (): void {
        $connection = new MockConnection();
        $builder = new PgSqlQueryBuilder($connection);

        // -> in WHERE (JSON-typed)
        $builder->table('users')->where('data->name', '=', 'Bob')->get();
        expect($connection->lastQuerySql)
            ->toContain('"data"->\'name\'');

        // ->> in WHERE (text result)
        $builder2 = new PgSqlQueryBuilder($connection);
        $builder2->table('users')->where('data->>name', '=', 'Bob')->get();
        expect($connection->lastQuerySql)
            ->toContain('"data"->>\'name\'');

        // @> for JSON containment on plain column
        $builder3 = new PgSqlQueryBuilder($connection);
        $builder3->table('users')->whereJsonContains('tags', 'premium')->get();
        expect($connection->lastQuerySql)
            ->toContain('"tags" @> ?');

        // @> for JSON containment via path
        $builder4 = new PgSqlQueryBuilder($connection);
        $builder4->table('users')->whereJsonContains('data->roles', 'admin')->get();
        expect($connection->lastQuerySql)
            ->toContain('"data"->\'roles\' @> ?');

        // jsonb_path_exists for existence
        $builder5 = new PgSqlQueryBuilder($connection);
        $builder5->table('users')->whereJsonExists('data->middle_name')->get();
        expect($connection->lastQuerySql)
            ->toContain("jsonb_path_exists(\"data\", '$.middle_name')");

        // NOT jsonb_path_exists for missing
        $builder6 = new PgSqlQueryBuilder($connection);
        $builder6->table('users')->whereJsonMissing('data->middle_name')->get();
        expect($connection->lastQuerySql)
            ->toContain("NOT jsonb_path_exists(\"data\", '$.middle_name')");
    });

    it('filters rows by a nested JSON path value via where()', function (): void {
        $connection = new MockConnection(
            queryReturn: [['id' => 1, 'name' => 'Bob']],
        );
        $builder = new PgSqlQueryBuilder($connection);

        $result = $builder
            ->table('users')
            ->where('data->user->name', '=', 'Bob')
            ->get();

        expect($connection->lastQuerySql)
            ->toContain('"data"->\'user\'->\'name\'')
            ->and($connection->lastQueryBindings)->toBe(['Bob'])
            ->and($result)->toBe([['id' => 1, 'name' => 'Bob']]);
    });

    it('selects a nested JSON path as an aliased column', function (): void {
        $connection = new MockConnection(
            queryReturn: [['user_name' => 'Bob']],
        );
        $builder = new PgSqlQueryBuilder($connection);

        $result = $builder
            ->table('users')
            ->select('data->name as user_name')
            ->get();

        expect($connection->lastQuerySql)
            ->toContain('"data"->\'name\'')
            ->toContain('AS "user_name"')
            ->and($result)->toBe([['user_name' => 'Bob']]);
    });

    it('returns rows whose JSON array contains a value via whereJsonContains()', function (): void {
        $connection = new MockConnection(
            queryReturn: [['id' => 1]],
        );
        $builder = new PgSqlQueryBuilder($connection);

        $result = $builder
            ->table('users')
            ->whereJsonContains('tags', 'premium')
            ->get();

        expect($connection->lastQuerySql)
            ->toContain('"tags" @> ?')
            ->and($result)->toHaveCount(1);
    });

    it('returns rows whose JSON object contains a nested value via whereJsonContains() with a path', function (): void {
        $connection = new MockConnection(
            queryReturn: [['id' => 2]],
        );
        $builder = new PgSqlQueryBuilder($connection);

        $result = $builder
            ->table('users')
            ->whereJsonContains('data->roles', 'admin')
            ->get();

        expect($connection->lastQuerySql)
            ->toContain('"data"->\'roles\' @> ?')
            ->and($result)->toHaveCount(1);
    });

    it('returns rows where a JSON path exists via whereJsonExists()', function (): void {
        $connection = new MockConnection(
            queryReturn: [['id' => 1]],
        );
        $builder = new PgSqlQueryBuilder($connection);

        $result = $builder
            ->table('users')
            ->whereJsonExists('data->middle_name')
            ->get();

        expect($connection->lastQuerySql)
            ->toContain("jsonb_path_exists(\"data\", '$.middle_name')")
            ->and($result)->toHaveCount(1);
    });

    it('returns rows where a JSON path does NOT exist via whereJsonMissing()', function (): void {
        $connection = new MockConnection(
            queryReturn: [['id' => 2]],
        );
        $builder = new PgSqlQueryBuilder($connection);

        $result = $builder
            ->table('users')
            ->whereJsonMissing('data->middle_name')
            ->get();

        expect($connection->lastQuerySql)
            ->toContain("NOT jsonb_path_exists(\"data\", '$.middle_name')")
            ->and($result)->toHaveCount(1);
    });

    it('parameterizes JSON query values safely (no injection via the value side)', function (): void {
        $connection = new MockConnection();
        $builder = new PgSqlQueryBuilder($connection);

        $maliciousValue = "'; DROP TABLE users; --";

        $builder
            ->table('users')
            ->where('data->name', '=', $maliciousValue)
            ->get();

        expect($connection->lastQuerySql)->not->toContain($maliciousValue)
            ->and($connection->lastQueryBindings)->toBe([$maliciousValue]);
    });

    it('composes JSON path operators with WHERE, GROUP BY, HAVING, ORDER BY, and LIMIT correctly', function (): void {
        $connection = new MockConnection();
        $builder = new PgSqlQueryBuilder($connection);

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
            ->toContain('"data"->\'name\'')
            ->toContain('"data"->\'age\'')
            ->toContain("jsonb_path_exists(\"data\", '$.email')")
            ->toContain('ORDER BY "id" DESC')
            ->toContain('LIMIT 10');
    });
});
