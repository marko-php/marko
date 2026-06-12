<?php

declare(strict_types=1);

namespace Marko\Database\MySql\Tests\Query;

use Marko\Database\Connection\ConnectionInterface;
use Marko\Database\Connection\StatementInterface;
use Marko\Database\Exceptions\InvalidColumnException;
use Marko\Database\MySql\Query\MySqlQueryBuilder;
use ReflectionClass;
use RuntimeException;

/**
 * Minimal stub connection for security hardening tests.
 * Records the last query SQL/bindings so tests can assert on compiled SQL.
 */
class SecurityMockConnection implements ConnectionInterface
{
    public string $lastQuerySql = '';

    /** @var array<mixed> */
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

    public function query(
        string $sql,
        array $bindings = [],
    ): array {
        $this->lastQuerySql = $sql;
        $this->lastQueryBindings = $bindings;

        return $this->queryReturn;
    }

    public function execute(
        string $sql,
        array $bindings = [],
    ): int {
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

describe('MySqlQueryBuilder security hardening', function (): void {
    beforeEach(function (): void {
        $this->connection = new SecurityMockConnection();
        $this->builder = new MySqlQueryBuilder($this->connection);
    });

    it('escapes an embedded backtick in a column name when quoting an identifier', function (): void {
        $builder = new MySqlQueryBuilder($this->connection);
        $reflection = new ReflectionClass($builder);
        $method = $reflection->getMethod('quoteIdentifier');

        // A backtick inside the identifier should be doubled (escaped), not allow breakout
        expect($method->invoke($builder, 'col`name'))
            ->toBe('`col``name`');
    });

    it('rejects a where column containing a backtick or SQL comment', function (): void {
        expect(fn () => $this->builder->table('users')->where('col`bad', '=', 1))
            ->toThrow(InvalidColumnException::class)
            ->and(fn () => $this->builder->table('users')->where('col--bad', '=', 1))
            ->toThrow(InvalidColumnException::class);
    });

    it('rejects a where operator not in the allowlist', function (): void {
        expect(fn () => $this->builder->table('users')->where('id', 'SLEEP(1)--', 1))
            ->toThrow(InvalidColumnException::class);
    });

    it('rejects an orWhere operator not in the allowlist', function (): void {
        expect(fn () => $this->builder->table('users')->orWhere('id', 'SLEEP(1)--', 1))
            ->toThrow(InvalidColumnException::class);
    });

    it('rejects a whereIn column that is not a valid identifier', function (): void {
        expect(fn () => $this->builder->table('users')->whereIn('col--bad', [1, 2]))
            ->toThrow(InvalidColumnException::class);
    });

    it('rejects a whereNull column that is not a valid identifier', function (): void {
        expect(fn () => $this->builder->table('users')->whereNull('col--bad'))
            ->toThrow(InvalidColumnException::class);
    });

    it('rejects an orderBy column that is not a valid identifier', function (): void {
        expect(fn () => $this->builder->table('users')->orderBy('col--bad'))
            ->toThrow(InvalidColumnException::class);
    });

    it('rejects a join operator not in the allowlist', function (): void {
        expect(fn () => $this->builder->table('users')->join('posts', 'users.id', 'SLEEP(1)', 'posts.user_id'))
            ->toThrow(InvalidColumnException::class);
    });

    it('rejects a join table or column that is not a valid identifier', function (): void {
        expect(fn () => $this->builder->table('users')->join('bad--table', 'users.id', '=', 'posts.user_id'))
            ->toThrow(InvalidColumnException::class)
            ->and(fn () => $this->builder->table('users')->join('posts', 'bad--col', '=', 'posts.user_id'))
            ->toThrow(InvalidColumnException::class)
            ->and(fn () => $this->builder->table('users')->join('posts', 'users.id', '=', 'bad--col'))
            ->toThrow(InvalidColumnException::class);
    });

    it('rejects a table name that is not a valid identifier', function (): void {
        expect(fn () => $this->builder->table('bad--table')->get())
            ->toThrow(InvalidColumnException::class);
    });

    it('rejects an insert column key that is not a valid identifier', function (): void {
        expect(fn () => $this->builder->table('users')->insert(['bad--col' => 'value']))
            ->toThrow(InvalidColumnException::class);
    });

    it('rejects an update column key that is not a valid identifier', function (): void {
        expect(fn () => $this->builder->table('users')->update(['bad--col' => 'value']))
            ->toThrow(InvalidColumnException::class);
    });

    it(
        'still compiles a JSON-path where column (data->name) without rejecting it as an invalid identifier',
        function (): void {
            $this->builder
                ->table('users')
                ->where('data->name', '=', 'Bob')
                ->get();

            expect($this->connection->lastQuerySql)
                ->toContain('JSON_EXTRACT(`data`, \'$.name\')');
        },
    );

    it('still allows count() with no column (COUNT(*))', function (): void {
        $connection = new SecurityMockConnection(queryReturn: [['aggregate' => 5]]);
        $builder = new MySqlQueryBuilder($connection);

        $result = $builder->table('users')->count();

        expect($result)->toBe(5)
            ->and($connection->lastQuerySql)->toContain('COUNT(*)');
    });

    it('still builds a valid SELECT with a qualified identifier and an allowlisted operator', function (): void {
        $this->builder
            ->table('users')
            ->where('users.id', '=', 1)
            ->get();

        expect($this->connection->lastQuerySql)
            ->toContain('`users`.`id`')
            ->toContain('= ?');
    });
});
