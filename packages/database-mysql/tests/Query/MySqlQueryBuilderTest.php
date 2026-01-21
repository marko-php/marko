<?php

declare(strict_types=1);

namespace Marko\Database\MySql\Tests\Query;

use Marko\Database\MySql\Connection\MySqlConnection;
use Marko\Database\MySql\Query\MySqlQueryBuilder;
use Marko\Database\Query\QueryBuilderInterface;
use PDO;

describe('MySqlQueryBuilder', function (): void {
    beforeEach(function (): void {
        // Create a testable connection with SQLite for testing
        $this->connection = new class (
            host: 'localhost',
            port: 3306,
            database: 'test',
            username: 'root',
            password: '',
        ) extends MySqlConnection
        {
            private ?PDO $testPdo = null;

            protected function createPdo(
                string $dsn,
                string $username,
                string $password,
                array $options,
            ): PDO {
                $this->testPdo = new PDO('sqlite::memory:', options: $options);
                // Create test tables using SQLite syntax
                $this->testPdo->exec(
                    'CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT, email TEXT, status TEXT)',
                );
                $this->testPdo->exec(
                    'CREATE TABLE posts (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER, title TEXT, content TEXT)',
                );
                $this->testPdo->exec(
                    'CREATE TABLE comments (id INTEGER PRIMARY KEY AUTOINCREMENT, post_id INTEGER, body TEXT)',
                );

                // Seed some data
                $this->testPdo->exec(
                    "INSERT INTO users (name, email, status) VALUES ('Alice', 'alice@example.com', 'active')",
                );
                $this->testPdo->exec(
                    "INSERT INTO users (name, email, status) VALUES ('Bob', 'bob@example.com', 'inactive')",
                );
                $this->testPdo->exec(
                    "INSERT INTO users (name, email, status) VALUES ('Charlie', 'charlie@example.com', 'active')",
                );

                $this->testPdo->exec(
                    "INSERT INTO posts (user_id, title, content) VALUES (1, 'First Post', 'Hello World')",
                );
                $this->testPdo->exec(
                    "INSERT INTO posts (user_id, title, content) VALUES (1, 'Second Post', 'More content')",
                );
                $this->testPdo->exec(
                    "INSERT INTO posts (user_id, title, content) VALUES (2, 'Bobs Post', 'Bobs content')",
                );

                return $this->testPdo;
            }

            public function lastInsertId(): int
            {
                return (int) $this->testPdo->lastInsertId();
            }
        };

        $this->connection->connect();
        $this->builder = new MySqlQueryBuilder($this->connection);
    });

    it('implements QueryBuilderInterface', function (): void {
        expect($this->builder)->toBeInstanceOf(QueryBuilderInterface::class);
    });

    it('quotes identifiers with backticks', function (): void {
        $builder = new MySqlQueryBuilder($this->connection);

        // Test the quoteIdentifier method
        expect($builder->quoteIdentifier('users'))
            ->toBe('`users`')
            ->and($builder->quoteIdentifier('table.column'))->toBe('`table`.`column`')
            ->and($builder->quoteIdentifier('column'))->toBe('`column`');
    });

    it('builds SELECT queries with column selection', function (): void {
        $results = $this->builder
            ->table('users')
            ->select('name', 'email')
            ->get();

        expect($results)
            ->toHaveCount(3)
            ->and($results[0])->toHaveKeys(['name', 'email'])
            ->and($results[0]['name'])->toBe('Alice');
    });

    it('builds WHERE clauses with parameter binding', function (): void {
        $results = $this->builder
            ->table('users')
            ->select('name', 'email')
            ->where('status', '=', 'active')
            ->get();

        expect($results)
            ->toHaveCount(2)
            ->and($results[0]['name'])->toBe('Alice')
            ->and($results[1]['name'])->toBe('Charlie');
    });

    it('builds WHERE IN clauses correctly', function (): void {
        $results = $this->builder
            ->table('users')
            ->select('name')
            ->whereIn('name', ['Alice', 'Charlie'])
            ->get();

        $names = array_column($results, 'name');
        expect($results)
            ->toHaveCount(2)
            ->and($names)->toContain('Alice')
            ->and($names)->toContain('Charlie');
    });

    it('builds JOIN clauses with proper syntax', function (): void {
        $results = $this->builder
            ->table('posts')
            ->select('posts.title', 'users.name')
            ->join('users', 'posts.user_id', '=', 'users.id')
            ->get();

        expect($results)
            ->toHaveCount(3)
            ->and($results[0])->toHaveKeys(['title', 'name']);
    });

    it('builds ORDER BY with ASC/DESC', function (): void {
        $resultsAsc = $this->builder
            ->table('users')
            ->select('name')
            ->orderBy('name', 'ASC')
            ->get();

        expect($resultsAsc[0]['name'])
            ->toBe('Alice')
            ->and($resultsAsc[1]['name'])->toBe('Bob')
            ->and($resultsAsc[2]['name'])->toBe('Charlie');

        // Need a fresh builder for the second query
        $builder2 = new MySqlQueryBuilder($this->connection);
        $resultsDesc = $builder2
            ->table('users')
            ->select('name')
            ->orderBy('name', 'DESC')
            ->get();

        expect($resultsDesc[0]['name'])
            ->toBe('Charlie')
            ->and($resultsDesc[1]['name'])->toBe('Bob')
            ->and($resultsDesc[2]['name'])->toBe('Alice');
    });

    it('builds LIMIT and OFFSET clauses', function (): void {
        $results = $this->builder
            ->table('users')
            ->select('name')
            ->orderBy('name', 'ASC')
            ->limit(2)
            ->get();

        expect($results)
            ->toHaveCount(2)
            ->and($results[0]['name'])->toBe('Alice')
            ->and($results[1]['name'])->toBe('Bob');

        // Test with offset
        $builder2 = new MySqlQueryBuilder($this->connection);
        $resultsWithOffset = $builder2
            ->table('users')
            ->select('name')
            ->orderBy('name')
            ->limit(2)
            ->offset(1)
            ->get();

        expect($resultsWithOffset)
            ->toHaveCount(2)
            ->and($resultsWithOffset[0]['name'])->toBe('Bob')
            ->and($resultsWithOffset[1]['name'])->toBe('Charlie');
    });

    it('builds INSERT statements with parameter binding', function (): void {
        $id = $this->builder
            ->table('users')
            ->insert([
                'name' => 'David',
                'email' => 'david@example.com',
                'status' => 'active',
            ]);

        expect($id)->toBeGreaterThan(0);

        // Verify the insert
        $builder2 = new MySqlQueryBuilder($this->connection);
        $result = $builder2
            ->table('users')
            ->where('name', '=', 'David')
            ->first();

        expect($result)
            ->not->toBeNull()
            ->and($result['email'])->toBe('david@example.com');
    });

    it('builds UPDATE statements with WHERE clause', function (): void {
        $affected = $this->builder
            ->table('users')
            ->where('name', '=', 'Alice')
            ->update(['status' => 'premium']);

        expect($affected)->toBe(1);

        // Verify the update
        $builder2 = new MySqlQueryBuilder($this->connection);
        $result = $builder2
            ->table('users')
            ->where('name', '=', 'Alice')
            ->first();

        expect($result['status'])->toBe('premium');
    });

    it('builds DELETE statements with WHERE clause', function (): void {
        $affected = $this->builder
            ->table('users')
            ->where('name', '=', 'Bob')
            ->delete();

        expect($affected)->toBe(1);

        // Verify the delete
        $builder2 = new MySqlQueryBuilder($this->connection);
        $count = $builder2
            ->table('users')
            ->count();

        expect($count)->toBe(2);
    });

    it('returns last insert ID after insert', function (): void {
        $id = $this->builder
            ->table('users')
            ->insert([
                'name' => 'Eve',
                'email' => 'eve@example.com',
                'status' => 'active',
            ]);

        expect($id)->toBe(4); // 3 existing + 1 new
    });

    it('returns affected row count after update/delete', function (): void {
        // Update multiple rows
        $affected = $this->builder
            ->table('users')
            ->where('status', '=', 'active')
            ->update(['status' => 'verified']);

        expect($affected)->toBe(2); // Alice and Charlie

        // Delete multiple rows
        $builder2 = new MySqlQueryBuilder($this->connection);
        $deleted = $builder2
            ->table('users')
            ->where('status', '=', 'verified')
            ->delete();

        expect($deleted)->toBe(2);
    });

    it('executes raw queries with parameter binding', function (): void {
        $results = $this->builder->raw(
            'SELECT name FROM users WHERE status = ?',
            ['active'],
        );

        $names = array_column($results, 'name');
        expect($results)
            ->toHaveCount(2)
            ->and($names)->toContain('Alice')
            ->and($names)->toContain('Charlie');
    });
});
