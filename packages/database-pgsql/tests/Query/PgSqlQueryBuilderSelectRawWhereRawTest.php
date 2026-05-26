<?php

declare(strict_types=1);

namespace Marko\Database\PgSql\Tests\Query;

use Marko\Database\Exceptions\InvalidColumnException;
use Marko\Database\PgSql\Query\PgSqlQueryBuilder;

describe('PgSqlQueryBuilder selectRaw', function (): void {
    it('selectRaw appends the expression to the SELECT list after regular columns', function (): void {
        $connection = new MockConnection();
        $builder = new PgSqlQueryBuilder($connection);

        $builder
            ->table('users')
            ->select('name')
            ->selectRaw("'static' AS extra")
            ->get();

        expect($connection->lastQuerySql)->toBe("SELECT \"name\", 'static' AS extra FROM \"users\"");
    });

    it(
        'selectRaw alone (no prior select call) emits "SELECT *, <expression>" preserving the default *',
        function (): void {
            $connection = new MockConnection();
            $builder = new PgSqlQueryBuilder($connection);
    
            $builder
                ->table('users')
                ->selectRaw("'hello' AS greeting")
                ->get();
    
            expect($connection->lastQuerySql)->toBe("SELECT *, 'hello' AS greeting FROM \"users\"");
        }
    );

    it(
        'selectRaw can be called multiple times; expressions appear in call order in the SELECT list',
        function (): void {
            $connection = new MockConnection();
            $builder = new PgSqlQueryBuilder($connection);
    
            $builder
                ->table('users')
                ->selectRaw("'first' AS one")
                ->selectRaw("'second' AS two")
                ->get();
    
            expect($connection->lastQuerySql)->toBe("SELECT *, 'first' AS one, 'second' AS two FROM \"users\"");
        }
    );

    it('selectRaw together with select() emits select() columns first then selectRaw expressions', function (): void {
        $connection = new MockConnection();
        $builder = new PgSqlQueryBuilder($connection);

        $builder
            ->table('users')
            ->select('id', 'name')
            ->selectRaw("'extra' AS computed")
            ->get();

        expect($connection->lastQuerySql)->toBe("SELECT \"id\", \"name\", 'extra' AS computed FROM \"users\"");
    });

    it(
        'selectRaw with bindings places the bindings BEFORE WHERE bindings in the compiled bindings array',
        function (): void {
            $connection = new MockConnection();
            $builder = new PgSqlQueryBuilder($connection);
    
            $builder
                ->table('users')
                ->selectRaw('? AS val', [42])
                ->where('id', '=', 1)
                ->get();
    
            expect($connection->lastQueryBindings)->toBe([42, 1]);
        }
    );

    it('selectRaw bindings from multiple calls concatenate in call order', function (): void {
        $connection = new MockConnection();
        $builder = new PgSqlQueryBuilder($connection);

        $builder
            ->table('users')
            ->selectRaw('? AS first', [10])
            ->selectRaw('? AS second', [20])
            ->get();

        expect($connection->lastQueryBindings)->toBe([10, 20]);
    });

    it(
        'running ->get() twice on the same builder produces identical SQL and bindings (no mutation of internal raw state)',
        function (): void {
            $connection = new MockConnection();
            $builder = new PgSqlQueryBuilder($connection);
    
            $builder
                ->table('users')
                ->selectRaw('? AS val', [42])
                ->where('id', '=', 1);
    
            $builder->get();
            $firstSql = $connection->lastQuerySql;
            $firstBindings = $connection->lastQueryBindings;
    
            $builder->get();
            $secondSql = $connection->lastQuerySql;
            $secondBindings = $connection->lastQueryBindings;
    
            expect($firstSql)->toBe($secondSql)
                ->and($firstBindings)->toBe($secondBindings);
        }
    );

    it('selectRaw throws InvalidColumnException when the expression contains a semicolon', function (): void {
        $connection = new MockConnection();
        $builder = new PgSqlQueryBuilder($connection);

        expect(fn () => $builder->selectRaw('1; DROP TABLE users--'))
            ->toThrow(InvalidColumnException::class);
    });

    it('selectRaw throws InvalidColumnException when the expression contains a -- comment marker', function (): void {
        $connection = new MockConnection();
        $builder = new PgSqlQueryBuilder($connection);

        expect(fn () => $builder->selectRaw('1 -- comment'))
            ->toThrow(InvalidColumnException::class);
    });

    it(
        'selectRaw throws InvalidColumnException when the expression contains a /* or */ block-comment marker',
        function (): void {
            $connection = new MockConnection();
            $builder = new PgSqlQueryBuilder($connection);
    
            expect(fn () => $builder->selectRaw('/* comment */'))
                ->toThrow(InvalidColumnException::class);
        }
    );

    it('selectRaw throws InvalidColumnException when the expression contains a backtick', function (): void {
        $connection = new MockConnection();
        $builder = new PgSqlQueryBuilder($connection);

        expect(fn () => $builder->selectRaw('`name`'))
            ->toThrow(InvalidColumnException::class);
    });

    it('selectRaw returns the builder for fluent chaining', function (): void {
        $connection = new MockConnection();
        $builder = new PgSqlQueryBuilder($connection);

        $result = $builder->table('users')->selectRaw("'x' AS col");

        expect($result)->toBeInstanceOf(PgSqlQueryBuilder::class);
    });
});

