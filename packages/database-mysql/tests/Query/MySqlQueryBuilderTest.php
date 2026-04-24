<?php

declare(strict_types=1);

namespace Marko\Database\MySql\Tests\Query;

use Marko\Core\Path\ProjectPaths;
use Marko\Database\Config\DatabaseConfig;
use Marko\Database\Connection\ConnectionInterface;
use Marko\Database\Exceptions\UnionShapeMismatchException;
use Marko\Database\MySql\Connection\MySqlConnection;
use Marko\Database\MySql\Query\MySqlQueryBuilder;
use Marko\Database\Query\QueryBuilderInterface;
use PDO;
use ReflectionClass;

function createQueryBuilderTestConfig(): DatabaseConfig
{
    $tempDir = sys_get_temp_dir() . '/marko_mysql_qb_' . uniqid();
    mkdir($tempDir . '/config', recursive: true);
    file_put_contents(
        $tempDir . '/config/database.php',
        '<?php return ' . var_export([
            'driver' => 'mysql',
            'host' => 'localhost',
            'port' => 3306,
            'database' => 'test',
            'username' => 'root',
            'password' => '',
        ], true) . ';',
    );

    $paths = new ProjectPaths($tempDir);
    $config = new DatabaseConfig($paths);

    unlink($tempDir . '/config/database.php');
    rmdir($tempDir . '/config');
    rmdir($tempDir);

    return $config;
}

