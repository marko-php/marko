<?php

declare(strict_types=1);

use Marko\Core\Contracts\ResettableInterface;
use Marko\Database\Connection\ConnectionInterface;
use Marko\Database\Connection\PendingAfterCommitInterface;
use Marko\Database\Connection\PrimaryReadInterface;
use Marko\Database\Connection\StatementInterface;
use Marko\Database\Connection\TransactionalDdlInterface;
use Marko\Database\Connection\TransactionInterface;
use Marko\Database\Exceptions\TransactionException;
use Marko\Database\ReadWrite\Connection\ReadWriteConnection;
use Marko\Database\ReadWrite\Exceptions\ReadException;
use Marko\Database\ReadWrite\Replica\ReplicaSelectorInterface;

function makeConnection(
    array $overrides = [],
): ConnectionInterface&TransactionInterface {
    return new class ($overrides) implements ConnectionInterface, TransactionInterface, PendingAfterCommitInterface
    {
        public array $calls = [];

        /** @var list<int> */
        public array $transactionAttempts = [];

        /** @var list<int|Closure|null> */
        public array $transactionBackoffs = [];

        public function __construct(private readonly array $overrides) {}

        public function connect(): void
        {
            $this->calls[] = 'connect';
        }

        public function disconnect(): void
        {
            $this->calls[] = 'disconnect';
        }

        public function isConnected(): bool
        {
            return $this->overrides['isConnected'] ?? true;
        }

        public function query(
            string $sql,
            array $bindings = [],
        ): array {
            $this->calls[] = ['query', $sql, $bindings];

            return $this->overrides['query'] ?? [['col' => 'val']];
        }

        public function execute(
            string $sql,
            array $bindings = [],
        ): int {
            $this->calls[] = ['execute', $sql, $bindings];

            return $this->overrides['execute'] ?? 1;
        }

        public function prepare(
            string $sql,
        ): StatementInterface {
            $this->calls[] = ['prepare', $sql];

            return $this->overrides['prepare'] ?? throw new RuntimeException('Not implemented');
        }

        public function lastInsertId(): int
        {
            $this->calls[] = 'lastInsertId';

            return $this->overrides['lastInsertId'] ?? 42;
        }

        public function driverName(): string
        {
            $this->calls[] = 'driverName';

            return $this->overrides['driverName'] ?? 'mysql';
        }

        public function supportsReturning(): bool
        {
            $this->calls[] = 'supportsReturning';

            return $this->overrides['supportsReturning'] ?? false;
        }

        public function quoteIdentifier(
            string $identifier,
        ): string {
            $this->calls[] = 'quoteIdentifier';

            return '"' . str_replace('"', '""', $identifier) . '"';
        }

        public function beginTransaction(): void
        {
            $this->calls[] = 'beginTransaction';
        }

        public function commit(): void
        {
            $this->calls[] = 'commit';
        }

        public function rollback(): void
        {
            $this->calls[] = 'rollback';

            if (isset($this->overrides['rollback'])) {
                throw $this->overrides['rollback'];
            }
        }

        public function inTransaction(): bool
        {
            $this->calls[] = 'inTransaction';

            return $this->overrides['inTransaction'] ?? false;
        }

        public function transaction(
            callable $callback,
            int $attempts = 1,
            int|Closure|null $backoff = null,
        ): mixed {
            $this->calls[] = 'transaction';
            $this->transactionAttempts[] = $attempts;
            $this->transactionBackoffs[] = $backoff;

            return $callback();
        }

        public function transactionLevel(): int
        {
            $this->calls[] = 'transactionLevel';

            return $this->overrides['transactionLevel'] ?? 0;
        }

        public function afterCommit(
            callable $callback,
        ): void {
            $this->calls[] = ['afterCommit', $callback];
        }

        public function afterRollback(
            callable $callback,
        ): void {
            $this->calls[] = ['afterRollback', $callback];
        }

        public function runPendingAfterCommitCallbacks(): void
        {
            $this->calls[] = 'runPendingAfterCommitCallbacks';
        }
    };
}

/**
 * A write connection that also answers for transactional DDL, delegating
 * everything else to a plain fake write connection.
 */
function makeTransactionalDdlConnection(
    bool $supportsTransactionalDdl,
): ConnectionInterface&TransactionInterface&TransactionalDdlInterface {
    return new readonly class (makeConnection(), $supportsTransactionalDdl) implements ConnectionInterface, TransactionInterface, TransactionalDdlInterface
    {
        public function __construct(
            private ConnectionInterface&TransactionInterface $inner,
            private bool $supportsTransactionalDdl,
        ) {}

        public function supportsTransactionalDdl(): bool
        {
            return $this->supportsTransactionalDdl;
        }

        public function connect(): void
        {
            $this->inner->connect();
        }

        public function disconnect(): void
        {
            $this->inner->disconnect();
        }

        public function isConnected(): bool
        {
            return $this->inner->isConnected();
        }

        public function query(
            string $sql,
            array $bindings = [],
        ): array {
            return $this->inner->query($sql, $bindings);
        }

        public function execute(
            string $sql,
            array $bindings = [],
        ): int {
            return $this->inner->execute($sql, $bindings);
        }

        public function prepare(
            string $sql,
        ): StatementInterface {
            return $this->inner->prepare($sql);
        }

        public function lastInsertId(): int
        {
            return $this->inner->lastInsertId();
        }

        public function driverName(): string
        {
            return $this->inner->driverName();
        }

        public function supportsReturning(): bool
        {
            return $this->inner->supportsReturning();
        }

        public function quoteIdentifier(
            string $identifier,
        ): string {
            return $this->inner->quoteIdentifier($identifier);
        }

        public function beginTransaction(): void
        {
            $this->inner->beginTransaction();
        }

        public function commit(): void
        {
            $this->inner->commit();
        }

        public function rollback(): void
        {
            $this->inner->rollback();
        }

        public function inTransaction(): bool
        {
            return $this->inner->inTransaction();
        }

        public function transactionLevel(): int
        {
            return $this->inner->transactionLevel();
        }

        public function transaction(
            callable $callback,
            int $attempts = 1,
            int|Closure|null $backoff = null,
        ): mixed {
            return $this->inner->transaction($callback, $attempts, $backoff);
        }

        public function afterCommit(
            callable $callback,
        ): void {
            $this->inner->afterCommit($callback);
        }

        public function afterRollback(
            callable $callback,
        ): void {
            $this->inner->afterRollback($callback);
        }
    };
}