describe('PgSqlQueryBuilder whereRaw', function (): void {
    it('whereRaw appends the expression to the WHERE clause AND-combined with other conditions', function (): void {
        $connection = new MockConnection();
        $builder = new PgSqlQueryBuilder($connection);

        $builder
            ->table('users')
            ->where('status', '=', 'active')
            ->whereRaw('LENGTH(name) > 3')
            ->get();

        expect($connection->lastQuerySql)->toBe('SELECT * FROM "users" WHERE "status" = ? AND LENGTH(name) > 3');
    });

    it('whereRaw used alone (no prior where) emits "WHERE <expression>" with no leading AND', function (): void {
        $connection = new MockConnection();
        $builder = new PgSqlQueryBuilder($connection);

        $builder
            ->table('users')
            ->whereRaw('LENGTH(name) > 3')
            ->get();

        expect($connection->lastQuerySql)->toBe('SELECT * FROM "users" WHERE LENGTH(name) > 3');
    });

    it('whereRaw can be called multiple times; expressions appear in call order, AND-combined', function (): void {
        $connection = new MockConnection();
        $builder = new PgSqlQueryBuilder($connection);

        $builder
            ->table('users')
            ->whereRaw('LENGTH(name) > 3')
            ->whereRaw('LENGTH(email) > 5')
            ->get();

        expect($connection->lastQuerySql)->toBe('SELECT * FROM "users" WHERE LENGTH(name) > 3 AND LENGTH(email) > 5');
    });

    it(
        'whereRaw together with where() emits the regular where condition first then the raw expression, AND-combined',
        function (): void {
            $connection = new MockConnection();
            $builder = new PgSqlQueryBuilder($connection);
    
            $builder
                ->table('users')
                ->where('id', '=', 1)
                ->whereRaw('LENGTH(name) > 3')
                ->get();
    
            expect($connection->lastQuerySql)->toBe('SELECT * FROM "users" WHERE "id" = ? AND LENGTH(name) > 3');
        }
    );

    it(
        'whereRaw with bindings places its bindings in the WHERE position of the bindings array (after selectRaw bindings, after regular where bindings if both exist)',
        function (): void {
            $connection = new MockConnection();
            $builder = new PgSqlQueryBuilder($connection);
    
            $builder
                ->table('users')
                ->where('status', '=', 'active')
                ->whereRaw('LENGTH(name) > ?', [3])
                ->get();
    
            expect($connection->lastQueryBindings)->toBe(['active', 3]);
        }
    );

    it('whereRaw bindings from multiple calls concatenate in call order', function (): void {
        $connection = new MockConnection();
        $builder = new PgSqlQueryBuilder($connection);

        $builder
            ->table('users')
            ->whereRaw('LENGTH(name) > ?', [3])
            ->whereRaw('LENGTH(email) > ?', [5])
            ->get();

        expect($connection->lastQueryBindings)->toBe([3, 5]);
    });

    it('whereRaw throws InvalidColumnException when the expression contains a semicolon', function (): void {
        $connection = new MockConnection();
        $builder = new PgSqlQueryBuilder($connection);

        expect(fn () => $builder->whereRaw('1; DROP TABLE users--'))
            ->toThrow(InvalidColumnException::class);
    });

    it('whereRaw throws InvalidColumnException when the expression contains a -- comment marker', function (): void {
        $connection = new MockConnection();
        $builder = new PgSqlQueryBuilder($connection);

        expect(fn () => $builder->whereRaw('1 -- comment'))
            ->toThrow(InvalidColumnException::class);
    });

    it(
        'whereRaw throws InvalidColumnException when the expression contains a /* or */ block-comment marker',
        function (): void {
            $connection = new MockConnection();
            $builder = new PgSqlQueryBuilder($connection);
    
            expect(fn () => $builder->whereRaw('/* comment */'))
                ->toThrow(InvalidColumnException::class);
        }
    );

    it('whereRaw throws InvalidColumnException when the expression contains a backtick', function (): void {
        $connection = new MockConnection();
        $builder = new PgSqlQueryBuilder($connection);

        expect(fn () => $builder->whereRaw('`name` = ?'))
            ->toThrow(InvalidColumnException::class);
    });

    it('whereRaw returns the builder for fluent chaining', function (): void {
        $connection = new MockConnection();
        $builder = new PgSqlQueryBuilder($connection);

        $result = $builder->table('users')->whereRaw('1 = 1');

        expect($result)->toBeInstanceOf(PgSqlQueryBuilder::class);
    });

    it('count() honors whereRaw conditions (filtered count, not full-table count)', function (): void {
        $connection = new MockConnection(queryReturn: [['aggregate' => 2]]);
        $builder = new PgSqlQueryBuilder($connection);

        $builder
            ->table('users')
            ->whereRaw('LENGTH(name) > ?', [3])
            ->count();

        expect($connection->lastQuerySql)->toBe('SELECT COUNT(*) as aggregate FROM "users" WHERE LENGTH(name) > ?')
            ->and($connection->lastQueryBindings)->toBe([3]);
    });

    it('min() / max() / sum() / avg() honor whereRaw conditions', function (): void {
        $connection = new MockConnection(queryReturn: [['aggregate' => 10]]);
        $builder = new PgSqlQueryBuilder($connection);

        $builder
            ->table('users')
            ->whereRaw('LENGTH(name) > ?', [3])
            ->min('id');

        expect($connection->lastQuerySql)->toBe('SELECT MIN("id") as aggregate FROM "users" WHERE LENGTH(name) > ?')
            ->and($connection->lastQueryBindings)->toBe([3]);
    });
});

