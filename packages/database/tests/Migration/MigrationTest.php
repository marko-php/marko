<?php

declare(strict_types=1);

use Marko\Database\Connection\ConnectionInterface;
use Marko\Database\Migration\Migration;

describe('Migration', function (): void {
    it('provides Migration base class with execute() helper', function (): void {
        $executedStatements = [];

        $connection = $this->createMock(ConnectionInterface::class);
        $connection->method('execute')
            ->willReturnCallback(function (string $sql) use (&$executedStatements): int {
                $executedStatements[] = $sql;

                return 1;
            });

        $migration = new class () extends Migration
        {
            public function up(
                ConnectionInterface $connection,
            ): void {
                $this->execute($connection, 'CREATE TABLE users (id INT PRIMARY KEY)');
                $this->execute($connection, 'CREATE TABLE posts (id INT PRIMARY KEY)');
            }

            public function down(
                ConnectionInterface $connection,
            ): void {
                $this->execute($connection, 'DROP TABLE posts');
                $this->execute($connection, 'DROP TABLE users');
            }
        };

        $migration->up($connection);

        expect($executedStatements)->toBe([
            'CREATE TABLE users (id INT PRIMARY KEY)',
            'CREATE TABLE posts (id INT PRIMARY KEY)',
        ]);
    });

    it('executes migration up() method', function (): void {
        $upCalled = false;

        $connection = $this->createMock(ConnectionInterface::class);

        $migration = new class ($upCalled) extends Migration
        {
            public function __construct(
                private bool &$called,
            ) {}

            public function up(
                ConnectionInterface $connection,
            ): void {
                $this->called = true;
            }

            public function down(ConnectionInterface $connection): void {}
        };

        $migration->up($connection);

        expect($upCalled)->toBeTrue();
    });

    it('executes migration down() method on rollback', function (): void {
        $downCalled = false;

        $connection = $this->createMock(ConnectionInterface::class);

        $migration = new class ($downCalled) extends Migration
        {
            public function __construct(
                private bool &$called,
            ) {}

            public function up(ConnectionInterface $connection): void {}

            public function down(
                ConnectionInterface $connection,
            ): void {
                $this->called = true;
            }
        };

        $migration->down($connection);

        expect($downCalled)->toBeTrue();
    });
});