function makeSelector(
    ConnectionInterface $replica,
): ReplicaSelectorInterface {
    return new readonly class ($replica) implements ReplicaSelectorInterface
    {
        public function __construct(private ConnectionInterface $replica) {}

        public function select(
            array $replicas,
        ): ConnectionInterface {
            return $this->replica;
        }
    };
}

/**
 * A selector that returns replicas from the provided list in order (round-robin).
 */
function makeSequentialSelector(): ReplicaSelectorInterface
{
    return new class () implements ReplicaSelectorInterface
    {
        public function select(
            array $replicas,
        ): ConnectionInterface {
            return $replicas[0];
        }
    };
}

/**
 * Create a connection whose query() throws a PDOException.
 */
function makeThrowingConnection(
    string $message = 'connection refused',
): ConnectionInterface&TransactionInterface {
    return new class ($message) implements ConnectionInterface, TransactionInterface
    {
        public array $calls = [];

        public function __construct(private readonly string $message) {}

        public function connect(): void {}

        public function disconnect(): void {}

        public function isConnected(): bool
        {
            return false;
        }

        public function query(
            string $sql,
            array $bindings = [],
        ): array {
            $this->calls[] = ['query', $sql, $bindings];
            throw new PDOException($this->message);
        }

        public function execute(
            string $sql,
            array $bindings = [],
        ): int {
            return 0;
        }

        public function prepare(
            string $sql,
        ): StatementInterface {
            throw new RuntimeException('Not implemented');
        }

        public function lastInsertId(): int
        {
            return 0;
        }

        public function driverName(): string
        {
            return 'mysql';
        }

        public function supportsReturning(): bool
        {
            $this->calls[] = 'supportsReturning';

            return $this->overrides['supportsReturning'] ?? false;
        }

        public function quoteIdentifier(
            string $identifier,
        ): string {
            return '"' . str_replace('"', '""', $identifier) . '"';
        }

        public function beginTransaction(): void {}

        public function commit(): void {}

        public function rollback(): void {}

        public function inTransaction(): bool
        {
            return false;
        }

        public function transaction(
            callable $callback,
            int $attempts = 1,
            int|Closure|null $backoff = null,
        ): mixed {
            return $callback();
        }

        public function transactionLevel(): int
        {
            return 0;
        }

        public function afterCommit(callable $callback): void {}

        public function afterRollback(callable $callback): void {}
    };
}

