<?php

declare(strict_types=1);

use Marko\Core\Environment\AppEnvironment;
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
            appEnvironment: new AppEnvironment(['APP_ENV' => 'local']),
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
            appEnvironment: new AppEnvironment(['APP_ENV' => 'local']),
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
            appEnvironment: new AppEnvironment(['APP_ENV' => 'production']),
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
            appEnvironment: new AppEnvironment(['APP_ENV' => 'local']),
        );

        $runner->runByName('users', $definitions);

        expect($userSeederRan)->toBeTrue()
            ->and($postSeederRan)->toBeFalse();
    });

    it('shows error when seeder not found', function (): void {
        $definitions = [];

        $runner = new SeederRunner(
            seeders: [],
            appEnvironment: new AppEnvironment(['APP_ENV' => 'local']),
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
                return $this->started
                    && !$this->committed;
            }

            public function transaction(
                callable $callback,
                int $attempts = 1,
                int|Closure|null $backoff = null,
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

            public function transactionLevel(): int
            {
                return 0;
            }

            public function afterCommit(callable $callback): void {}

            public function afterRollback(callable $callback): void {}
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
            appEnvironment: new AppEnvironment(['APP_ENV' => 'local']),
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
                int $attempts = 1,
                int|Closure|null $backoff = null,
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

            public function transactionLevel(): int
            {
                return 0;
            }

            public function afterCommit(callable $callback): void {}

            public function afterRollback(callable $callback): void {}
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
            appEnvironment: new AppEnvironment(['APP_ENV' => 'local']),
            transaction: $transaction,
        );

        expect(fn () => $runner->runAll($definitions))
            ->toThrow(RuntimeException::class, 'Seeder failed');

        expect($transactionRolledBack)->toBeTrue();
    });
});

/**
 * @return array{runner: SeederRunner, definitions: list<SeederDefinition>, ran: object{count: int}}
 */
function createPolicyRunner(
    string $environment,
): array {
    $ran = new class ()
    {
        public int $count = 0;
    };
    $seeder = new readonly class ($ran) implements SeederInterface
    {
        public function __construct(
            private object $ran,
        ) {}

        public function run(): void
        {
            $this->ran->count++;
        }
    };

    return [
        'runner' => new SeederRunner(
            seeders: [get_class($seeder) => $seeder],
            appEnvironment: new AppEnvironment(['APP_ENV' => $environment]),
        ),
        'definitions' => [new SeederDefinition(seederClass: get_class($seeder), name: 'users', order: 0)],
        'ran' => $ran,
    ];
}

describe('SeederRunner environment policy', function (): void {
    it('runs seeders in development and testing without force', function (string $environment): void {
        ['runner' => $runner, 'definitions' => $definitions, 'ran' => $ran] = createPolicyRunner($environment);

        $runner->runAll($definitions);
        $runner->runByName('users', $definitions);

        expect($ran->count)->toBe(2);
    })->with(['local', 'development', 'testing', 'test']);

    it('throws requiresForce naming the environment in staging without force', function (string $environment): void {
        ['runner' => $runner, 'definitions' => $definitions, 'ran' => $ran] = createPolicyRunner($environment);

        expect(fn () => $runner->runAll($definitions))
            ->toThrow(SeederException::class, "Seeders are refused in the '$environment' environment without force")
            ->and($ran->count)->toBe(0);
    })->with(['staging', 'qa']);

    it('runs seeders in staging with force', function (): void {
        ['runner' => $runner, 'definitions' => $definitions, 'ran' => $ran] = createPolicyRunner('staging');

        $runner->runAll($definitions, force: true);
        $runner->runByName('users', $definitions, force: true);

        expect($ran->count)->toBe(2);
    });

    it('throws blockedInProduction in production even with force', function (): void {
        ['runner' => $runner, 'definitions' => $definitions, 'ran' => $ran] = createPolicyRunner('production');

        expect(fn () => $runner->runAll($definitions, force: true))
            ->toThrow(SeederException::class, 'cannot be run in production')
            ->and(fn () => $runner->runByName('users', $definitions, force: true))
            ->toThrow(SeederException::class, 'cannot be run in production')
            ->and($ran->count)->toBe(0);
    });

    it('applies the same policy to runByName', function (): void {
        ['runner' => $runner, 'definitions' => $definitions, 'ran' => $ran] = createPolicyRunner('staging');

        expect(fn () => $runner->runByName('users', $definitions))
            ->toThrow(SeederException::class, "Seeders are refused in the 'staging' environment without force")
            ->and($ran->count)->toBe(0);
    });
});