describe('MySqlQueryBuilder', function (): void {
    beforeEach(function (): void {
        $config = createQueryBuilderTestConfig();

        // Create a testable connection with SQLite for testing
        $this->connection = new class ($config) extends MySqlConnection
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

        // Test the quoteIdentifier method via reflection (protected method)
        $reflection = new ReflectionClass($builder);
        $method = $reflection->getMethod('quoteIdentifier');

        expect($method->invoke($builder, 'users'))
            ->toBe('`users`')
            ->and($method->invoke($builder, 'table.column'))->toBe('`table`.`column`')
            ->and($method->invoke($builder, 'column'))->toBe('`column`');
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

    it('combines two queries with UNION producing deduplicated rows', function (): void {
        // Verify SQL output: backtick quoting and parenthesized UNION form
        $recordedSql = '';
        $recordedBindings = [];

        $recordingConnection = new class ($this->connection, $recordedSql, $recordedBindings) extends MySqlConnection
        {
            public function __construct(
                private readonly ConnectionInterface $inner,
                public string &$lastSql,
                public array &$lastBindings,
            ) {}

            public function connect(): void {}

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
        };

        $left = (new MySqlQueryBuilder($recordingConnection))
            ->table('users')
            ->select('name')
            ->where('status', '=', 'active');

        $right = (new MySqlQueryBuilder($recordingConnection))
            ->table('users')
            ->select('name')
            ->where('status', '=', 'inactive');

        $left->union($right)->get();

        expect($recordedSql)->toBe(
            '(SELECT `name` FROM `users` WHERE `status` = ?) UNION (SELECT `name` FROM `users` WHERE `status` = ?)',
        )
            ->and($recordedBindings)->toBe(['active', 'inactive']);
    });

    it('throws UnionShapeMismatchException when the two queries select different numbers of columns', function (): void {
        $left = (new MySqlQueryBuilder($this->connection))
            ->table('users')
            ->select('name', 'email');

        $right = (new MySqlQueryBuilder($this->connection))
            ->table('users')
            ->select('name');

        expect(fn () => $left->union($right))
            ->toThrow(UnionShapeMismatchException::class);
    });

    it('combines two queries with UNION ALL preserving duplicates', function (): void {
        $recordedSql = '';
        $recordedBindings = [];

        $recordingConnection = new class ($recordedSql, $recordedBindings) extends MySqlConnection
        {
            public function __construct(
                public string &$lastSql,
                public array &$lastBindings,
            ) {}

            public function connect(): void {}

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
        };

        $left = (new MySqlQueryBuilder($recordingConnection))
            ->table('users')
            ->select('name')
            ->where('status', '=', 'active');

        $right = (new MySqlQueryBuilder($recordingConnection))
            ->table('users')
            ->select('name')
            ->where('status', '=', 'inactive');

        $left->unionAll($right)->get();

        expect($recordedSql)->toBe(
            '(SELECT `name` FROM `users` WHERE `status` = ?) UNION ALL (SELECT `name` FROM `users` WHERE `status` = ?)',
        )
            ->and($recordedBindings)->toBe(['active', 'inactive']);
    });

    it('composes UNION with ORDER BY applied to the combined result', function (): void {
        $recordedSql = '';
        $recordingConnection = new class ($recordedSql) extends MySqlConnection
        {
            public function __construct(public string &$lastSql) {}

            public function connect(): void {}

            public function query(string $sql, array $bindings = []): array
            {
                $this->lastSql = $sql;

                return [];
            }

            public function execute(string $sql, array $bindings = []): int
            {
                return 0;
            }
        };

        $left = (new MySqlQueryBuilder($recordingConnection))
            ->table('users')
            ->select('name');

        $right = (new MySqlQueryBuilder($recordingConnection))
            ->table('admins')
            ->select('name');

        $left->union($right)->orderBy('name', 'ASC')->get();

        expect($recordedSql)->toBe(
            '(SELECT `name` FROM `users`) UNION (SELECT `name` FROM `admins`) ORDER BY `name` ASC',
        );
    });

    it('composes UNION with LIMIT applied to the combined result', function (): void {
        $recordedSql = '';
        $recordingConnection = new class ($recordedSql) extends MySqlConnection
        {
            public function __construct(public string &$lastSql) {}

            public function connect(): void {}

            public function query(string $sql, array $bindings = []): array
            {
                $this->lastSql = $sql;

                return [];
            }

            public function execute(string $sql, array $bindings = []): int
            {
                return 0;
            }
        };

        $left = (new MySqlQueryBuilder($recordingConnection))
            ->table('users')
            ->select('name');

        $right = (new MySqlQueryBuilder($recordingConnection))
            ->table('admins')
            ->select('name');

        $left->union($right)->limit(5)->get();

        expect($recordedSql)->toBe(
            '(SELECT `name` FROM `users`) UNION (SELECT `name` FROM `admins`) LIMIT 5',
        );
    });

    it('parameterizes bindings from both sides of the UNION safely', function (): void {
        $recordedSql = '';
        $recordedBindings = [];

        $recordingConnection = new class ($recordedSql, $recordedBindings) extends MySqlConnection
        {
            public function __construct(
                public string &$lastSql,
                public array &$lastBindings,
            ) {}

            public function connect(): void {}

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
        };

        $left = (new MySqlQueryBuilder($recordingConnection))
            ->table('users')
            ->select('name')
            ->where('status', '=', 'active');

        $right = (new MySqlQueryBuilder($recordingConnection))
            ->table('admins')
            ->select('name')
            ->where('role', '=', 'superadmin');

        $left->union($right)->get();

        expect($recordedSql)->toBe(
            '(SELECT `name` FROM `users` WHERE `status` = ?) UNION (SELECT `name` FROM `admins` WHERE `role` = ?)',
        )
            ->and($recordedBindings)->toBe(['active', 'superadmin']);
    });

    it('returns distinct rows for a query that would otherwise duplicate due to joins', function (): void {
        // Alice has 2 posts; joining users to posts without DISTINCT yields duplicate user rows
        $withoutDistinct = (new MySqlQueryBuilder($this->connection))
            ->table('users')
            ->select('users.name')
            ->join('posts', 'users.id', '=', 'posts.user_id')
            ->where('users.name', '=', 'Alice')
            ->get();

        expect($withoutDistinct)->toHaveCount(2); // duplicated

        $withDistinct = (new MySqlQueryBuilder($this->connection))
            ->table('users')
            ->select('users.name')
            ->join('posts', 'users.id', '=', 'posts.user_id')
            ->where('users.name', '=', 'Alice')
            ->distinct()
            ->get();

        expect($withDistinct)
            ->toHaveCount(1)
            ->and($withDistinct[0]['name'])->toBe('Alice');
    });

    it('quotes both the column and the alias using driver-specific identifier quoting', function (): void {
        $results = $this->builder
            ->table('users')
            ->select('users.name as author_name')
            ->get();

        expect($results)->toHaveCount(3)
            ->and($results[0])->toHaveKey('author_name')
            ->and($results[0]['author_name'])->toBe('Alice');
    });

    it('returns rows keyed by alias when a select uses an alias', function (): void {
        $results = $this->builder
            ->table('users')
            ->select('name as author_name', 'email as contact')
            ->where('status', '=', 'active')
            ->get();

        expect($results)->toHaveCount(2)
            ->and($results[0])->toHaveKeys(['author_name', 'contact'])
            ->and(array_key_exists('name', $results[0]))->toBeFalse()
            ->and(array_key_exists('email', $results[0]))->toBeFalse()
            ->and($results[0]['author_name'])->toBe('Alice')
            ->and($results[0]['contact'])->toBe('alice@example.com');
    });
});
