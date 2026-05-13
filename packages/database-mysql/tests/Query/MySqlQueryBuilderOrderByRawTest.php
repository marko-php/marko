<?php

declare(strict_types=1);

namespace Marko\Database\MySql\Tests\Query;

use Marko\Database\Exceptions\InvalidColumnException;
use Marko\Database\MySql\Connection\MySqlConnection;
use Marko\Database\MySql\Query\MySqlQueryBuilder;

function makeOrderByRawRecordingConnection(string &$lastSql, array &$lastBindings): MySqlConnection
{
    return new class ($lastSql, $lastBindings) extends MySqlConnection
    {
        public function __construct(
            public string &$lastSql,
            public array &$lastBindings,
        ) {}

        public function connect(): void {}

        public function disconnect(): void {}

        public function isConnected(): bool
        {
            return true;
        }

        public function query(string $sql, array $bindings = []): array
        {
            $this->lastSql = $sql;
            $this->lastBindings = $bindings;

            return [];
        }

        public function execute(string $sql, array $bindings = []): int
        {
            return 0;
        }

        public function lastInsertId(): int
        {
            return 0;
        }

        public function beginTransaction(): void {}

        public function commit(): void {}

        public function rollback(): void {}
    };
}

describe('MySqlQueryBuilder orderByRaw', function (): void {
    it('appends a raw expression to the order clause without quoting', function (): void {
        $sql = '';
        $bindings = [];
        $conn = makeOrderByRawRecordingConnection($sql, $bindings);

        (new MySqlQueryBuilder($conn))
            ->table('products')
            ->select('id')
            ->orderByRaw('FIELD(status, "active", "inactive")')
            ->get();

        expect($sql)->toBe(
            'SELECT `id` FROM `products` ORDER BY FIELD(status, "active", "inactive") ASC',
        );
    });

    it('rejects expressions containing semicolons', function (): void {
        $sql = '';
        $bindings = [];
        $conn = makeOrderByRawRecordingConnection($sql, $bindings);

        expect(
            fn () => (new MySqlQueryBuilder($conn))
                ->table('products')
                ->select('id')
                ->orderByRaw('id; DROP TABLE products--'),
        )->toThrow(InvalidColumnException::class);
    });

    it('rejects expressions containing SQL comments (-- or /*)', function (): void {
        $sql = '';
        $bindings = [];
        $conn = makeOrderByRawRecordingConnection($sql, $bindings);

        expect(
            fn () => (new MySqlQueryBuilder($conn))
                ->table('products')
                ->select('id')
                ->orderByRaw('id -- comment'),
        )->toThrow(InvalidColumnException::class);

        expect(
            fn () => (new MySqlQueryBuilder($conn))
                ->table('products')
                ->select('id')
                ->orderByRaw('id /* comment */'),
        )->toThrow(InvalidColumnException::class);
    });

    it('composes correctly with a regular orderBy call before or after', function (): void {
        // orderBy before orderByRaw
        $sqlBefore = '';
        $bindingsBefore = [];
        $connBefore = makeOrderByRawRecordingConnection($sqlBefore, $bindingsBefore);

        (new MySqlQueryBuilder($connBefore))
            ->table('products')
            ->select('id')
            ->orderBy('name', 'ASC')
            ->orderByRaw('FIELD(status, "active", "inactive")')
            ->get();

        expect($sqlBefore)->toBe(
            'SELECT `id` FROM `products` ORDER BY `name` ASC, FIELD(status, "active", "inactive") ASC',
        );

        // orderByRaw before orderBy
        $sqlAfter = '';
        $bindingsAfter = [];
        $connAfter = makeOrderByRawRecordingConnection($sqlAfter, $bindingsAfter);

        (new MySqlQueryBuilder($connAfter))
            ->table('products')
            ->select('id')
            ->orderByRaw('FIELD(status, "active", "inactive")')
            ->orderBy('name', 'ASC')
            ->get();

        expect($sqlAfter)->toBe(
            'SELECT `id` FROM `products` ORDER BY FIELD(status, "active", "inactive") ASC, `name` ASC',
        );
    });

    it('emits a single ORDER BY clause with comma-separated entries for mixed regular and raw orders', function (): void {
        $sql = '';
        $bindings = [];
        $conn = makeOrderByRawRecordingConnection($sql, $bindings);

        (new MySqlQueryBuilder($conn))
            ->table('products')
            ->select('id')
            ->orderBy('category', 'ASC')
            ->orderByRaw('FIELD(status, "active", "inactive")')
            ->orderBy('name', 'DESC')
            ->get();

        expect($sql)->toBe(
            'SELECT `id` FROM `products` ORDER BY `category` ASC, FIELD(status, "active", "inactive") ASC, `name` DESC',
        );
    });

    it('preserves direction asc or desc on the emitted ORDER BY', function (): void {
        $sqlAsc = '';
        $bindingsAsc = [];
        $connAsc = makeOrderByRawRecordingConnection($sqlAsc, $bindingsAsc);

        (new MySqlQueryBuilder($connAsc))
            ->table('products')
            ->select('id')
            ->orderByRaw('FIELD(status, "active", "inactive")', 'asc')
            ->get();

        expect($sqlAsc)->toBe(
            'SELECT `id` FROM `products` ORDER BY FIELD(status, "active", "inactive") ASC',
        );

        $sqlDesc = '';
        $bindingsDesc = [];
        $connDesc = makeOrderByRawRecordingConnection($sqlDesc, $bindingsDesc);

        (new MySqlQueryBuilder($connDesc))
            ->table('products')
            ->select('id')
            ->orderByRaw('FIELD(status, "active", "inactive")', 'desc')
            ->get();

        expect($sqlDesc)->toBe(
            'SELECT `id` FROM `products` ORDER BY FIELD(status, "active", "inactive") DESC',
        );

        $sqlInvalid = '';
        $bindingsInvalid = [];
        $connInvalid = makeOrderByRawRecordingConnection($sqlInvalid, $bindingsInvalid);

        (new MySqlQueryBuilder($connInvalid))
            ->table('products')
            ->select('id')
            ->orderByRaw('FIELD(status, "active", "inactive")', 'INVALID')
            ->get();

        expect($sqlInvalid)->toBe(
            'SELECT `id` FROM `products` ORDER BY FIELD(status, "active", "inactive") ASC',
        );
    });
});
