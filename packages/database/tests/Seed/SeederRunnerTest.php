<?php

declare(strict_types=1);

use Marko\Database\Connection\TransactionInterface;
use Marko\Database\Exceptions\SeederException;
use Marko\Database\Seed\SeederDefinition;
use Marko\Database\Seed\SeederInterface;
use Marko\Database\Seed\SeederRunner;

describe('SeederRunner', function (): void {
    it('runs seeders in order specified by attribute', function (): void {
        $executionOrder = [];

        // Create mock seeders with different orders
        $seeder1 = new class ($executionOrder) implements SeederInterface
        {
            public function __construct(
                /** @noinspection PhpPropertyOnlyWrittenInspection - Reference property modifies external variable */
                private array &$order,
            ) {}

            public function run(): void
            {
                $this->order[] = 'third';
            }
        };

        $seeder2 = new class ($executionOrder) implements SeederInterface
        {
            public function __construct(
                /** @noinspection PhpPropertyOnlyWrittenInspection - Reference property modifies external variable */
                private array &$order,
            ) {}

            public function run(): void
            {
                $this->order[] = 'first';
            }
        };

        $seeder3 = new class ($executionOrder) implements SeederInterface
        {
            public function __construct(
                /** @noinspection PhpPropertyOnlyWrittenInspection - Reference property modifies external variable */
                private array &$order,
            ) {}

            public function run(): void
            {
                $this->order[] = 'second';
            }
        };

        $definitions = [
            new SeederDefinition(seederClass: get_class($seeder1), name: 'third', order: 30),
            new SeederDefinition(seederClass: get_class($seeder2), name: 'first', order: 10),
            new SeederDefinition(seederClass: get_class($seeder3), name: 'second', order: 20),
        ];

        $runner = new SeederRunner(
            seeders: [
                get_class($seeder1) => $seeder1,
                get_class($seeder2) => $seeder2,
                get_class($seeder3) => $seeder3,
            ],
            isProduction: false,
        );

        $runner->runAll($definitions);

        expect($executionOrder)->toBe(['first', 'second', 'third']);
    });

    it('provides SeederRunner to execute discovered seeders', function (): void {
        $executed = false;

        $seeder = new class ($executed) implements SeederInterface
        {
            public function __construct(
                /** @noinspection PhpPropertyOnlyWrittenInspection - Reference property modifies external variable */
                private bool &$executed,
            ) {}

            public function run(): void
            {
                $this->executed = true;
            }
        };

        $definitions = [
            new SeederDefinition(seederClass: get_class($seeder), name: 'test', order: 0),
        ];

        $runner = new SeederRunner(
            seeders: [get_class($seeder) => $seeder],
            isProduction: false,
        );

        $runner->runAll($definitions);

        expect($executed)->toBeTrue();
    });

    it('blocks seeder execution in production environment', function (): void {
        $seeder = new class () implements SeederInterface
        {
            public function run(): void
            {
                // This should not execute
            }
        };

        $definitions = [
            new SeederDefinition(seederClass: get_class($seeder), name: 'test', order: 0),
        ];

        $runner = new SeederRunner(
            seeders: [get_class($seeder) => $seeder],
            isProduction: true,
        );

        expect(fn () => $runner->runAll($definitions))
            ->toThrow(SeederException::class, 'cannot be run in production');
    });

    it('supports running specific seeder by name', function (): void {
        $userSeederRan = false;
        $postSeederRan = false;

        $userSeeder = new class ($userSeederRan) implements SeederInterface
        {
            public function __construct(
                /** @noinspection PhpPropertyOnlyWrittenInspection - Reference property modifies external variable */
                private bool &$ran,
            ) {}

            public function run(): void
            {
                $this->ran = true;
            }
        };

        $postSeeder = new class ($postSeederRan) implements SeederInterface
        {
            public function __construct(
                /** @noinspection PhpPropertyOnlyWrittenInspection - Reference property modifies external variable */
                private bool &$ran,
            ) {}

            public function run(): void
            {
                $this->ran = true;
            }
        };

        $definitions = [
            new SeederDefinition(seederClass: get_class($userSeeder), name: 'users', order: 0),
            new SeederDefinition(seederClass: get_class($postSeeder), name: 'posts', order: 10),
        ];

        $runner = new SeederRunner(
            seeders: [
                get_class($userSeeder) => $userSeeder,
                get_class($postSeeder) => $postSeeder,
            ],
            isProduction: false,
        );

        $runner->runByName('users', $definitions);

        expect($userSeederRan)->toBeTrue()
            ->and($postSeederRan)->toBeFalse();
    });

    it('shows error when seeder not found', function (): void {
        $definitions = [];

        $runner = new SeederRunner(
            seeders: [],
            isProduction: false,
        );

        expect(fn () => $runner->runByName('nonexistent', $definitions))
            ->toThrow(SeederException::class, "'nonexistent' not found");
    });

    it('wraps seeder execution in transaction when transaction manager provided', function (): void {
        $transactionStarted = false;
        $transactionCommitted = false;
        $seederExecuted = false;

        $transaction = new class ($transactionStarted, $transactionCommitted) implements TransactionInterface
        {
            public function __construct(
                private bool &$started,
                private bool &$committed,
            ) {}

            public function beginTransaction(): void
            {
                $this->started = true;
            }

            public function commit(): void
            {
                $this->committed = true;
            }

            public function rollback(): void {}

            public function inTransaction(): bool
            {
                return $this->started && !$this->committed;
            }

            public function transaction(
                callable $callback,
            ): mixed {
                $this->beginTransaction();
                try {
                    $result = $callback();
                    $this->commit();

                    return $result;
                } catch (Throwable $e) {
                    $this->rollback();
                    throw $e;
                }
            }
        };

        $seeder = new class ($seederExecuted) implements SeederInterface
        {
            public function __construct(
                private bool &$executed,
            ) {}

            public function run(): void
            {
                $this->executed = true;
            }
        };

        $definitions = [
            new SeederDefinition(seederClass: get_class($seeder), name: 'test', order: 0),
        ];

        $runner = new SeederRunner(
            seeders: [get_class($seeder) => $seeder],
            isProduction: false,
            transaction: $transaction,
        );

        $runner->runAll($definitions);

        expect($transactionStarted)->toBeTrue()
            ->and($transactionCommitted)->toBeTrue()
            ->and($seederExecuted)->toBeTrue();
    });

    it('rolls back transaction when seeder fails', function (): void {
        $transactionRolledBack = false;

        $transaction = new class ($transactionRolledBack) implements TransactionInterface
        {
            public function __construct(
                private bool &$rolledBack,
            ) {}

            public function beginTransaction(): void {}

            public function commit(): void {}

            public function rollback(): void
            {
                $this->rolledBack = true;
            }

            public function inTransaction(): bool
            {
                return true;
            }

            public function transaction(
                callable $callback,
            ): mixed {
                $this->beginTransaction();
                try {
                    $result = $callback();
                    $this->commit();

                    return $result;
                } catch (Throwable $e) {
                    $this->rollback();
                    throw $e;
                }
            }
        };

        $seeder = new class () implements SeederInterface
        {
            public function run(): void
            {
                throw new RuntimeException('Seeder failed');
            }
        };

        $definitions = [
            new SeederDefinition(seederClass: get_class($seeder), name: 'test', order: 0),
        ];

        $runner = new SeederRunner(
            seeders: [get_class($seeder) => $seeder],
            isProduction: false,
            transaction: $transaction,
        );

        expect(fn () => $runner->runAll($definitions))
            ->toThrow(RuntimeException::class, 'Seeder failed');

        expect($transactionRolledBack)->toBeTrue();
    });
});
