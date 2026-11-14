<?php

declare(strict_types=1);

namespace Marko\Database\Tests\Repository;

use Closure;
use Marko\Core\Event\Event;
use Marko\Core\Event\EventDispatcherInterface;
use Marko\Database\Attributes\Column;
use Marko\Database\Attributes\HasMany;
use Marko\Database\Attributes\Table;
use Marko\Database\Connection\ConnectionInterface;
use Marko\Database\Connection\StatementInterface;
use Marko\Database\Connection\TransactionInterface;
use Marko\Database\Entity\Entity;
use Marko\Database\Entity\EntityHydrator;
use Marko\Database\Entity\EntityMetadataFactory;
use Marko\Database\Events\EntityCreated;
use Marko\Database\Events\EntityCreating;
use Marko\Database\Exceptions\BatchInsertException;
use Marko\Database\Exceptions\RepositoryException;
use Marko\Database\Repository\Repository;
use RuntimeException;
use Throwable;

// ── Fixtures ───────────────────────────────────────────────────────────────────

#[Table('batch_users')]
class BatchUser extends Entity
{
    #[Column(primaryKey: true, autoIncrement: true)]
    public ?int $id = null;

    #[Column]
    public string $name = '';

    #[Column]
    public string $email = '';
}

#[Table('batch_products')]
class BatchProduct extends Entity
{
    #[Column(primaryKey: true, autoIncrement: true)]
    public ?int $id = null;

    #[Column]
    public string $sku = '';
}

#[Table('batch_string_pk')]
class BatchStringPk extends Entity
{
    #[Column(primaryKey: true)]
    public ?string $uuid = null;

    #[Column]
    public string $label = '';
}

#[Table('batch_with_tags')]
class BatchEntityWithRelationship extends Entity
{
    #[Column(primaryKey: true, autoIncrement: true)]
    public ?int $id = null;

    #[Column]
    public string $title = '';

    #[HasMany(entityClass: BatchUser::class, foreignKey: 'batch_entity_id')]
    public array $tags = [];
}

class BatchUserRepository extends Repository
{
    protected const string ENTITY_CLASS = BatchUser::class;
}

class BatchProductRepository extends Repository
{
    protected const string ENTITY_CLASS = BatchProduct::class;
}

class BatchStringPkRepository extends Repository
{
    protected const string ENTITY_CLASS = BatchStringPk::class;
}

class BatchWithRelationshipRepository extends Repository
{
    protected const string ENTITY_CLASS = BatchEntityWithRelationship::class;
}

// ── Helpers ────────────────────────────────────────────────────────────────────

/**
 * Creates a connection that records SQL/bindings and returns lastInsertId = $firstId.
 */
function makeBatchSpyConnection(
    array &$sqlLog,
    int $firstId = 1,
): ConnectionInterface {
    return new class ($sqlLog, $firstId) implements ConnectionInterface
    {
        private bool $shouldThrow = false;

        public function __construct(
            private array &$sqlLog,
            private readonly int $firstId,
        ) {}

        public function throwOnNextExecute(): void
        {
            $this->shouldThrow = true;
        }

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
            $this->sqlLog[] = ['type' => 'query', 'sql' => $sql, 'bindings' => $bindings];

            return [];
        }

        public function execute(
            string $sql,
            array $bindings = [],
        ): int {
            if ($this->shouldThrow) {
                $this->shouldThrow = false;
                throw new RuntimeException('Simulated DB failure');
            }

            $this->sqlLog[] = ['type' => 'execute', 'sql' => $sql, 'bindings' => $bindings];

            return count(explode('),(', $sql));
        }

        public function prepare(
            string $sql,
        ): StatementInterface {
            throw new RuntimeException('Not implemented');
        }

        public function lastInsertId(): int
        {
            return $this->firstId;
        }

        public function driverName(): string
        {
            return 'sqlite';
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
    };
}

/**
 * Creates a connection that tracks transaction calls alongside SQL log.
 */
