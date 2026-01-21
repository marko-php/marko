<?php

declare(strict_types=1);

namespace Marko\Database\Tests\Feature;

use Marko\Database\Connection\ConnectionInterface;
use Marko\Database\Connection\StatementInterface;
use Marko\Database\Exceptions\SeederException;
use Marko\Database\Seed\SeederDefinition;
use Marko\Database\Seed\SeederInterface;
use Marko\Database\Seed\SeederRunner;
use RuntimeException;

// Test seeders

class UserSeeder implements SeederInterface
{
    public array $executedInserts = [];

    public function run(
        ConnectionInterface $connection,
    ): void {
        $connection->execute(
            'INSERT INTO users (name, email) VALUES (?, ?)',
            ['John Doe', 'john@example.com'],
        );
        $connection->execute(
            'INSERT INTO users (name, email) VALUES (?, ?)',
            ['Jane Doe', 'jane@example.com'],
        );
    }
}

class PostSeeder implements SeederInterface
{
    public function run(
        ConnectionInterface $connection,
    ): void {
        $connection->execute(
            'INSERT INTO posts (title, author_id) VALUES (?, ?)',
            ['First Post', 1],
        );
    }
}

class DependentSeeder implements SeederInterface
{
    public function run(
        ConnectionInterface $connection,
    ): void {
        $connection->execute(
            'INSERT INTO comments (post_id, content) VALUES (?, ?)',
            [1, 'A comment'],
        );
    }
}

describe('Seeder Execution', function (): void {
    it('runs seeders and populates test data', function (): void {
        $insertedData = [];

        $connection = new class ($insertedData) implements ConnectionInterface
        {
            public function __construct(
                private array &$inserts,
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
                return [];
            }

            public function execute(
                string $sql,
                array $bindings = [],
            ): int {
                if (str_starts_with($sql, 'INSERT')) {
                    $this->inserts[] = ['sql' => $sql, 'bindings' => $bindings];
                }

                return 1;
            }

            public function prepare(
                string $sql,
            ): StatementInterface {
                throw new RuntimeException('Not implemented');
            }

            public function lastInsertId(): int
            {
                return 1;
            }
        };

        $userSeeder = new UserSeeder();
        $seeders = [UserSeeder::class => $userSeeder];

        $definitions = [
            new SeederDefinition(
                seederClass: UserSeeder::class,
                name: 'users',
                order: 1,
            ),
        ];

        $runner = new SeederRunner($seeders, isProduction: false);
        $runner->runAll($definitions, $connection);

        expect($insertedData)->toHaveCount(2);
        expect($insertedData[0]['bindings'])->toContain('John Doe');
        expect($insertedData[1]['bindings'])->toContain('Jane Doe');
    });

    it('runs seeders in order based on order property', function (): void {
        $executionOrder = [];

        $connection = new class ($executionOrder) implements ConnectionInterface
        {
            public function __construct(
                private array &$order,
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
                return [];
            }

            public function execute(
                string $sql,
                array $bindings = [],
            ): int {
                if (str_contains($sql, 'users')) {
                    $this->order[] = 'users';
                } elseif (str_contains($sql, 'posts')) {
                    $this->order[] = 'posts';
                } elseif (str_contains($sql, 'comments')) {
                    $this->order[] = 'comments';
                }

                return 1;
            }

            public function prepare(
                string $sql,
            ): StatementInterface {
                throw new RuntimeException('Not implemented');
            }

            public function lastInsertId(): int
            {
                return 1;
            }
        };

        $userSeeder = new UserSeeder();
        $postSeeder = new PostSeeder();
        $dependentSeeder = new DependentSeeder();

        $seeders = [
            UserSeeder::class => $userSeeder,
            PostSeeder::class => $postSeeder,
            DependentSeeder::class => $dependentSeeder,
        ];

        // Definitions in random order, but with order property
        $definitions = [
            new SeederDefinition(seederClass: DependentSeeder::class, name: 'comments', order: 30),
            new SeederDefinition(seederClass: UserSeeder::class, name: 'users', order: 10),
            new SeederDefinition(seederClass: PostSeeder::class, name: 'posts', order: 20),
        ];

        $runner = new SeederRunner($seeders, isProduction: false);
        $runner->runAll($definitions, $connection);

        // Users (order 10) should run first
        // Posts (order 20) should run second
        // Comments (order 30) should run last
        expect($executionOrder[0])->toBe('users');
        expect($executionOrder[2])->toBe('posts');
        expect($executionOrder[3])->toBe('comments');
    });

    it('runs specific seeder by name', function (): void {
        $ran = [];

        $connection = new class ($ran) implements ConnectionInterface
        {
            public function __construct(
                private array &$ran,
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
                return [];
            }

            public function execute(
                string $sql,
                array $bindings = [],
            ): int {
                if (str_contains($sql, 'posts')) {
                    $this->ran[] = 'posts';
                } elseif (str_contains($sql, 'users')) {
                    $this->ran[] = 'users';
                }

                return 1;
            }

            public function prepare(
                string $sql,
            ): StatementInterface {
                throw new RuntimeException('Not implemented');
            }

            public function lastInsertId(): int
            {
                return 1;
            }
        };

        $userSeeder = new UserSeeder();
        $postSeeder = new PostSeeder();

        $seeders = [
            UserSeeder::class => $userSeeder,
            PostSeeder::class => $postSeeder,
        ];

        $definitions = [
            new SeederDefinition(seederClass: UserSeeder::class, name: 'users', order: 10),
            new SeederDefinition(seederClass: PostSeeder::class, name: 'posts', order: 20),
        ];

        $runner = new SeederRunner($seeders, isProduction: false);
        $runner->runByName('posts', $definitions, $connection);

        // Only posts seeder should have run
        expect($ran)->toHaveCount(1);
        expect($ran[0])->toBe('posts');
    });

    it('throws SeederException when running in production', function (): void {
        $connection = $this->createMock(ConnectionInterface::class);
        $userSeeder = new UserSeeder();
        $seeders = [UserSeeder::class => $userSeeder];

        $definitions = [
            new SeederDefinition(seederClass: UserSeeder::class, name: 'users', order: 1),
        ];

        $runner = new SeederRunner($seeders, isProduction: true);

        expect(fn () => $runner->runAll($definitions, $connection))
            ->toThrow(SeederException::class, 'production');
    });

    it('throws SeederException when seeder not found', function (): void {
        $connection = $this->createMock(ConnectionInterface::class);
        $seeders = [];

        $definitions = [
            new SeederDefinition(seederClass: UserSeeder::class, name: 'users', order: 1),
        ];

        $runner = new SeederRunner($seeders, isProduction: false);

        expect(fn () => $runner->runByName('nonexistent', $definitions, $connection))
            ->toThrow(SeederException::class, 'not found');
    });
});