describe('ReadWriteConnection', function (): void {
    it('routes query to the selected replica', function (): void {
        $write = makeConnection();
        $replica = makeConnection(['query' => [['id' => 1]]]);
        $selector = makeSelector($replica);

        $conn = new ReadWriteConnection($write, [$replica], $selector);
        $result = $conn->query('SELECT 1');

        expect($result)->toBe([['id' => 1]])
            ->and($replica->calls)->toContain(['query', 'SELECT 1', []])
            ->and($write->calls)->toBeEmpty();
    });

    it('routes execute to the write connection', function (): void {
        $write = makeConnection(['execute' => 5]);
        $replica = makeConnection();
        $selector = makeSelector($replica);

        $conn = new ReadWriteConnection($write, [$replica], $selector);
        $affected = $conn->execute('DELETE FROM foo');

        expect($affected)->toBe(5)
            ->and($write->calls)->toContain(['execute', 'DELETE FROM foo', []])
            ->and($replica->calls)->toBeEmpty();
    });

    it('routes prepare to the write connection', function (): void {
        $statement = new class () implements StatementInterface
        {
            public function execute(
                array $bindings = [],
            ): bool {
                return true;
            }

            public function fetchAll(): array
            {
                return [];
            }

            public function fetch(): ?array
            {
                return null;
            }

            public function rowCount(): int
            {
                return 0;
            }
        };

        $write = makeConnection(['prepare' => $statement]);
        $replica = makeConnection();
        $selector = makeSelector($replica);

        $conn = new ReadWriteConnection($write, [$replica], $selector);
        $result = $conn->prepare('INSERT INTO foo VALUES (?)');

        expect($result)->toBe($statement)
            ->and($write->calls)->toContain(['prepare', 'INSERT INTO foo VALUES (?)'])
            ->and($replica->calls)->toBeEmpty();
    });

    it('routes lastInsertId to the write connection', function (): void {
        $write = makeConnection(['lastInsertId' => 99]);
        $replica = makeConnection();
        $selector = makeSelector($replica);

        $conn = new ReadWriteConnection($write, [$replica], $selector);
        $id = $conn->lastInsertId();

        expect($id)->toBe(99)
            ->and($write->calls)->toContain('lastInsertId')
            ->and($replica->calls)->toBeEmpty();
    });

    it(
        'makes the connection sticky to the write connection after a write statement runs through query',
        function (): void {
            $write = makeConnection(['query' => [['id' => 'generated']]]);
            $replica = makeConnection();
            $selector = makeSelector($replica);

            $conn = new ReadWriteConnection($write, [$replica], $selector);
            $conn->query('INSERT INTO tokens (name) VALUES (?) RETURNING id', ['api']);
            $conn->query('SELECT * FROM tokens WHERE id = ?', ['generated']);

            expect($write->calls)->toBe([
                ['query', 'INSERT INTO tokens (name) VALUES (?) RETURNING id', ['api']],
                ['query', 'SELECT * FROM tokens WHERE id = ?', ['generated']],
            ])
                ->and($replica->calls)->toBeEmpty();
        },
    );

    it('delegates supportsReturning to the write connection', function (): void {
        $write = makeConnection(['supportsReturning' => true]);
        $replica = makeConnection();
        $selector = makeSelector($replica);

        $conn = new ReadWriteConnection($write, [$replica], $selector);

        expect($conn->supportsReturning())->toBeTrue()
            ->and($write->calls)->toContain('supportsReturning')
            ->and($replica->calls)->toBeEmpty();
    });

    it(
        'reports transactional DDL support on a read/write connection whose write connection supports it',
        function (): void {
            $replica = makeConnection();
            $conn = new ReadWriteConnection(makeTransactionalDdlConnection(true), [$replica], makeSelector($replica));

            expect($conn)->toBeInstanceOf(TransactionalDdlInterface::class)
                ->and($conn->supportsTransactionalDdl())->toBeTrue()
                ->and($replica->calls)->toBeEmpty();
        },
    );

    it(
        'reports no transactional DDL support on a read/write connection whose write connection does not',
        function (): void {
            $replica = makeConnection();
            $withoutCapability = new ReadWriteConnection(makeConnection(), [$replica], makeSelector($replica));
            $declined = new ReadWriteConnection(
                makeTransactionalDdlConnection(false),
                [$replica],
                makeSelector($replica),
            );

            expect($withoutCapability->supportsTransactionalDdl())->toBeFalse()
                ->and($declined->supportsTransactionalDdl())->toBeFalse()
                ->and($replica->calls)->toBeEmpty();
        },
    );

    it('delegates quoteIdentifier to the write connection', function (): void {
        $write = makeConnection();
        $replica = makeConnection();
        $selector = makeSelector($replica);

        $conn = new ReadWriteConnection($write, [$replica], $selector);

        expect($conn->quoteIdentifier('group'))->toBe('"group"')
            ->and($write->calls)->toContain('quoteIdentifier')
            ->and($replica->calls)->toBeEmpty();
    });

    it('routes connect to the write connection', function (): void {
        $write = makeConnection();
        $replica = makeConnection();
        $selector = makeSelector($replica);

        $conn = new ReadWriteConnection($write, [$replica], $selector);
        $conn->connect();

        expect($write->calls)->toContain('connect')
            ->and($replica->calls)->toBeEmpty();
    });

    it('routes disconnect to the write connection', function (): void {
        $write = makeConnection();
        $replica = makeConnection();
        $selector = makeSelector($replica);

        $conn = new ReadWriteConnection($write, [$replica], $selector);
        $conn->disconnect();

        expect($write->calls)->toContain('disconnect')
            ->and($replica->calls)->toBeEmpty();
    });

    it('delegates isConnected to the write connection', function (): void {
        $write = makeConnection(['isConnected' => false]);
        $replica = makeConnection(['isConnected' => true]);
        $selector = makeSelector($replica);

        $conn = new ReadWriteConnection($write, [$replica], $selector);

        expect($conn->isConnected())->toBeFalse();
    });

    it('uses the replica selector to choose a replica for queries', function (): void {
        $write = makeConnection();
        $replica1 = makeConnection(['query' => [['a' => 1]]]);
        $replica2 = makeConnection(['query' => [['b' => 2]]]);

        $alwaysSecond = new class ($replica2) implements ReplicaSelectorInterface
        {
            public int $selectCallCount = 0;

            public function __construct(private readonly ConnectionInterface $replica) {}

            public function select(
                array $replicas,
            ): ConnectionInterface {
                $this->selectCallCount++;

                return $this->replica;
            }
        };

        $conn = new ReadWriteConnection($write, [$replica1, $replica2], $alwaysSecond);
        $result = $conn->query('SELECT 1');

        expect($result)->toBe([['b' => 2]])
            ->and($alwaysSecond->selectCallCount)->toBe(1)
            ->and($replica1->calls)->toBeEmpty();
    });

    it('passes query params to the replica', function (): void {
        $write = makeConnection();
        $replica = makeConnection(['query' => [['id' => 7]]]);
        $selector = makeSelector($replica);

        $conn = new ReadWriteConnection($write, [$replica], $selector);
        $conn->query('SELECT * FROM users WHERE id = ?', [7]);

        expect($replica->calls)->toContain(['query', 'SELECT * FROM users WHERE id = ?', [7]]);
    });

    it('passes execute params to the write connection', function (): void {
        $write = makeConnection(['execute' => 1]);
        $replica = makeConnection();
        $selector = makeSelector($replica);

        $conn = new ReadWriteConnection($write, [$replica], $selector);
        $conn->execute('UPDATE users SET name = ? WHERE id = ?', ['Alice', 3]);

        expect($write->calls)->toContain(['execute', 'UPDATE users SET name = ? WHERE id = ?', ['Alice', 3]]);
    });

    it('delegates beginTransaction to the write connection', function (): void {
        $write = makeConnection();
        $replica = makeConnection();
        $selector = makeSelector($replica);

        $conn = new ReadWriteConnection($write, [$replica], $selector);
        $conn->beginTransaction();

        expect($write->calls)->toContain('beginTransaction')
            ->and($replica->calls)->toBeEmpty();
    });

    it('delegates commit to the write connection', function (): void {
        $write = makeConnection();
        $replica = makeConnection();
        $selector = makeSelector($replica);

        $conn = new ReadWriteConnection($write, [$replica], $selector);
        $conn->commit();

        expect($write->calls)->toContain('commit')
            ->and($replica->calls)->toBeEmpty();
    });

    it('delegates rollback to the write connection', function (): void {
        $write = makeConnection();
        $replica = makeConnection();
        $selector = makeSelector($replica);

        $conn = new ReadWriteConnection($write, [$replica], $selector);
        $conn->rollback();

        expect($write->calls)->toContain('rollback')
            ->and($replica->calls)->toBeEmpty();
    });

    it('delegates inTransaction to the write connection', function (): void {
        $write = makeConnection(['inTransaction' => true]);
        $replica = makeConnection();
        $selector = makeSelector($replica);

        $conn = new ReadWriteConnection($write, [$replica], $selector);

        expect($conn->inTransaction())->toBeTrue()
            ->and($write->calls)->toContain('inTransaction')
            ->and($replica->calls)->toBeEmpty();
    });

    it('delegates transaction to the write connection', function (): void {
        $write = makeConnection();
        $replica = makeConnection();
        $selector = makeSelector($replica);

        $conn = new ReadWriteConnection($write, [$replica], $selector);
        $conn->transaction(function (): void {});

        expect($write->calls)->toContain('transaction')
            ->and($replica->calls)->toBeEmpty();
    });

    it('delegates the attempts argument of transaction to the write connection', function (): void {
        $write = makeConnection();
        $replica = makeConnection();

        $conn = new ReadWriteConnection($write, [$replica], makeSelector($replica));
        $conn->transaction(function (): void {}, attempts: 3);

        expect($write->transactionAttempts)->toBe([3])
            ->and($replica->calls)->toBeEmpty();
    });

    it('passes the backoff to the write connection', function (): void {
        $write = makeConnection();
        $replica = makeConnection();
        $custom = fn (int $attempt): int => $attempt * 10;

        $conn = new ReadWriteConnection($write, [$replica], makeSelector($replica));
        $conn->transaction(function (): void {}, attempts: 3, backoff: 25);
        $conn->transaction(function (): void {}, attempts: 3, backoff: $custom);
        $conn->transaction(function (): void {});

        expect($write->transactionBackoffs)->toBe([25, $custom, null])
            ->and($replica->calls)->toBeEmpty();
    });

    it('delegates one attempt by default', function (): void {
        $write = makeConnection();
        $replica = makeConnection();

        $conn = new ReadWriteConnection($write, [$replica], makeSelector($replica));
        $conn->transaction(function (): void {});

        expect($write->transactionAttempts)->toBe([1]);
    });

    it('delegates transactionLevel to the write connection', function (): void {
        $write = makeConnection(['transactionLevel' => 2]);
        $replica = makeConnection();

        $conn = new ReadWriteConnection($write, [$replica], makeSelector($replica));

        expect($conn->transactionLevel())->toBe(2)
            ->and($write->calls)->toContain('transactionLevel')
            ->and($replica->calls)->toBeEmpty();
    });

    it('delegates afterCommit to the write connection', function (): void {
        $write = makeConnection();
        $replica = makeConnection();
        $callback = function (): void {};

        $conn = new ReadWriteConnection($write, [$replica], makeSelector($replica));
        $conn->afterCommit($callback);

        expect($write->calls)->toContain(['afterCommit', $callback])
            ->and($replica->calls)->toBeEmpty();
    });

    it('delegates pending after-commit callbacks to the write connection', function (): void {
        $write = makeConnection();
        $replica = makeConnection();

        $conn = new ReadWriteConnection($write, [$replica], makeSelector($replica));
        $conn->runPendingAfterCommitCallbacks();

        expect($write->calls)->toContain('runPendingAfterCommitCallbacks')
            ->and($conn)->toBeInstanceOf(PendingAfterCommitInterface::class)
            ->and($replica->calls)->toBeEmpty();
    });

    it('throws when the write connection cannot run pending after-commit callbacks', function (): void {
        $replica = makeConnection();

        $conn = new ReadWriteConnection(makeThrowingConnection(), [$replica], makeSelector($replica));

        expect(fn () => $conn->runPendingAfterCommitCallbacks())
            ->toThrow(TransactionException::class, 'cannot run pending after-commit callbacks');
    });

    it('delegates afterRollback to the write connection', function (): void {
        $write = makeConnection();
        $replica = makeConnection();
        $callback = function (): void {};

        $conn = new ReadWriteConnection($write, [$replica], makeSelector($replica));
        $conn->afterRollback($callback);

        expect($write->calls)->toContain(['afterRollback', $callback])
            ->and($replica->calls)->toBeEmpty();
    });

    it(
        'keeps reads on the write connection after a nested transaction returns inside an outer transaction',
        function (): void {
            $write = makeConnection(['query' => [['id' => 1]]]);
            $replica = makeConnection(['query' => [['id' => 99]]]);

            $conn = new ReadWriteConnection($write, [$replica], makeSelector($replica));
            $result = $conn->transaction(function () use ($conn): array {
                $conn->transaction(function (): void {});

                return $conn->query('SELECT 1');
            });

            expect($result)->toBe([['id' => 1]])
                ->and($replica->calls)->toBeEmpty();
        },
    );

    it('returns the transaction callback result', function (): void {
        $write = makeConnection();
        $replica = makeConnection();
        $selector = makeSelector($replica);

        $conn = new ReadWriteConnection($write, [$replica], $selector);
        $result = $conn->transaction(fn () => 'expected-value');

        expect($result)->toBe('expected-value');
    });

    it('routes query to write when sticky after execute', function (): void {
        $write = makeConnection(['query' => [['id' => 1]]]);
        $replica = makeConnection(['query' => [['id' => 99]]]);
        $selector = makeSelector($replica);

        $conn = new ReadWriteConnection($write, [$replica], $selector);
        $conn->execute('INSERT INTO foo VALUES (1)');
        $result = $conn->query('SELECT 1');

        expect($result)->toBe([['id' => 1]])
            ->and($write->calls)->toContain(['query', 'SELECT 1', []])
            ->and($replica->calls)->not->toContain(['query', 'SELECT 1', []]);
    });

    it('routes query to write when sticky after beginTransaction', function (): void {
        $write = makeConnection(['query' => [['id' => 1]]]);
        $replica = makeConnection(['query' => [['id' => 99]]]);
        $selector = makeSelector($replica);

        $conn = new ReadWriteConnection($write, [$replica], $selector);
        $conn->beginTransaction();
        $result = $conn->query('SELECT 1');

        expect($result)->toBe([['id' => 1]])
            ->and($write->calls)->toContain(['query', 'SELECT 1', []])
            ->and($replica->calls)->not->toContain(['query', 'SELECT 1', []]);
    });

    it('routes query to replica when not sticky', function (): void {
        $write = makeConnection(['query' => [['id' => 1]]]);
        $replica = makeConnection(['query' => [['id' => 99]]]);
        $selector = makeSelector($replica);

        $conn = new ReadWriteConnection($write, [$replica], $selector);
        $result = $conn->query('SELECT 1');

        expect($result)->toBe([['id' => 99]])
            ->and($replica->calls)->toContain(['query', 'SELECT 1', []])
            ->and($write->calls)->not->toContain(['query', 'SELECT 1', []]);
    });

    it('resets sticky state when resetStickyState is called', function (): void {
        $write = makeConnection(['query' => [['id' => 1]]]);
        $replica = makeConnection(['query' => [['id' => 99]]]);
        $selector = makeSelector($replica);

        $conn = new ReadWriteConnection($write, [$replica], $selector);
        $conn->execute('INSERT INTO foo VALUES (1)');
        $conn->resetStickyState();
        $result = $conn->query('SELECT 1');

        expect($result)->toBe([['id' => 99]])
            ->and($replica->calls)->toContain(['query', 'SELECT 1', []]);
    });

    it('stays sticky after commit', function (): void {
        $write = makeConnection(['query' => [['id' => 1]]]);
        $replica = makeConnection(['query' => [['id' => 99]]]);
        $selector = makeSelector($replica);

        $conn = new ReadWriteConnection($write, [$replica], $selector);
        $conn->beginTransaction();
        $conn->commit();
        $result = $conn->query('SELECT 1');

        expect($result)->toBe([['id' => 1]])
            ->and($write->calls)->toContain(['query', 'SELECT 1', []])
            ->and($replica->calls)->not->toContain(['query', 'SELECT 1', []]);
    });

    it('stays sticky after rollback', function (): void {
        $write = makeConnection(['query' => [['id' => 1]]]);
        $replica = makeConnection(['query' => [['id' => 99]]]);
        $selector = makeSelector($replica);

        $conn = new ReadWriteConnection($write, [$replica], $selector);
        $conn->beginTransaction();
        $conn->rollback();
        $result = $conn->query('SELECT 1');

        expect($result)->toBe([['id' => 1]])
            ->and($write->calls)->toContain(['query', 'SELECT 1', []])
            ->and($replica->calls)->not->toContain(['query', 'SELECT 1', []]);
    });

    it('provides a public resetStickyState method', function (): void {
        $write = makeConnection();
        $replica = makeConnection();
        $selector = makeSelector($replica);

        $conn = new ReadWriteConnection($write, [$replica], $selector);

        $reflection = new ReflectionMethod($conn, 'resetStickyState');

        expect(method_exists($conn, 'resetStickyState'))->toBeTrue()
            ->and($reflection->isPublic())->toBeTrue();
    });

    it('implements the resettable contract', function (): void {
        $write = makeConnection();
        $replica = makeConnection();
        $selector = makeSelector($replica);

        $conn = new ReadWriteConnection($write, [$replica], $selector);

        expect($conn)->toBeInstanceOf(ResettableInterface::class);
    });

    it('clears sticky write state when reset', function (): void {
        $write = makeConnection(['query' => [['id' => 1]]]);
        $replica = makeConnection(['query' => [['id' => 99]]]);
        $selector = makeSelector($replica);

        $conn = new ReadWriteConnection($write, [$replica], $selector);
        $conn->execute('INSERT INTO foo VALUES (1)');
        $conn->reset();
        $result = $conn->query('SELECT 1');

        expect($result)->toBe([['id' => 99]]);
    });

    it('routes reads to a replica again after reset', function (): void {
        $write = makeConnection(['query' => [['id' => 1]]]);
        $replica = makeConnection(['query' => [['id' => 99]]]);
        $selector = makeSelector($replica);

        $conn = new ReadWriteConnection($write, [$replica], $selector);
        $conn->beginTransaction();
        $conn->reset();
        $result = $conn->query('SELECT 1');

        expect($result)->toBe([['id' => 99]])
            ->and($replica->calls)->toContain(['query', 'SELECT 1', []])
            ->and($write->calls)->not->toContain(['query', 'SELECT 1', []]);
    });

    it('rolls back an open transaction when reset', function (): void {
        $write = makeConnection(['transactionLevel' => 1]);
        $replica = makeConnection();
        $selector = makeSelector($replica);

        $conn = new ReadWriteConnection($write, [$replica], $selector);
        $conn->reset();

        expect($write->calls)->toContain('rollback');
    });

    it('rolls back every open level when reset inside nested transactions', function (): void {
        $write = makeConnection(['transactionLevel' => 3]);
        $replica = makeConnection();

        $conn = new ReadWriteConnection($write, [$replica], makeSelector($replica));
        $conn->reset();

        expect(array_count_values(array_filter($write->calls, 'is_string'))['rollback'])->toBe(3);
    });

    it('delegates reset to a resettable write connection', function (): void {
        $write = $this->createMockForIntersectionOfInterfaces([
            ConnectionInterface::class,
            TransactionInterface::class,
            ResettableInterface::class,
        ]);
        $write->expects($this->once())->method('reset');
        $write->expects($this->never())->method('rollback');
        $replica = makeConnection();

        $conn = new ReadWriteConnection($write, [$replica], makeSelector($replica));
        $conn->reset();
    });

    it('does not attempt a rollback when no transaction is open', function (): void {
        $write = makeConnection(['transactionLevel' => 0]);
        $replica = makeConnection();
        $selector = makeSelector($replica);

        $conn = new ReadWriteConnection($write, [$replica], $selector);
        $conn->reset();

        expect($write->calls)->not->toContain('rollback');
    });

    it('still clears sticky write state when reset', function (): void {
        $write = makeConnection([
            'transactionLevel' => 1,
            'query' => [['id' => 99]],
        ]);
        $replica = makeConnection(['query' => [['id' => 99]]]);
        $selector = makeSelector($replica);

        $conn = new ReadWriteConnection($write, [$replica], $selector);
        $conn->execute('INSERT INTO foo VALUES (1)');
        $conn->reset();
        $result = $conn->query('SELECT 1');

        expect($result)->toBe([['id' => 99]])
            ->and($replica->calls)->toContain(['query', 'SELECT 1', []]);
    });

    it('clears sticky write state even when the rollback fails', function (): void {
        $write = makeConnection([
            'transactionLevel' => 1,
            'rollback' => new PDOException('rollback failed'),
            'query' => [['id' => 1]],
        ]);
        $replica = makeConnection(['query' => [['id' => 99]]]);
        $selector = makeSelector($replica);

        $conn = new ReadWriteConnection($write, [$replica], $selector);
        $conn->execute('INSERT INTO foo VALUES (1)');

        expect(fn () => $conn->reset())->toThrow(PDOException::class, 'rollback failed');

        $result = $conn->query('SELECT 1');

        expect($result)->toBe([['id' => 99]])
            ->and($replica->calls)->toContain(['query', 'SELECT 1', []]);
    });

    it('keeps the existing reset sticky state method available', function (): void {
        $write = makeConnection(['query' => [['id' => 1]]]);
        $replica = makeConnection(['query' => [['id' => 99]]]);
        $selector = makeSelector($replica);

        $conn = new ReadWriteConnection($write, [$replica], $selector);
        $conn->execute('INSERT INTO foo VALUES (1)');
        $conn->resetStickyState();
        $result = $conn->query('SELECT 1');

        expect($result)->toBe([['id' => 99]]);
    });

    it('tries the next replica when first replica throws PDOException', function (): void {
        $write = makeConnection();
        $failing = makeThrowingConnection('replica1 down');
        $good = makeConnection(['query' => [['id' => 2]]]);
        $selector = makeSequentialSelector();

        $conn = new ReadWriteConnection($write, [$failing, $good], $selector);
        $result = $conn->query('SELECT 1');

        expect($result)->toBe([['id' => 2]])
            ->and($failing->calls)->toContain(['query', 'SELECT 1', []])
            ->and($good->calls)->toContain(['query', 'SELECT 1', []]);
    });

    it('throws ReadException when all replicas fail', function (): void {
        $write = makeConnection();
        $failing1 = makeThrowingConnection('replica1 down');
        $failing2 = makeThrowingConnection('replica2 down');
        $selector = makeSequentialSelector();

        $conn = new ReadWriteConnection($write, [$failing1, $failing2], $selector);

        expect(fn () => $conn->query('SELECT 1'))->toThrow(ReadException::class);
    });

    it('succeeds on second replica after first replica fails', function (): void {
        $write = makeConnection();
        $failing = makeThrowingConnection('first down');
        $good = makeConnection(['query' => [['name' => 'alice']]]);
        $selector = makeSequentialSelector();

        $conn = new ReadWriteConnection($write, [$failing, $good], $selector);
        $result = $conn->query('SELECT name FROM users', []);

        expect($result)->toBe([['name' => 'alice']])
            ->and($good->calls)->toContain(['query', 'SELECT name FROM users', []]);
    });

    it('bubbles non-PDOException errors immediately without trying other replicas', function (): void {
        $write = makeConnection();
        $badQuery = new class () implements ConnectionInterface, TransactionInterface
        {
            public array $calls = [];

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
                $this->calls[] = ['query', $sql, $bindings];
                throw new InvalidArgumentException('SQL syntax error');
            }

            public function execute(
                string $sql,
                array $bindings = [],
            ): int {
                return 0;
            }

            public function prepare(
                string $sql,
            ): StatementInterface {
                throw new RuntimeException('Not implemented');
            }

            public function lastInsertId(): int
            {
                return 0;
            }

            public function driverName(): string
            {
                return 'mysql';
            }

            public function supportsReturning(): bool
            {
                return false;
            }

            public function quoteIdentifier(
                string $identifier,
            ): string {
                return '"' . str_replace('"', '""', $identifier) . '"';
            }

            public function beginTransaction(): void {}

            public function commit(): void {}

            public function rollback(): void {}

            public function inTransaction(): bool
            {
                return false;
            }

            public function transaction(
                callable $callback,
                int $attempts = 1,
                int|Closure|null $backoff = null,
            ): mixed {
                return $callback();
            }

            public function transactionLevel(): int
            {
                return 0;
            }

            public function afterCommit(callable $callback): void {}

            public function afterRollback(callable $callback): void {}
        };

        $good = makeConnection(['query' => [['id' => 1]]]);
        $selector = makeSequentialSelector();

        $conn = new ReadWriteConnection($write, [$badQuery, $good], $selector);

        expect(fn () => $conn->query('INVALID SQL'))
            ->toThrow(InvalidArgumentException::class, 'SQL syntax error')
            ->and($badQuery->calls)->toContain(['query', 'INVALID SQL', []])
            ->and($good->calls)->toBeEmpty();
    });

    it('includes failure messages in ReadException', function (): void {
        $write = makeConnection();
        $failing1 = makeThrowingConnection('replica1 timed out');
        $failing2 = makeThrowingConnection('replica2 refused');
        $selector = makeSequentialSelector();

        $conn = new ReadWriteConnection($write, [$failing1, $failing2], $selector);

        try {
            $conn->query('SELECT 1');
            fail('Expected ReadException to be thrown');
        } catch (ReadException $e) {
            expect($e->getMessage())->toContain('replica1 timed out')
                ->and($e->getMessage())->toContain('replica2 refused');
        }
    });

    it(
        'routes query() calls executed inside a transaction() callback to the primary, not a replica',
        function (): void {
            $write = makeConnection(['query' => [['id' => 1]]]);
            $replica = makeConnection(['query' => [['id' => 99]]]);
            $selector = makeSelector($replica);

            $conn = new ReadWriteConnection($write, [$replica], $selector);
            $result = null;
            $conn->transaction(function () use ($conn, &$result): void {
                $result = $conn->query('SELECT 1');
            });

            expect($result)->toBe([['id' => 1]])
                ->and($write->calls)->toContain(['query', 'SELECT 1', []])
                ->and($replica->calls)->not->toContain(['query', 'SELECT 1', []]);
        },
    );

    it('resets sticky-write state after the transaction() callback completes', function (): void {
        $write = makeConnection(['query' => [['id' => 1]]]);
        $replica = makeConnection(['query' => [['id' => 99]]]);
        $selector = makeSelector($replica);

        $conn = new ReadWriteConnection($write, [$replica], $selector);
        $conn->transaction(function (): void {});
        $result = $conn->query('SELECT 1');

        expect($result)->toBe([['id' => 99]])
            ->and($replica->calls)->toContain(['query', 'SELECT 1', []])
            ->and($write->calls)->not->toContain(['query', 'SELECT 1', []]);
    });

    it('routes an INSERT ... RETURNING statement issued via query() to the primary', function (): void {
        $write = makeConnection(['query' => [['id' => 1]]]);
        $replica = makeConnection(['query' => [['id' => 99]]]);
        $selector = makeSelector($replica);

        $conn = new ReadWriteConnection($write, [$replica], $selector);
        $result = $conn->query('INSERT INTO users (name) VALUES (?) RETURNING id', ['Alice']);

        expect($result)->toBe([['id' => 1]])
            ->and($write->calls)->toContain(['query', 'INSERT INTO users (name) VALUES (?) RETURNING id', ['Alice']])
            ->and($replica->calls)->not->toContain(
                ['query', 'INSERT INTO users (name) VALUES (?) RETURNING id', ['Alice']],
            );
    });

    it('continues routing plain SELECTs to a replica when not in a transaction and not sticky', function (): void {
        $write = makeConnection(['query' => [['id' => 1]]]);
        $replica = makeConnection(['query' => [['id' => 99]]]);
        $selector = makeSelector($replica);

        $conn = new ReadWriteConnection($write, [$replica], $selector);
        $result = $conn->query('SELECT * FROM users');

        expect($result)->toBe([['id' => 99]])
            ->and($replica->calls)->toContain(['query', 'SELECT * FROM users', []])
            ->and($write->calls)->not->toContain(['query', 'SELECT * FROM users', []]);
    });

    it(
        'routes a write statement to the primary despite leading whitespace, comments or lowercase keywords',
        function (): void {
            $write = makeConnection(['query' => [['id' => 1]]]);
            $replica = makeConnection(['query' => [['id' => 99]]]);
            $selector = makeSelector($replica);

            $conn = new ReadWriteConnection($write, [$replica], $selector);

            // Leading whitespace
            $result1 = $conn->query('   INSERT INTO foo VALUES (1) RETURNING id');
            // Leading line comment
            $result2 = $conn->query("-- a comment\nUPDATE foo SET x = 1 RETURNING id");
            // Leading block comment
            $result3 = $conn->query('/* note */ DELETE FROM foo WHERE id = 1 RETURNING id');
            // Uppercase
            $result4 = $conn->query('insert into bar values (2) returning id');

            expect($result1)->toBe([['id' => 1]])
                ->and($result2)->toBe([['id' => 1]])
                ->and($result3)->toBe([['id' => 1]])
                ->and($result4)->toBe([['id' => 1]])
                ->and($replica->calls)->toBeEmpty();
        },
    );

    it('resets sticky-write state even when the transaction() callback throws', function (): void {
        $write = makeConnection(['query' => [['id' => 1]]]);
        $replica = makeConnection(['query' => [['id' => 99]]]);
        $selector = makeSelector($replica);

        $conn = new ReadWriteConnection($write, [$replica], $selector);

        try {
            $conn->transaction(function (): void {
                throw new RuntimeException('rollback!');
            });
        } catch (RuntimeException) {
            // expected
        }

        $result = $conn->query('SELECT 1');

        expect($result)->toBe([['id' => 99]])
            ->and($replica->calls)->toContain(['query', 'SELECT 1', []])
            ->and($write->calls)->not->toContain(['query', 'SELECT 1', []]);
    });
});