describe('PgSqlQueryBuilder selectRaw + whereRaw combined', function (): void {
    it(
        'selectRaw and whereRaw used together produce bindings in [select-bindings..., where-bindings...] order in the final bindings array',
        function (): void {
            $connection = new MockConnection();
            $builder = new PgSqlQueryBuilder($connection);
    
            $builder
                ->table('users')
                ->selectRaw('? AS sel_val', [10])
                ->whereRaw('LENGTH(name) > ?', [3])
                ->get();
    
            expect($connection->lastQueryBindings)->toBe([10, 3]);
        }
    );

    it(
        'selectRaw and whereRaw flow correctly through compileSubquery() when this builder is used as a UNION right-hand side',
        function (): void {
            $leftConnection = new MockConnection();
            $rightConnection = new MockConnection();
    
            $left = new PgSqlQueryBuilder($leftConnection);
            $left->table('users')->select('name');
    
            $right = new PgSqlQueryBuilder($rightConnection);
            $right->table('admins')
                ->select('name')
                ->selectRaw('? AS extra', [99])
                ->whereRaw('LENGTH(name) > ?', [2]);
    
            $left->union($right)->get();
    
            expect($leftConnection->lastQuerySql)->toBe(
                '(SELECT "name" FROM "users") UNION (SELECT "name", ? AS extra FROM "admins" WHERE LENGTH(name) > ?)',
            )
                ->and($leftConnection->lastQueryBindings)->toBe([99, 2]);
        }
    );
});

