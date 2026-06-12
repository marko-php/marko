<?php

declare(strict_types=1);

namespace Marko\Database\PgSql\Tests\Query;

use Marko\Database\PgSql\Query\PgSqlQueryBuilder;

describe('PgSqlQueryBuilder whereIn empty array', function (): void {
    beforeEach(function (): void {
        $this->connection = new MockConnection();
        $this->builder = new PgSqlQueryBuilder($this->connection);
    });

    it('compiles whereIn with an empty array to a no-match condition', function (): void {
        $this->builder->table('users')->whereIn('id', [])->get();

        expect($this->connection->lastQuerySql)->toContain('1 = 0')
            ->and($this->connection->lastQuerySql)->not->toContain('IN ()');
    });

    it('binds no parameters for an empty whereIn', function (): void {
        $this->builder->table('users')->whereIn('id', [])->get();

        expect($this->connection->lastQueryBindings)->toBeEmpty();
    });

    it('still compiles whereIn with a non-empty array to an IN clause', function (): void {
        $this->builder->table('users')->whereIn('id', [1, 2, 3])->get();

        expect($this->connection->lastQuerySql)->toContain('IN (?, ?, ?)')
            ->and($this->connection->lastQueryBindings)->toBe([1, 2, 3]);
    });

    it('composes an empty whereIn with other where clauses using AND', function (): void {
        $this->builder->table('users')->where('status', '=', 'active')->whereIn('id', [])->get();

        expect($this->connection->lastQuerySql)->toContain('AND 1 = 0')
            ->and($this->connection->lastQuerySql)->toContain('WHERE');
    });
});
