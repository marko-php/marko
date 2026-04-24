<?php

declare(strict_types=1);

namespace Marko\Database\Tests\Repository;

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
function makeBatchSpyConnection(array &$sqlLog, int $firstId = 1): ConnectionInterface
{
    return new class ($sqlLog, $firstId) implements ConnectionInterface
    {
        private bool $shouldThrow = false;

        public function __construct(
            private array &$sqlLog,
            private int $firstId,
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

        public function query(string $sql, array $bindings = []): array
        {
            $this->sqlLog[] = ['type' => 'query', 'sql' => $sql, 'bindings' => $bindings];

            return [];
        }

        public function execute(string $sql, array $bindings = []): int
        {
            if ($this->shouldThrow) {
                $this->shouldThrow = false;
                throw new RuntimeException('Simulated DB failure');
            }

            $this->sqlLog[] = ['type' => 'execute', 'sql' => $sql, 'bindings' => $bindings];

            return count(explode('),(', $sql));
        }

        public function prepare(string $sql): StatementInterface
        {
            throw new RuntimeException('Not implemented');
        }

        public function lastInsertId(): int
        {
            return $this->firstId;
        }
    };
}

/**
 * Creates a connection that tracks transaction calls alongside SQL log.
 */
function makeBatchTransactionConnection(array &$log, bool $failInsert = false): ConnectionInterface&TransactionInterface
{
    return new class ($log, $failInsert) implements ConnectionInterface, TransactionInterface
    {
        private bool $inTx = false;

        public function __construct(
            private array &$log,
            private bool $failInsert,
        ) {}

        public function connect(): void {}

        public function disconnect(): void {}

        public function isConnected(): bool
        {
            return true;
        }

        public function query(string $sql, array $bindings = []): array
        {
            return [];
        }

        public function execute(string $sql, array $bindings = []): int
        {
            if ($this->failInsert && str_contains($sql, 'INSERT')) {
                throw new RuntimeException('Simulated DB failure on INSERT');
            }

            $this->log[] = ['type' => 'execute', 'sql' => $sql, 'bindings' => $bindings];

            return 1;
        }

        public function prepare(string $sql): StatementInterface
        {
            throw new RuntimeException('Not implemented');
        }

        public function lastInsertId(): int
        {
            return 1;
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

        public function transaction(callable $callback): mixed
        {
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
}

class BatchFakeDispatcher implements EventDispatcherInterface
{
    /** @var array<Event> */
    public array $dispatched = [];

    public function dispatch(Event $event): void
    {
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
        ->and($insertStatements[0]['sql'])->toContain('INSERT INTO batch_users')
        ->and(substr_count($insertStatements[0]['sql'], '(?, ?)') >= 3)->toBeTrue();
});

it('fires Creating event for each entity before insert', function (): void {
    $sqlLog = [];
    $connection = makeBatchSpyConnection($sqlLog, 1);
    $dispatcher = new BatchFakeDispatcher();
    $repository = new BatchUserRepository($connection, new EntityMetadataFactory(), new EntityHydrator(), null, $dispatcher);

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
    $repository = new BatchUserRepository($connection, new EntityMetadataFactory(), new EntityHydrator(), null, $dispatcher);

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

it('populates auto-generated primary keys back onto each entity when the driver supports it (MySQL: lastInsertId returns the FIRST id, increment by one per row assuming no gaps; PostgreSQL: use INSERT ... RETURNING id)', function (): void {
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
});

it('documents and tests that MySQL populated-id logic is correct only when innodb_autoinc_lock_mode permits sequential ids (contiguous block)', function (): void {
    // MySQL innodb_autoinc_lock_mode=2 (interleaved, the default since MySQL 8.0) does NOT
    // guarantee a contiguous block of IDs for a single multi-row INSERT in a concurrent
    // environment. The MySQL id-recovery strategy (LAST_INSERT_ID + row-count math) is
    // therefore only reliable under lock_mode=0 (traditional) or lock_mode=1 (consecutive),
    // where a single INSERT statement always receives a contiguous block.
    //
    // This test verifies the documented contract: given a contiguous block starting at
    // firstId, each entity receives firstId + its zero-based index in the batch.

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

    // Under contiguous-block assumption: IDs are 5, 6, 7
    expect($users[0]->id)->toBe(5)
        ->and($users[1]->id)->toBe(6)
        ->and($users[2]->id)->toBe(7);

    // Verify that only a single INSERT statement was issued
    $insertStmts = array_values(array_filter($sqlLog, fn ($e) => str_contains($e['sql'], 'INSERT')));
    expect($insertStmts)->toHaveCount(1);
});

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

    expect($threw)->toBeTrue();

    $txEvents = array_column($log, 'type');
    expect(in_array('beginTransaction', $txEvents))->toBeTrue()
        ->and(in_array('rollback', $txEvents))->toBeTrue()
        ->and(in_array('commit', $txEvents))->toBeFalse();
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
        ->and($insertStmts[0]['sql'])->toContain('INSERT INTO batch_string_pk')
        ->and($insertStmts[0]['bindings'])->toContain('uuid-aaa')
        ->and($insertStmts[0]['bindings'])->toContain('uuid-bbb')
        ->and($insertStmts[0]['bindings'])->toContain('First')
        ->and($insertStmts[0]['bindings'])->toContain('Second');

    // String PKs are not auto-increment — they must be preserved, not overwritten
    expect($item1->uuid)->toBe('uuid-aaa')
        ->and($item2->uuid)->toBe('uuid-bbb');
});