function makeBatchTransactionConnection(
    array &$log,
    bool $failInsert = false,
): ConnectionInterface&TransactionInterface {
    return new class ($log, $failInsert) implements ConnectionInterface, TransactionInterface
    {
        private bool $inTx = false;

        public function __construct(
            private array &$log,
            private readonly bool $failInsert,
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
            if (
                $this->failInsert
                && str_contains($sql, 'INSERT')
            ) {
                throw new RuntimeException('Simulated DB failure on INSERT');
            }

            $this->log[] = ['type' => 'execute', 'sql' => $sql, 'bindings' => $bindings];

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

        public function driverName(): string
        {
            return 'sqlite';
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

        public function beginTransaction(): void
        {
            $this->inTx = true;
            $this->log[] = ['type' => 'beginTransaction'];
        }

        public function commit(): void
        {
            $this->inTx = false;
            $this->log[] = ['type' => 'commit'];
        }

        public function rollback(): void
        {
            $this->inTx = false;
            $this->log[] = ['type' => 'rollback'];
        }

        public function inTransaction(): bool
        {
            return $this->inTx;
        }

        public function transaction(
            callable $callback,
            int $attempts = 1,
            int|Closure|null $backoff = null,
        ): mixed {
            $this->log[] = ['type' => 'transaction'];
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
}

class BatchFakeDispatcher implements EventDispatcherInterface
{
    /** @var array<Event> */
    public array $dispatched = [];

    public function dispatch(
        Event $event,
    ): void {
        $this->dispatched[] = $event;
    }
}

// ── Tests ──────────────────────────────────────────────────────────────────────

it('inserts multiple entities in a single multi-row INSERT statement', function (): void {
    $sqlLog = [];
    $connection = makeBatchSpyConnection($sqlLog, 1);
    $repository = new BatchUserRepository($connection, new EntityMetadataFactory(), new EntityHydrator());

    $user1 = new BatchUser();
    $user1->name = 'Alice';
    $user1->email = 'alice@example.com';

    $user2 = new BatchUser();
    $user2->name = 'Bob';
    $user2->email = 'bob@example.com';

    $user3 = new BatchUser();
    $user3->name = 'Carol';
    $user3->email = 'carol@example.com';

    $repository->insertBatch([$user1, $user2, $user3]);

    $insertStatements = array_values(array_filter($sqlLog, fn ($e) => str_contains($e['sql'], 'INSERT')));
    expect($insertStatements)->toHaveCount(1)
        ->and($insertStatements[0]['sql'])->toContain('INSERT INTO "batch_users"')
        ->and(substr_count($insertStatements[0]['sql'], '(?, ?)') >= 3)->toBeTrue();
});

it('fires Creating event for each entity before insert', function (): void {
    $sqlLog = [];
    $connection = makeBatchSpyConnection($sqlLog, 1);
    $dispatcher = new BatchFakeDispatcher();
    $repository = new BatchUserRepository(
        $connection,
        new EntityMetadataFactory(),
        new EntityHydrator(),
        null,
        $dispatcher,
    );

    $user1 = new BatchUser();
    $user1->name = 'Alice';
    $user1->email = 'alice@example.com';

    $user2 = new BatchUser();
    $user2->name = 'Bob';
    $user2->email = 'bob@example.com';

    $repository->insertBatch([$user1, $user2]);

    $creatingEvents = array_values(array_filter($dispatcher->dispatched, fn ($e) => $e instanceof EntityCreating));
    expect($creatingEvents)->toHaveCount(2)
        ->and($creatingEvents[0]->entity)->toBe($user1)
        ->and($creatingEvents[1]->entity)->toBe($user2);
});

it('fires Created event for each entity after insert', function (): void {
    $sqlLog = [];
    $connection = makeBatchSpyConnection($sqlLog, 1);
    $dispatcher = new BatchFakeDispatcher();
    $repository = new BatchUserRepository(
        $connection,
        new EntityMetadataFactory(),
        new EntityHydrator(),
        null,
        $dispatcher,
    );

    $user1 = new BatchUser();
    $user1->name = 'Alice';
    $user1->email = 'alice@example.com';

    $user2 = new BatchUser();
    $user2->name = 'Bob';
    $user2->email = 'bob@example.com';

    $repository->insertBatch([$user1, $user2]);

    $createdEvents = array_values(array_filter($dispatcher->dispatched, fn ($e) => $e instanceof EntityCreated));
    expect($createdEvents)->toHaveCount(2)
        ->and($createdEvents[0]->entity)->toBe($user1)
        ->and($createdEvents[1]->entity)->toBe($user2);

    // Verify Creating fires before Created (Creating indices 0,1 precede Created indices 2,3)
    $allEvents = $dispatcher->dispatched;
    $creatingIdx = array_keys(array_filter($allEvents, fn ($e) => $e instanceof EntityCreating));
    $createdIdx = array_keys(array_filter($allEvents, fn ($e) => $e instanceof EntityCreated));
    expect(max($creatingIdx) < min($createdIdx))->toBeTrue();
});

it(
    'populates auto-increment keys from the first lastInsertId() on a connection without RETURNING',
    function (): void {
        $sqlLog = [];
        // Simulate MySQL: lastInsertId() returns first inserted ID = 10
        $connection = makeBatchSpyConnection($sqlLog, 10);
        $repository = new BatchUserRepository($connection, new EntityMetadataFactory(), new EntityHydrator());

        $user1 = new BatchUser();
        $user1->name = 'Alice';
        $user1->email = 'alice@example.com';

        $user2 = new BatchUser();
        $user2->name = 'Bob';
        $user2->email = 'bob@example.com';

        $user3 = new BatchUser();
        $user3->name = 'Carol';
        $user3->email = 'carol@example.com';

        expect($user1->id)->toBeNull()
            ->and($user2->id)->toBeNull()
            ->and($user3->id)->toBeNull();

        $repository->insertBatch([$user1, $user2, $user3]);

        expect($user1->id)->toBe(10)
            ->and($user2->id)->toBe(11)
            ->and($user3->id)->toBe(12);
    },
);

it(
    'assigns ids from a contiguous block in a single INSERT on a connection without RETURNING',
    function (): void {
        // A multi-row INSERT ... VALUES gets one contiguous block under innodb_autoinc_lock_mode
        // 0 and 1, and under 2 unless a bulk insert on the same table interleaves. Given that
        // block starting at firstId, each entity receives firstId + its zero-based index.

        $sqlLog = [];
        // firstId=5 simulates a scenario where rows 5, 6, 7 are a contiguous block
        $connection = makeBatchSpyConnection($sqlLog, 5);
        $repository = new BatchUserRepository($connection, new EntityMetadataFactory(), new EntityHydrator());

        $users = [];
        for ($i = 0; $i < 3; $i++) {
            $u = new BatchUser();
            $u->name = "User $i";
            $u->email = "user$i@example.com";
            $users[] = $u;
        }

        $repository->insertBatch($users);

        // Under contiguous-block assumption: IDs are 5, 6, 7, from a single INSERT statement
        $insertStmts = array_values(array_filter($sqlLog, fn ($e) => str_contains($e['sql'], 'INSERT')));
        expect($users[0]->id)->toBe(5)
            ->and($users[1]->id)->toBe(6)
            ->and($users[2]->id)->toBe(7)
            ->and($insertStmts)->toHaveCount(1);
    },
);

it('throws a descriptive exception when the input array is empty', function (): void {
    $sqlLog = [];
    $connection = makeBatchSpyConnection($sqlLog, 1);
    $repository = new BatchUserRepository($connection, new EntityMetadataFactory(), new EntityHydrator());

    $repository->insertBatch([]);
})->throws(BatchInsertException::class, 'Cannot insert an empty batch of entities');

it('throws a descriptive exception when entities in the batch are not all the same class', function (): void {
    $sqlLog = [];
    $connection = makeBatchSpyConnection($sqlLog, 1);
    $repository = new BatchUserRepository($connection, new EntityMetadataFactory(), new EntityHydrator());

    $user = new BatchUser();
    $user->name = 'Alice';
    $user->email = 'alice@example.com';

    $product = new BatchProduct();
    $product->sku = 'SKU-001';

    $repository->insertBatch([$user, $product]);
})->throws(BatchInsertException::class, 'All entities in the batch must be of the same class');

it('rolls back all rows when any insert fails (within a transaction)', function (): void {
    $log = [];
    $connection = makeBatchTransactionConnection($log, failInsert: true);
    $repository = new BatchUserRepository($connection, new EntityMetadataFactory(), new EntityHydrator());

    $user1 = new BatchUser();
    $user1->name = 'Alice';
    $user1->email = 'alice@example.com';

    $user2 = new BatchUser();
    $user2->name = 'Bob';
    $user2->email = 'bob@example.com';

    $threw = false;
    try {
        $repository->insertBatch([$user1, $user2]);
    } catch (Throwable) {
        $threw = true;
    }

    $txEvents = array_column($log, 'type');
    expect($threw)->toBeTrue()
        ->and(in_array('beginTransaction', $txEvents))->toBeTrue()
        ->and(in_array('rollback', $txEvents))->toBeTrue()
        ->and(in_array('commit', $txEvents))->toBeFalse();
});

it('wraps the batch insert in transaction() on a transactional connection', function (): void {
    $log = [];
    $connection = makeBatchTransactionConnection($log);
    $repository = new BatchUserRepository($connection, new EntityMetadataFactory(), new EntityHydrator());

    $user = new BatchUser();
    $user->name = 'Alice';
    $user->email = 'alice@example.com';

    $repository->insertBatch([$user]);

    expect(array_column($log, 'type'))->toBe(['transaction', 'beginTransaction', 'execute', 'commit']);
});

it('nests the batch insert inside an outer transaction instead of skipping the wrap', function (): void {
    $log = [];
    $connection = makeBatchTransactionConnection($log);
    $repository = new BatchUserRepository($connection, new EntityMetadataFactory(), new EntityHydrator());

    $user = new BatchUser();
    $user->name = 'Alice';
    $user->email = 'alice@example.com';

    $connection->beginTransaction();
    $repository->insertBatch([$user]);

    expect(array_column($log, 'type'))->toBe(
        ['beginTransaction', 'transaction', 'beginTransaction', 'execute', 'commit'],
    );
});

it('does NOT persist relationships of batch-inserted entities', function (): void {
    $sqlLog = [];
    $connection = makeBatchSpyConnection($sqlLog, 1);
    $repository = new BatchWithRelationshipRepository($connection, new EntityMetadataFactory(), new EntityHydrator());

    $entity1 = new BatchEntityWithRelationship();
    $entity1->title = 'Post 1';
    $entity1->tags = [
        (function () {
            $u = new BatchUser();
            $u->name = 'Tag User';
            $u->email = 'tag@example.com';

            return $u;
        })(),
    ];

    $entity2 = new BatchEntityWithRelationship();
    $entity2->title = 'Post 2';
    $entity2->tags = [];

    $repository->insertBatch([$entity1, $entity2]);

    // Only one INSERT statement should exist (for batch_with_tags), not for batch_users
    $insertStmts = array_values(array_filter($sqlLog, fn ($e) => str_contains($e['sql'], 'INSERT')));
    expect($insertStmts)->toHaveCount(1)
        ->and($insertStmts[0]['sql'])->toContain('batch_with_tags')
        ->and($insertStmts[0]['sql'])->not->toContain('batch_users');
});

it('handles string primary keys in the batch correctly', function (): void {
    $sqlLog = [];
    $connection = makeBatchSpyConnection($sqlLog, 0);
    $repository = new BatchStringPkRepository($connection, new EntityMetadataFactory(), new EntityHydrator());

    $item1 = new BatchStringPk();
    $item1->uuid = 'uuid-aaa';
    $item1->label = 'First';

    $item2 = new BatchStringPk();
    $item2->uuid = 'uuid-bbb';
    $item2->label = 'Second';

    $repository->insertBatch([$item1, $item2]);

    $insertStmts = array_values(array_filter($sqlLog, fn ($e) => str_contains($e['sql'], 'INSERT')));
    expect($insertStmts)->toHaveCount(1)
        ->and($insertStmts[0]['sql'])->toContain('INSERT INTO "batch_string_pk"')
        ->and($insertStmts[0]['bindings'])->toContain('uuid-aaa')
        ->and($insertStmts[0]['bindings'])->toContain('uuid-bbb')
        ->and($insertStmts[0]['bindings'])->toContain('First')
        ->and($insertStmts[0]['bindings'])->toContain('Second');

    // String PKs are not auto-increment — they must be preserved, not overwritten
    expect($item1->uuid)->toBe('uuid-aaa')
        ->and($item2->uuid)->toBe('uuid-bbb');
});

// ── PostgreSQL RETURNING tests ─────────────────────────────────────────────────

/**
 * Creates a pgsql connection stub that simulates INSERT ... RETURNING results.
 *
 * @param array<int, array<string, mixed>> $returningRows Rows returned from RETURNING clause
 * @param array<array{type: string, sql: string, bindings: array}> $sqlLog Reference for recording SQL calls
 */
function makePgsqlSpyConnection(
    array $returningRows,
    array &$sqlLog,
): ConnectionInterface {
    return new class ($returningRows, $sqlLog) implements ConnectionInterface
    {
        public function __construct(
            private readonly array $returningRows,
            private array &$sqlLog,
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
            $this->sqlLog[] = ['type' => 'query', 'sql' => $sql, 'bindings' => $bindings];

            return $this->returningRows;
        }

        public function execute(
            string $sql,
            array $bindings = [],
        ): int {
            $this->sqlLog[] = ['type' => 'execute', 'sql' => $sql, 'bindings' => $bindings];

            return count($this->returningRows);
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
            return 'pgsql';
        }

        public function supportsReturning(): bool
        {
            return true;
        }

        public function quoteIdentifier(
            string $identifier,
        ): string {
            return '"' . str_replace('"', '""', $identifier) . '"';
        }
    };
}

/**
 * Creates a pgsql connection stub that also implements TransactionInterface.
 *
 * @param array<int, array<string, mixed>> $returningRows Rows returned from RETURNING clause
 * @param array<array{type: string, sql: string, bindings: array}> $sqlLog Reference for recording SQL calls
 */
function makePgsqlTransactionConnection(
    array $returningRows,
    array &$sqlLog,
): ConnectionInterface&TransactionInterface {
    return new class ($returningRows, $sqlLog) implements ConnectionInterface, TransactionInterface
    {
        private bool $inTx = false;

        public function __construct(
            private readonly array $returningRows,
            private array &$sqlLog,
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
            $this->sqlLog[] = ['type' => 'query', 'sql' => $sql, 'bindings' => $bindings];

            return $this->returningRows;
        }

        public function execute(
            string $sql,
            array $bindings = [],
        ): int {
            $this->sqlLog[] = ['type' => 'execute', 'sql' => $sql, 'bindings' => $bindings];

            return count($this->returningRows);
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
            return 'pgsql';
        }

        public function supportsReturning(): bool
        {
            return true;
        }

        public function quoteIdentifier(
            string $identifier,
        ): string {
            return '"' . str_replace('"', '""', $identifier) . '"';
        }

        public function beginTransaction(): void
        {
            $this->inTx = true;
            $this->sqlLog[] = ['type' => 'beginTransaction'];
        }

        public function commit(): void
        {
            $this->inTx = false;
            $this->sqlLog[] = ['type' => 'commit'];
        }

        public function rollback(): void
        {
            $this->inTx = false;
            $this->sqlLog[] = ['type' => 'rollback'];
        }

        public function inTransaction(): bool
        {
            return $this->inTx;
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
}

it(
    'assigns each entity its true database id when batch-inserting two or more entities on a postgresql connection (ids are not shifted by count-1)',
    function (): void {
        // On pgsql with 3 rows, lastInsertId() would return 12 (the LAST row's id = LASTVAL).
        // The buggy code would assign: entity[0]=12, entity[1]=13, entity[2]=14 (wrong!).
        // The correct code uses RETURNING: entity[0]=10, entity[1]=11, entity[2]=12.
        $sqlLog = [];
        $returningRows = [
            ['id' => 10],
            ['id' => 11],
            ['id' => 12],
        ];
        $connection = makePgsqlTransactionConnection($returningRows, $sqlLog);
        $repository = new BatchUserRepository($connection, new EntityMetadataFactory(), new EntityHydrator());

        $user1 = new BatchUser();
        $user1->name = 'Alice';
        $user1->email = 'alice@example.com';

        $user2 = new BatchUser();
        $user2->name = 'Bob';
        $user2->email = 'bob@example.com';

        $user3 = new BatchUser();
        $user3->name = 'Carol';
        $user3->email = 'carol@example.com';

        expect($user1->id)->toBeNull()
            ->and($user2->id)->toBeNull()
            ->and($user3->id)->toBeNull();

        $repository->insertBatch([$user1, $user2, $user3]);

        expect($user1->id)->toBe(10)
            ->and($user2->id)->toBe(11)
            ->and($user3->id)->toBe(12);
    },
);

it(
    'assigns consecutive ids correctly when batch-inserting on a mysql connection',
    function (): void {
        $sqlLog = [];
        $connection = makeBatchSpyConnection($sqlLog, 5);
        $repository = new BatchUserRepository($connection, new EntityMetadataFactory(), new EntityHydrator());

        $user1 = new BatchUser();
        $user1->name = 'Alice';
        $user1->email = 'alice@example.com';

        $user2 = new BatchUser();
        $user2->name = 'Bob';
        $user2->email = 'bob@example.com';

        $repository->insertBatch([$user1, $user2]);

        expect($user1->id)->toBe(5)
            ->and($user2->id)->toBe(6);
    },
);

it(
    'builds an INSERT ... RETURNING <primaryKey> statement on a postgresql connection',
    function (): void {
        $sqlLog = [];
        $returningRows = [['id' => 1], ['id' => 2]];
        $connection = makePgsqlTransactionConnection($returningRows, $sqlLog);
        $repository = new BatchUserRepository($connection, new EntityMetadataFactory(), new EntityHydrator());

        $user1 = new BatchUser();
        $user1->name = 'Alice';
        $user1->email = 'alice@example.com';

        $user2 = new BatchUser();
        $user2->name = 'Bob';
        $user2->email = 'bob@example.com';

        $repository->insertBatch([$user1, $user2]);

        $insertStatements = array_values(array_filter($sqlLog, fn ($e) => str_contains($e['sql'] ?? '', 'INSERT')));
        expect($insertStatements)->not->toBeEmpty();
        $insertSql = $insertStatements[0]['sql'];
        expect($insertSql)
            ->toContain('INSERT INTO "batch_users"')
            ->toContain('RETURNING "id"');
    },
);

it(
    'builds a plain multi-row INSERT (no RETURNING) and uses LAST_INSERT_ID offset on mysql',
    function (): void {
        $sqlLog = [];
        $connection = makeBatchSpyConnection($sqlLog, 1);
        $repository = new BatchUserRepository($connection, new EntityMetadataFactory(), new EntityHydrator());

        $user1 = new BatchUser();
        $user1->name = 'Alice';
        $user1->email = 'alice@example.com';

        $user2 = new BatchUser();
        $user2->name = 'Bob';
        $user2->email = 'bob@example.com';

        $repository->insertBatch([$user1, $user2]);

        $insertStatements = array_values(array_filter($sqlLog, fn ($e) => str_contains($e['sql'] ?? '', 'INSERT')));
        expect($insertStatements)->not->toBeEmpty();
        $insertSql = $insertStatements[0]['sql'];
        expect($insertSql)
            ->toContain('INSERT INTO "batch_users"')
            ->not->toContain('RETURNING');
    },
);

it(
    'returns the true ids for three or more entities on pgsql (not just two), proving positional mapping over the full RETURNING result set',
    function (): void {
        $sqlLog = [];
        $returningRows = [
            ['id' => 100],
            ['id' => 101],
            ['id' => 102],
            ['id' => 103],
            ['id' => 104],
        ];
        $connection = makePgsqlTransactionConnection($returningRows, $sqlLog);
        $repository = new BatchUserRepository($connection, new EntityMetadataFactory(), new EntityHydrator());

        $users = [];
        for ($i = 0; $i < 5; $i++) {
            $u = new BatchUser();
            $u->name = "User $i";
            $u->email = "user$i@example.com";
            $users[] = $u;
        }

        $repository->insertBatch($users);

        expect($users[0]->id)->toBe(100)
            ->and($users[1]->id)->toBe(101)
            ->and($users[2]->id)->toBe(102)
            ->and($users[3]->id)->toBe(103)
            ->and($users[4]->id)->toBe(104);
    },
);

it(
    'throws a loud BatchInsertException when the pgsql RETURNING result count does not match the number of entities',
    function (): void {
        $sqlLog = [];
        // RETURNING returns 1 row but 2 entities were inserted
        $returningRows = [['id' => 1]];
        $connection = makePgsqlTransactionConnection($returningRows, $sqlLog);
        $repository = new BatchUserRepository($connection, new EntityMetadataFactory(), new EntityHydrator());

        $user1 = new BatchUser();
        $user1->name = 'Alice';
        $user1->email = 'alice@example.com';

        $user2 = new BatchUser();
        $user2->name = 'Bob';
        $user2->email = 'bob@example.com';

        expect(fn () => $repository->insertBatch([$user1, $user2]))
            ->toThrow(BatchInsertException::class);
    },
);

it(
    'routes the RETURNING write through the write connection (not a replica) on a read/write connection',
    function (): void {
        // The RETURNING path uses query() (SELECT-like path).
        // On a ReadWriteConnection without stickyWrite, query() normally goes to replica.
        // But after execute() sets stickyWrite=true, query() routes to write.
        // We test this via a standalone pgsql spy that exercises the path.
        $sqlLog = [];
        $returningRows = [['id' => 7], ['id' => 8]];
        $connection = makePgsqlTransactionConnection($returningRows, $sqlLog);
        $repository = new BatchUserRepository($connection, new EntityMetadataFactory(), new EntityHydrator());

        $user1 = new BatchUser();
        $user1->name = 'Alice';
        $user1->email = 'alice@example.com';

        $user2 = new BatchUser();
        $user2->name = 'Bob';
        $user2->email = 'bob@example.com';

        $repository->insertBatch([$user1, $user2]);

        // Verify the INSERT used RETURNING (pgsql path) and was a query() call
        $insertCalls = array_values(array_filter(
            $sqlLog,
            fn ($e) => isset($e['sql'])
                && str_contains($e['sql'], 'INSERT')
                && str_contains($e['sql'], 'RETURNING'),
        ));
        expect($insertCalls)->not->toBeEmpty()
            ->and($insertCalls[0]['type'])->toBe('query')
            ->and($user1->id)->toBe(7)
            ->and($user2->id)->toBe(8);
    },
);

// ── Database-generated keys ────────────────────────────────────────────────────

#[Table('batch_generated_tokens')]
class BatchGeneratedToken extends Entity
{
    #[Column(primaryKey: true, type: 'uuid', default: 'gen_random_uuid()', generated: true)]
    public ?string $id;

    #[Column]
    public string $label = '';
}

class BatchGeneratedTokenRepository extends Repository
{
    protected const string ENTITY_CLASS = BatchGeneratedToken::class;
}

function makeBatchGeneratedToken(
    string $label,
): BatchGeneratedToken {
    $token = new BatchGeneratedToken();
    $token->label = $label;

    return $token;
}

/**
 * A connection stub that reports RETURNING support under any driver name and records query() calls.
 *
 * @param list<array<string, mixed>> $returningRows
 * @param list<array{sql: string, bindings: array<mixed>}> $queries
 */
function makeReturningStubConnection(
    array $returningRows,
    array &$queries,
    string $driverName,
): ConnectionInterface {
    $connection = test()->createStub(ConnectionInterface::class);
    $connection->method('driverName')->willReturn($driverName);
    $connection->method('supportsReturning')->willReturn(true);
    $connection->method('quoteIdentifier')->willReturnCallback(
        fn (string $identifier): string => '"' . str_replace('"', '""', $identifier) . '"',
    );
    $connection->method('query')->willReturnCallback(
        function (string $sql, array $bindings = []) use ($returningRows, &$queries): array {
            $queries[] = ['sql' => $sql, 'bindings' => $bindings];

            return $returningRows;
        },
    );

    return $connection;
}

describe('database-generated keys in a batch', function (): void {
    it('reads generated keys back in insert order with RETURNING', function (): void {
        $sqlLog = [];
        $connection = makePgsqlSpyConnection(
            [['id' => 'a0e1c0de-0000-4000-8000-000000000001'], ['id' => 'a0e1c0de-0000-4000-8000-000000000002']],
            $sqlLog,
        );
        $repository = new BatchGeneratedTokenRepository(
            $connection,
            new EntityMetadataFactory(),
            new EntityHydrator(),
        );
        $first = makeBatchGeneratedToken('first');
        $second = makeBatchGeneratedToken('second');

        $repository->insertBatch([$first, $second]);

        expect($sqlLog[0]['sql'])->toBe(
            'INSERT INTO "batch_generated_tokens" ("label") VALUES (?), (?) RETURNING "id"',
        )
            ->and($sqlLog[0]['bindings'])->toBe(['first', 'second'])
            ->and($first->id)->toBe('a0e1c0de-0000-4000-8000-000000000001')
            ->and($second->id)->toBe('a0e1c0de-0000-4000-8000-000000000002');
    });

    it(
        'uses RETURNING for auto-increment keys when the connection supports it regardless of driver name',
        function (): void {
            $queries = [];
            $connection = makeReturningStubConnection([['id' => '11'], ['id' => '12']], $queries, 'cockroach');
            $repository = new BatchUserRepository($connection, new EntityMetadataFactory(), new EntityHydrator());
            $alice = new BatchUser();
            $alice->name = 'Alice';
            $bob = new BatchUser();
            $bob->name = 'Bob';

            $repository->insertBatch([$alice, $bob]);

            expect($queries[0]['sql'])->toEndWith('RETURNING "id"')
                ->and($alice->id)->toBe(11)
                ->and($bob->id)->toBe(12);
        },
    );

    it(
        'throws RepositoryException for unset generated keys in a batch on a connection without RETURNING',
        function (): void {
            $sqlLog = [];
            $repository = new BatchGeneratedTokenRepository(
                makeBatchSpyConnection($sqlLog),
                new EntityMetadataFactory(),
                new EntityHydrator(),
            );

            expect(fn () => $repository->insertBatch([makeBatchGeneratedToken('a'), makeBatchGeneratedToken('b')]))
                ->toThrow(RepositoryException::class, 'cannot read a generated key back')
                ->and($sqlLog)->toBe([]);
        },
    );

    it(
        'throws RepositoryException for unset keys that are neither generated nor auto-increment in a batch',
        function (): void {
            $sqlLog = [];
            $repository = new BatchStringPkRepository(
                makeBatchSpyConnection($sqlLog),
                new EntityMetadataFactory(),
                new EntityHydrator(),
            );
            $item = new BatchStringPk();
            $item->label = 'No key';

            expect(fn () => $repository->insertBatch([$item]))
                ->toThrow(RepositoryException::class, "Primary key 'uuid' of entity")
                ->and($sqlLog)->toBe([]);
        },
    );

    it('throws a clear exception for a batch that mixes set and unset generated keys', function (): void {
        $sqlLog = [];
        $repository = new BatchGeneratedTokenRepository(
            makePgsqlSpyConnection([], $sqlLog),
            new EntityMetadataFactory(),
            new EntityHydrator(),
        );
        $withKey = makeBatchGeneratedToken('with key');
        $withKey->id = 'a0e1c0de-0000-4000-8000-000000000003';

        expect(fn () => $repository->insertBatch([$withKey, makeBatchGeneratedToken('without key')]))
            ->toThrow(BatchInsertException::class, "with and without a 'id' key: entity at index 1");
    });
});

// ── MySQL auto_increment_increment ──────────────────────────────────────────────

/**
 * MySQL-flavoured transactional spy. query() answers the increment lookup with
 * $stepRows; lastInsertId() returns 0 when a query ran after the last execute()
 * (as PDO does), else $firstId.
 *
 * @param array<int, array<string, mixed>> $stepRows
 */
function makeMysqlStepSpyConnection(
    array &$log,
    array $stepRows,
    int $firstId = 10,
): ConnectionInterface&TransactionInterface {
    return new class ($log, $stepRows, $firstId) implements ConnectionInterface, TransactionInterface
    {
        private bool $queriedSinceExecute = false;

        private bool $inTx = false;

        public function __construct(
            private array &$log,
            private readonly array $stepRows,
            private readonly int $firstId,
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
            $this->log[] = ['type' => 'query', 'sql' => $sql, 'inTx' => $this->inTx];
            $this->queriedSinceExecute = true;

            return $this->stepRows;
        }

        public function execute(
            string $sql,
            array $bindings = [],
        ): int {
            $this->log[] = ['type' => 'execute', 'sql' => $sql, 'inTx' => $this->inTx];
            $this->queriedSinceExecute = false;

            return 1;
        }

        public function prepare(
            string $sql,
        ): StatementInterface {
            throw new RuntimeException('Not implemented');
        }

        public function lastInsertId(): int
        {
            return $this->queriedSinceExecute ? 0 : $this->firstId;
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
            return '`' . $identifier . '`';
        }

        public function beginTransaction(): void
        {
            $this->inTx = true;
        }

        public function commit(): void
        {
            $this->inTx = false;
        }

        public function rollback(): void
        {
            $this->inTx = false;
        }

        public function inTransaction(): bool
        {
            return $this->inTx;
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
}

/**
 * @return list<BatchUser>
 */
function makeBatchUsers(
    int $count,
): array {
    $users = [];
    for ($i = 0; $i < $count; $i++) {
        $user = new BatchUser();
        $user->name = "User $i";
        $user->email = "user$i@example.com";
        $users[] = $user;
    }

    return $users;
}

it('steps MySQL batch ids by auto_increment_increment', function (): void {
    $log = [];
    $connection = makeMysqlStepSpyConnection($log, [['auto_increment_increment' => 2]], 10);
    $repository = new BatchUserRepository($connection, new EntityMetadataFactory(), new EntityHydrator());
    $users = makeBatchUsers(3);

    $repository->insertBatch($users);

    expect(array_map(fn (BatchUser $u) => $u->id, $users))->toBe([10, 12, 14]);
});

it('reads auto_increment_increment inside the batch transaction on the same connection', function (): void {
    $log = [];
    $connection = makeMysqlStepSpyConnection($log, [['auto_increment_increment' => 1]]);
    $repository = new BatchUserRepository($connection, new EntityMetadataFactory(), new EntityHydrator());

    $repository->insertBatch(makeBatchUsers(2));

    $queries = array_values(array_filter($log, fn (array $e) => $e['type'] === 'query'));

    expect($queries)->toHaveCount(1)
        ->and($queries[0]['sql'])->toContain('@@auto_increment_increment')
        ->and($queries[0]['inTx'])->toBeTrue();
});

it('reads auto_increment_increment before the INSERT so lastInsertId() reflects the INSERT', function (): void {
    $log = [];
    $connection = makeMysqlStepSpyConnection($log, [['auto_increment_increment' => 1]], 10);
    $repository = new BatchUserRepository($connection, new EntityMetadataFactory(), new EntityHydrator());
    $users = makeBatchUsers(2);

    $repository->insertBatch($users);

    expect(array_column($log, 'type'))->toBe(['query', 'execute'])
        ->and($users[0]->id)->toBe(10)
        ->and($users[1]->id)->toBe(11);
});

it('accepts auto_increment_increment returned as a numeric string', function (): void {
    $log = [];
    $connection = makeMysqlStepSpyConnection($log, [['auto_increment_increment' => '3']], 5);
    $repository = new BatchUserRepository($connection, new EntityMetadataFactory(), new EntityHydrator());
    $users = makeBatchUsers(2);

    $repository->insertBatch($users);

    expect($users[0]->id)->toBe(5)
        ->and($users[1]->id)->toBe(8);
});

it('throws BatchInsertException naming auto_increment_increment when the setting cannot be read', function (
    array $rows,
): void {
    $log = [];
    $connection = makeMysqlStepSpyConnection($log, $rows);
    $repository = new BatchUserRepository($connection, new EntityMetadataFactory(), new EntityHydrator());

    expect(fn () => $repository->insertBatch(makeBatchUsers(2)))
        ->toThrow(BatchInsertException::class, 'auto_increment_increment');
})->with([
    'empty result' => [[]],
    'missing column' => [[['other' => 1]]],
    'zero' => [[['auto_increment_increment' => 0]]],
    'negative' => [[['auto_increment_increment' => -1]]],
    'non-numeric' => [[['auto_increment_increment' => 'abc']]],
]);

it('keeps explicit auto-increment keys set on every entity in the batch', function (): void {
    $log = [];
    $connection = makeMysqlStepSpyConnection($log, [['auto_increment_increment' => 2]], 999);
    $repository = new BatchUserRepository($connection, new EntityMetadataFactory(), new EntityHydrator());
    $users = makeBatchUsers(3);
    foreach ([10, 50, 30] as $i => $id) {
        $users[$i]->id = $id;
    }

    $repository->insertBatch($users);

    expect(array_map(fn (BatchUser $u) => $u->id, $users))->toBe([10, 50, 30])
        ->and(array_column($log, 'type'))->toBe(['execute']);
});

it('does not read auto_increment_increment on a non-mysql connection without RETURNING', function (): void {
    $sqlLog = [];
    $connection = makeBatchSpyConnection($sqlLog, 10);
    $repository = new BatchUserRepository($connection, new EntityMetadataFactory(), new EntityHydrator());
    $users = makeBatchUsers(2);

    $repository->insertBatch($users);

    expect(array_column($sqlLog, 'type'))->toBe(['execute'])
        ->and($users[1]->id)->toBe(11);
});
