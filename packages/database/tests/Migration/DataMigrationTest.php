<?php

declare(strict_types=1);

use Marko\Database\Connection\ConnectionInterface;
use Marko\Database\Migration\DataMigration;
use Marko\Database\Migration\Migration;

describe('DataMigration', function (): void {
    it('creates DataMigration base class extending Migration', function (): void {
        $dataMigration = new class () extends DataMigration
        {
            public function up(ConnectionInterface $connection): void {}

            public function down(ConnectionInterface $connection): void {}
        };

        expect($dataMigration)
            ->toBeInstanceOf(Migration::class)
            ->toBeInstanceOf(DataMigration::class);
    });

    it('supports raw SQL via execute() with nowdoc syntax', function (): void {
        $executedStatements = [];

        $connection = $this->createMock(ConnectionInterface::class);
        $connection->method('execute')
            ->willReturnCallback(function (string $sql) use (&$executedStatements): int {
                $executedStatements[] = $sql;

                return 3;
            });

        $dataMigration = new class () extends DataMigration
        {
            public function up(
                ConnectionInterface $connection,
            ): void {
                $this->execute($connection, <<<'SQL'
                    INSERT INTO "categories" ("id", "name", "slug")
                    VALUES
                        (1, 'Uncategorized', 'uncategorized'),
                        (2, 'News', 'news'),
                        (3, 'Tutorials', 'tutorials')
                    ON CONFLICT ("id") DO NOTHING;
                    SQL);
            }

            public function down(
                ConnectionInterface $connection,
            ): void {
                $this->execute($connection, <<<'SQL'
                    DELETE FROM "categories" WHERE "id" IN (1, 2, 3);
                    SQL);
            }
        };

        $dataMigration->up($connection);

        expect($executedStatements)
            ->toHaveCount(1)
            ->and($executedStatements[0])->toContain('INSERT INTO "categories"')
            ->and($executedStatements[0])->toContain('ON CONFLICT');
    });

    it('provides insert() helper for single inserts', function (): void {
        $executedStatements = [];
        $executedBindings = [];

        $connection = $this->createMock(ConnectionInterface::class);
        $connection->method('execute')
            ->willReturnCallback(
                function (string $sql, array $bindings = []) use (&$executedStatements, &$executedBindings): int {
                    $executedStatements[] = $sql;
                    $executedBindings[] = $bindings;

                    return 1;
                },
            );

        $dataMigration = new class () extends DataMigration
        {
            public function up(
                ConnectionInterface $connection,
            ): void {
                $this->insert($connection, 'post_statuses', [
                    'id' => 1,
                    'name' => 'draft',
                    'label' => 'Draft',
                ]);
            }

            public function down(ConnectionInterface $connection): void {}
        };

        $dataMigration->up($connection);

        expect($executedStatements)
            ->toHaveCount(1)
            ->and($executedStatements[0])->toContain('INSERT INTO')
            ->and($executedStatements[0])->toContain('post_statuses')
            ->and($executedBindings[0])->toBe([1, 'draft', 'Draft']);
    });

    it('provides insert() helper for bulk inserts', function (): void {
        $executedStatements = [];
        $executedBindings = [];

        $connection = $this->createMock(ConnectionInterface::class);
        $connection->method('execute')
            ->willReturnCallback(
                function (string $sql, array $bindings = []) use (&$executedStatements, &$executedBindings): int {
                    $executedStatements[] = $sql;
                    $executedBindings[] = $bindings;

                    return 3;
                },
            );

        $dataMigration = new class () extends DataMigration
        {
            public function up(
                ConnectionInterface $connection,
            ): void {
                $this->insert($connection, 'post_statuses', [
                    ['id' => 1, 'name' => 'draft', 'label' => 'Draft'],
                    ['id' => 2, 'name' => 'published', 'label' => 'Published'],
                    ['id' => 3, 'name' => 'archived', 'label' => 'Archived'],
                ]);
            }

            public function down(ConnectionInterface $connection): void {}
        };

        $dataMigration->up($connection);

        expect($executedStatements)
            ->toHaveCount(1)
            ->and($executedStatements[0])->toContain('INSERT INTO')
            ->and($executedStatements[0])->toContain('post_statuses')
            // Should have 3 value placeholders: (?, ?, ?), (?, ?, ?), (?, ?, ?)
            ->and(substr_count($executedStatements[0], '?'))->toBe(9)
            ->and($executedBindings[0])->toBe([
                1, 'draft', 'Draft',
                2, 'published', 'Published',
                3, 'archived', 'Archived',
            ]);
    });

    it('provides update() helper with where clause', function (): void {
        $executedStatements = [];
        $executedBindings = [];

        $connection = $this->createMock(ConnectionInterface::class);
        $connection->method('execute')
            ->willReturnCallback(
                function (string $sql, array $bindings = []) use (&$executedStatements, &$executedBindings): int {
                    $executedStatements[] = $sql;
                    $executedBindings[] = $bindings;

                    return 1;
                },
            );

        $dataMigration = new class () extends DataMigration
        {
            public function up(
                ConnectionInterface $connection,
            ): void {
                $this->update(
                    $connection,
                    'post_statuses',
                    ['label' => 'Published Post', 'active' => 1],
                    ['name' => 'published'],
                );
            }

            public function down(ConnectionInterface $connection): void {}
        };

        $dataMigration->up($connection);

        expect($executedStatements)
            ->toHaveCount(1)
            ->and($executedStatements[0])->toContain('UPDATE')
            ->and($executedStatements[0])->toContain('post_statuses')
            ->and($executedStatements[0])->toContain('SET')
            ->and($executedStatements[0])->toContain('WHERE')
            ->and($executedBindings[0])->toBe(['Published Post', 1, 'published']);
    });

    it('provides delete() helper with where clause', function (): void {
        $executedStatements = [];
        $executedBindings = [];

        $connection = $this->createMock(ConnectionInterface::class);
        $connection->method('execute')
            ->willReturnCallback(
                function (string $sql, array $bindings = []) use (&$executedStatements, &$executedBindings): int {
                    $executedStatements[] = $sql;
                    $executedBindings[] = $bindings;

                    return 3;
                },
            );

        $dataMigration = new class () extends DataMigration
        {
            public function up(ConnectionInterface $connection): void {}

            public function down(
                ConnectionInterface $connection,
            ): void {
                $this->delete($connection, 'post_statuses', ['id' => 1]);
            }
        };

        $dataMigration->down($connection);

        expect($executedStatements)
            ->toHaveCount(1)
            ->and($executedStatements[0])->toContain('DELETE FROM')
            ->and($executedStatements[0])->toContain('post_statuses')
            ->and($executedStatements[0])->toContain('WHERE')
            ->and($executedBindings[0])->toBe([1]);
    });

    it('supports down() for rollback', function (): void {
        $downCalled = false;

        $connection = $this->createMock(ConnectionInterface::class);
        $connection->method('execute')->willReturn(1);

        $dataMigration = new class ($downCalled) extends DataMigration
        {
            public function __construct(
                /** @noinspection PhpPropertyOnlyWrittenInspection - Reference property modifies external variable */
                private bool &$called,
            ) {}

            public function up(ConnectionInterface $connection): void {}

            public function down(
                ConnectionInterface $connection,
            ): void {
                $this->delete($connection, 'post_statuses', ['id' => 1]);
                $this->called = true;
            }
        };

        $dataMigration->down($connection);

        expect($downCalled)->toBeTrue();
    });

    it('runs in production (not blocked like seeders)', function (): void {
        // DataMigration has no environment checks - it always runs
        // This is verified by the fact that there's no isProduction() check
        // in the class and no environment-dependent behavior
        $dataMigration = new class () extends DataMigration
        {
            public function up(ConnectionInterface $connection): void {}

            public function down(ConnectionInterface $connection): void {}
        };

        // Verify there's no method that would block production execution
        $reflection = new ReflectionClass($dataMigration);
        $methods = array_map(
            fn (ReflectionMethod $m): string => $m->getName(),
            $reflection->getMethods(),
        );

        // Should not have any environment-checking methods
        expect($methods)
            ->not->toContain('isProduction')
            ->not->toContain('shouldBlock')
            ->not->toContain('blockInProduction');
    });
});