describe('primary routing for stale-sensitive reads', function (): void {
    it('implements PrimaryReadInterface', function (): void {
        $conn = new ReadWriteConnection(makeConnection(), [makeConnection()], makeSelector(makeConnection()));

        expect($conn)->toBeInstanceOf(PrimaryReadInterface::class);
    })->issue(390);

    it('routes reads inside onPrimary() to the write connection and returns the callback result', function (): void {
        $write = makeConnection(['query' => [['id' => 1]]]);
        $replica = makeConnection(['query' => [['id' => 99]]]);
        $conn = new ReadWriteConnection($write, [$replica], makeSelector($replica));

        $result = $conn->onPrimary(fn (): array => $conn->query('SELECT payload FROM sessions WHERE id = ?', ['abc']));

        expect($result)->toBe([['id' => 1]])
            ->and($write->calls)->toContain(['query', 'SELECT payload FROM sessions WHERE id = ?', ['abc']])
            ->and($replica->calls)->toBeEmpty();
    })->issue(390);

    it('sends reads back to a replica after an onPrimary() callback that wrote nothing', function (): void {
        $write = makeConnection(['query' => [['id' => 1]]]);
        $replica = makeConnection(['query' => [['id' => 99]]]);
        $conn = new ReadWriteConnection($write, [$replica], makeSelector($replica));

        $conn->onPrimary(fn (): array => $conn->query('SELECT 1'));

        expect($conn->query('SELECT 2'))->toBe([['id' => 99]])
            ->and($replica->calls)->toBe([['query', 'SELECT 2', []]]);
    })->issue(390);

    it('keeps reads on the write connection after an onPrimary() callback that wrote', function (): void {
        $write = makeConnection(['query' => [['id' => 1]]]);
        $replica = makeConnection(['query' => [['id' => 99]]]);
        $conn = new ReadWriteConnection($write, [$replica], makeSelector($replica));

        $conn->onPrimary(fn (): int => $conn->execute('DELETE FROM sessions WHERE id = ?', ['abc']));

        expect($conn->query('SELECT 2'))->toBe([['id' => 1]])
            ->and($replica->calls)->toBeEmpty();
    })->issue(390);

    it('restores the sticky flag after onPrimary() even when the callback throws', function (): void {
        $write = makeConnection(['query' => [['id' => 1]]]);
        $replica = makeConnection(['query' => [['id' => 99]]]);
        $conn = new ReadWriteConnection($write, [$replica], makeSelector($replica));

        try {
            $conn->onPrimary(function (): never {
                throw new RuntimeException('boom');
            });
        } catch (RuntimeException) {
            // expected
        }

        expect($conn->query('SELECT 1'))->toBe([['id' => 99]]);
    })->issue(390);

    it('keeps reads on the write connection after a transaction() that executed a write', function (): void {
        $write = makeConnection(['query' => [['id' => 1]]]);
        $replica = makeConnection(['query' => [['id' => 99]]]);
        $conn = new ReadWriteConnection($write, [$replica], makeSelector($replica));

        $conn->transaction(function () use ($conn): void {
            $conn->execute('DELETE FROM sessions WHERE id = ?', ['abc']);
        });

        expect($conn->query('SELECT 1'))->toBe([['id' => 1]])
            ->and($replica->calls)->toBeEmpty();
    })->issue(390);

    it('keeps reads on the write connection after a transaction() that wrote through query()', function (): void {
        $write = makeConnection(['query' => [['id' => 1]]]);
        $replica = makeConnection(['query' => [['id' => 99]]]);
        $conn = new ReadWriteConnection($write, [$replica], makeSelector($replica));

        $conn->transaction(function () use ($conn): void {
            $conn->query('INSERT INTO tokens (name) VALUES (?) RETURNING id', ['api']);
        });

        expect($conn->query('SELECT 1'))->toBe([['id' => 1]])
            ->and($replica->calls)->toBeEmpty();
    })->issue(390);

    it('keeps reads on the write connection after a nested transaction() wrote', function (): void {
        $write = makeConnection(['query' => [['id' => 1]]]);
        $replica = makeConnection(['query' => [['id' => 99]]]);
        $conn = new ReadWriteConnection($write, [$replica], makeSelector($replica));

        $conn->transaction(function () use ($conn): void {
            $conn->transaction(function () use ($conn): void {
                $conn->execute('UPDATE users SET name = ?', ['x']);
            });
        });

        expect($conn->query('SELECT 1'))->toBe([['id' => 1]])
            ->and($replica->calls)->toBeEmpty();
    })->issue(390);

    it('routes reads to a replica again after resetStickyState() following a write', function (): void {
        $write = makeConnection(['query' => [['id' => 1]]]);
        $replica = makeConnection(['query' => [['id' => 99]]]);
        $conn = new ReadWriteConnection($write, [$replica], makeSelector($replica));

        $conn->transaction(function () use ($conn): void {
            $conn->execute('DELETE FROM sessions');
        });
        $conn->resetStickyState();
        $conn->transaction(function (): void {});

        expect($conn->query('SELECT 1'))->toBe([['id' => 99]]);
    })->issue(390);

    it('makes the connection sticky when a write statement is prepared', function (): void {
        $statement = $this->createStub(StatementInterface::class);
        $write = makeConnection(['prepare' => $statement, 'query' => [['id' => 1]]]);
        $replica = makeConnection(['query' => [['id' => 99]]]);
        $conn = new ReadWriteConnection($write, [$replica], makeSelector($replica));

        $conn->prepare('DELETE FROM sessions WHERE id = ?');

        expect($conn->query('SELECT 1'))->toBe([['id' => 1]])
            ->and($replica->calls)->toBeEmpty();
    })->issue(390);

    it('routes statements that may write to the write connection', function (string $sql): void {
        $write = makeConnection(['query' => [['id' => 1]]]);
        $replica = makeConnection(['query' => [['id' => 99]]]);
        $conn = new ReadWriteConnection($write, [$replica], makeSelector($replica));

        expect($conn->query($sql))->toBe([['id' => 1]])
            ->and($write->calls)->toContain(['query', $sql, []])
            ->and($replica->calls)->toBeEmpty();
    })->with([
        'WITH ... DELETE' => ['WITH old AS (SELECT id FROM sessions) DELETE FROM sessions USING old RETURNING id'],
        'lowercase with ... update' => ['with t as (select 1) update users set x = 1 returning id'],
        'MERGE' => ['MERGE INTO users u USING incoming i ON u.id = i.id WHEN MATCHED THEN UPDATE SET name = i.name'],
        'REPLACE' => ['REPLACE INTO sessions (id, payload) VALUES (1, 2)'],
        'CALL' => ['CALL purge_sessions()'],
        'two leading line comments' => ["-- first\n-- second\nDELETE FROM sessions RETURNING id"],
        'two leading block comments' => ['/* a */ /* b */ UPDATE users SET x = 1 RETURNING id'],
        'mixed comments' => ["/* a */\n-- b\n# c\nINSERT INTO t VALUES (1) RETURNING id"],
        'multi-line block comment' => ["/*\n * note\n */\nDELETE FROM t RETURNING id"],
    ])->issue(390);

    it('routes reads behind leading comments to a replica', function (string $sql): void {
        $write = makeConnection(['query' => [['id' => 1]]]);
        $replica = makeConnection(['query' => [['id' => 99]]]);
        $conn = new ReadWriteConnection($write, [$replica], makeSelector($replica));

        expect($conn->query($sql))->toBe([['id' => 99]])
            ->and($write->calls)->not->toContain(['query', $sql, []]);
    })->with([
        'plain SELECT' => ['SELECT * FROM users'],
        'SELECT behind comments' => ["/* a */ -- b\nSELECT * FROM users"],
        'SELECT of an updated_at column' => ['SELECT updated_at FROM users'],
        'SHOW' => ['SHOW TABLES'],
    ])->issue(390);
});