describe('PgSqlQueryBuilder orderByRaw compilation', function (): void {
    it('compiles a raw ORDER BY expression with the given direction', function (): void {
        $connection = new MockConnection();
        $builder = new PgSqlQueryBuilder($connection);

        $builder
            ->table('users')
            ->select('name')
            ->orderByRaw('LENGTH(name)', 'DESC')
            ->get();

        expect($connection->lastQuerySql)->toBe('SELECT "name" FROM "users" ORDER BY LENGTH(name) DESC');
    });

    it('defaults direction to ASC when omitted', function (): void {
        $connection = new MockConnection();
        $builder = new PgSqlQueryBuilder($connection);

        $builder
            ->table('users')
            ->select('name')
            ->orderByRaw('LENGTH(name)')
            ->get();

        expect($connection->lastQuerySql)->toBe('SELECT "name" FROM "users" ORDER BY LENGTH(name) ASC');
    });

    it('preserves call order when mixing orderBy and orderByRaw', function (): void {
        $connection = new MockConnection();
        $builder = new PgSqlQueryBuilder($connection);

        $builder
            ->table('users')
            ->select('name')
            ->orderBy('status', 'ASC')
            ->orderByRaw('LENGTH(name)', 'DESC')
            ->get();

        expect($connection->lastQuerySql)->toBe('SELECT "name" FROM "users" ORDER BY "status" ASC, LENGTH(name) DESC');
    });

    it('returns the builder for fluent chaining', function (): void {
        $builder = new PgSqlQueryBuilder(new MockConnection());
        $result = $builder->orderByRaw('LENGTH(name)');
        expect($result)->toBe($builder);
    });
});

describe('PgSqlQueryBuilder orderByRaw denylist', function (): void {
    it('throws InvalidColumnException when the expression contains a semicolon', function (): void {
        $builder = new PgSqlQueryBuilder(new MockConnection());
        expect(fn () => $builder->orderByRaw('name; DROP TABLE users'))
            ->toThrow(InvalidColumnException::class);
    });

    it('throws InvalidColumnException when the expression contains a -- comment marker', function (): void {
        $builder = new PgSqlQueryBuilder(new MockConnection());
        expect(fn () => $builder->orderByRaw('name -- comment'))
            ->toThrow(InvalidColumnException::class);
    });

    it('throws InvalidColumnException when the expression contains a /* block-comment marker', function (): void {
        $builder = new PgSqlQueryBuilder(new MockConnection());
        expect(fn () => $builder->orderByRaw('name /* comment */'))
            ->toThrow(InvalidColumnException::class);
    });

    it('throws InvalidColumnException when the expression contains a backtick', function (): void {
        $builder = new PgSqlQueryBuilder(new MockConnection());
        expect(fn () => $builder->orderByRaw('`name`'))
            ->toThrow(InvalidColumnException::class);
    });
});

describe('PgSqlQueryBuilder having denylist (shared with raw helpers)', function (): void {
    it('throws InvalidColumnException when having() expression contains a backtick', function (): void {
        $builder = new PgSqlQueryBuilder(new MockConnection());
        expect(fn () => $builder->having('`count` > ?', [5]))
            ->toThrow(InvalidColumnException::class);
    });
});
