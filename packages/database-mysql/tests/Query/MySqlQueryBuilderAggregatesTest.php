<?php

declare(strict_types=1);

namespace Marko\Database\MySql\Tests\Query;

use Marko\Core\Path\ProjectPaths;
use Marko\Database\Config\DatabaseConfig;
use Marko\Database\Exceptions\InvalidColumnException;
use Marko\Database\MySql\Connection\MySqlConnection;
use Marko\Database\MySql\Query\MySqlQueryBuilder;
use PDO;
use ReflectionClass;

function createAggregatesTestConfig(): DatabaseConfig
{
    $tempDir = sys_get_temp_dir() . '/marko_mysql_agg_' . bin2hex(random_bytes(8));
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

function createAggregatesSqliteConnection(): MySqlConnection
{
    $config = createAggregatesTestConfig();

    return new class ($config) extends MySqlConnection
    {
        private ?PDO $testPdo = null;

        protected function createPdo(
            string $dsn,
            string $username,
            string $password,
            array $options,
        ): PDO {
            $this->testPdo = new PDO('sqlite::memory:', options: $options);
            $this->testPdo->exec(
                'CREATE TABLE scores (id INTEGER PRIMARY KEY AUTOINCREMENT, player TEXT, points INTEGER, rating REAL)',
            );
            $this->testPdo->exec("INSERT INTO scores (player, points, rating) VALUES ('Alice', 10, 4.5)");
            $this->testPdo->exec("INSERT INTO scores (player, points, rating) VALUES ('Bob', 30, 3.2)");
            $this->testPdo->exec("INSERT INTO scores (player, points, rating) VALUES ('Charlie', 20, 4.8)");

            return $this->testPdo;
        }

        public function lastInsertId(): int
        {
            return (int) $this->testPdo->lastInsertId();
        }
    };
}

describe('MySqlQueryBuilder aggregates', function (): void {
    beforeEach(function (): void {
        $this->connection = createAggregatesSqliteConnection();
        $this->connection->connect();
        $this->builder = new MySqlQueryBuilder($this->connection);
    });

    it('returns the minimum value of a numeric column via min()', function (): void {
        $result = $this->builder->table('scores')->min('points');

        expect($result)->toBe(10);
    });

    it('returns the maximum value of a numeric column via max()', function (): void {
        $result = $this->builder->table('scores')->max('points');

        expect($result)->toBe(30);
    });

    it('returns the sum of a numeric column via sum()', function (): void {
        $result = $this->builder->table('scores')->sum('points');

        expect($result)->toBe(60);
    });

    it('returns the average of a numeric column via avg()', function (): void {
        $result = $this->builder->table('scores')->avg('points');

        expect($result)->toBe(20.0);
    });

    it('returns the row count via count() with no column argument', function (): void {
        $result = $this->builder->table('scores')->count();

        expect($result)->toBe(3);
    });

    it('returns the count of non-null values via count(column)', function (): void {
        // Insert a row with null points
        (new MySqlQueryBuilder($this->connection))
            ->table('scores')
            ->insert(['player' => 'Dave', 'points' => null, 'rating' => null]);

        $result = (new MySqlQueryBuilder($this->connection))
            ->table('scores')
            ->count('points');

        expect($result)->toBe(3); // Dave's null points not counted
    });

    it('returns null from min/max/sum/avg when the result set is empty', function (): void {
        $min = (new MySqlQueryBuilder($this->connection))
            ->table('scores')
            ->where('player', '=', 'NonExistent')
            ->min('points');

        $max = (new MySqlQueryBuilder($this->connection))
            ->table('scores')
            ->where('player', '=', 'NonExistent')
            ->max('points');

        $sum = (new MySqlQueryBuilder($this->connection))
            ->table('scores')
            ->where('player', '=', 'NonExistent')
            ->sum('points');

        $avg = (new MySqlQueryBuilder($this->connection))
            ->table('scores')
            ->where('player', '=', 'NonExistent')
            ->avg('points');

        expect($min)->toBeNull()
            ->and($max)->toBeNull()
            ->and($sum)->toBeNull()
            ->and($avg)->toBeNull();
    });

    it('returns int 0 from count() when the result set is empty', function (): void {
        $result = $this->builder
            ->table('scores')
            ->where('player', '=', 'NonExistent')
            ->count();

        expect($result)->toBe(0);
    });

    it('respects WHERE clauses when computing aggregates', function (): void {
        $min = (new MySqlQueryBuilder($this->connection))
            ->table('scores')
            ->where('points', '>', 10)
            ->min('points');

        expect($min)->toBe(20);
    });

    it('rejects aggregate column identifiers that fail the identifier whitelist (no SQL injection)', function (): void {
        expect(fn () => $this->builder->table('scores')->min('points; DROP TABLE scores--'))
            ->toThrow(InvalidColumnException::class)
            ->and(fn () => $this->builder->table('scores')->max("col' OR '1'='1"))
            ->toThrow(InvalidColumnException::class)
            ->and(fn () => $this->builder->table('scores')->sum('1=1'))
            ->toThrow(InvalidColumnException::class)
            ->and(fn () => $this->builder->table('scores')->avg('/*bad*/'))
            ->toThrow(InvalidColumnException::class);
    });

    it('existing int return type of count() remains int (no nullable); only the signature gains an optional column argument', function (): void {
        $reflection = new ReflectionClass(MySqlQueryBuilder::class);
        $method = $reflection->getMethod('count');
        $returnType = $method->getReturnType();
        $params = $method->getParameters();

        expect($returnType?->getName())->toBe('int')
            ->and($returnType?->allowsNull())->toBeFalse()
            ->and($params)->toHaveCount(1)
            ->and($params[0]->getName())->toBe('column')
            ->and($params[0]->isOptional())->toBeTrue()
            ->and($params[0]->allowsNull())->toBeTrue()
            ->and($params[0]->getDefaultValue())->toBeNull();
    });
});
