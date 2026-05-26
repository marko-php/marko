<?php

declare(strict_types=1);

use Marko\Database\Connection\ConnectionInterface;
use Marko\Database\Connection\StatementInterface;
use Marko\Database\Connection\TransactionInterface;
use Marko\Database\ReadWrite\Connection\ReadWriteConnection;
use Marko\Database\ReadWrite\Exceptions\ReadException;
use Marko\Database\ReadWrite\Replica\ReplicaSelectorInterface;

function makeConnection(array $overrides = []): ConnectionInterface&TransactionInterface
{
    return new class ($overrides) implements ConnectionInterface, TransactionInterface
    {
        public array $calls = [];

        public function __construct(private array $overrides) {}

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

        public function prepare(string $sql): StatementInterface
        {
            $this->calls[] = ['prepare', $sql];

            return $this->overrides['prepare'] ?? throw new RuntimeException('Not implemented');
        }

        public function lastInsertId(): int
        {
            $this->calls[] = 'lastInsertId';

            return $this->overrides['lastInsertId'] ?? 42;
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
        }

        public function inTransaction(): bool
        {
            $this->calls[] = 'inTransaction';

            return $this->overrides['inTransaction'] ?? false;
        }

        public function transaction(callable $callback): mixed
        {
            $this->calls[] = 'transaction';

            return $callback();
        }
    };
}

function makeSelector(ConnectionInterface $replica): ReplicaSelectorInterface
{
    return new class ($replica) implements ReplicaSelectorInterface
    {
        public function __construct(private ConnectionInterface $replica) {}

        public function select(array $replicas): ConnectionInterface
        {
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
        public function select(array $replicas): ConnectionInterface
        {
            return $replicas[0];
        }
    };
}

/**
 * Create a connection whose query() throws a PDOException.
 */
function makeThrowingConnection(string $message = 'connection refused'): ConnectionInterface&TransactionInterface
{
    return new class ($message) implements ConnectionInterface, TransactionInterface
    {
        public array $calls = [];

        public function __construct(private string $message) {}

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

        public function prepare(string $sql): StatementInterface
        {
            throw new RuntimeException('Not implemented');
        }

        public function lastInsertId(): int
        {
            return 0;
        }

        public function beginTransaction(): void {}

        public function commit(): void {}

        public function rollback(): void {}

        public function inTransaction(): bool
        {
            return false;
        }

        public function transaction(callable $callback): mixed
        {
            return $callback();
        }
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
            public function execute(array $bindings = []): bool
            {
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

            public function __construct(private ConnectionInterface $replica) {}

            public function select(array $replicas): ConnectionInterface
            {
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

            public function prepare(string $sql): StatementInterface
            {
                throw new RuntimeException('Not implemented');
            }

            public function lastInsertId(): int
            {
                return 0;
            }

            public function beginTransaction(): void {}

            public function commit(): void {}

            public function rollback(): void {}

            public function inTransaction(): bool
            {
                return false;
            }

            public function transaction(callable $callback): mixed
            {
                return $callback();
            }
        };

        $good = makeConnection(['query' => [['id' => 1]]]);
        $selector = makeSequentialSelector();

        $conn = new ReadWriteConnection($write, [$badQuery, $good], $selector);

        expect(fn () => $conn->query('INVALID SQL'))
            ->toThrow(InvalidArgumentException::class, 'SQL syntax error');

        expect($badQuery->calls)->toContain(['query', 'INVALID SQL', []])
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
});
