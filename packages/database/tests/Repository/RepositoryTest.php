<?php

declare(strict_types=1);

namespace Marko\Database\Tests\Repository;

use Marko\Database\Attributes\Column;
use Marko\Database\Attributes\Table;
use Marko\Database\Connection\ConnectionInterface;
use Marko\Database\Connection\StatementInterface;
use Marko\Database\Entity\Entity;
use Marko\Database\Entity\EntityHydrator;
use Marko\Database\Entity\EntityMetadata;
use Marko\Database\Entity\EntityMetadataFactory;
use Marko\Database\Exceptions\RepositoryException;
use Marko\Database\Query\QueryBuilderInterface;
use Marko\Database\Repository\Repository;
use Marko\Database\Repository\RepositoryInterface;
use ReflectionClass;
use RuntimeException;

#[Table('users')]
class RepositoryTestUser extends Entity
{
    #[Column(primaryKey: true, autoIncrement: true)]
    public ?int $id = null;

    #[Column]
    public string $name;

    #[Column('email_address')]
    public string $email;

    #[Column]
    public bool $isActive;
}

/**
 * A concrete repository implementation for testing.
 */
class UserRepository extends Repository
{
    protected const string ENTITY_CLASS = RepositoryTestUser::class;
}

/**
 * A repository without ENTITY_CLASS constant for testing error handling.
 */
class InvalidRepository extends Repository {}

// Test RepositoryInterface method definitions

it('defines RepositoryInterface with find(id) method', function (): void {
    $reflection = new ReflectionClass(RepositoryInterface::class);

    expect($reflection->hasMethod('find'))->toBeTrue();

    $method = $reflection->getMethod('find');
    expect($method->isPublic())->toBeTrue();

    $parameters = $method->getParameters();
    $returnType = $method->getReturnType();
    expect($parameters)->toHaveCount(1)
        ->and($parameters[0]->getName())->toBe('id')
        ->and($parameters[0]->getType()->getName())->toBe('int')
        ->and($returnType->allowsNull())->toBeTrue()
        ->and($returnType->getName())->toBe(Entity::class);
});

it('defines RepositoryInterface with findAll() method', function (): void {
    $reflection = new ReflectionClass(RepositoryInterface::class);

    expect($reflection->hasMethod('findAll'))->toBeTrue();

    $method = $reflection->getMethod('findAll');
    $returnType = $method->getReturnType();
    expect($method->isPublic())->toBeTrue()
        ->and($method->getParameters())->toHaveCount(0)
        ->and($returnType->getName())->toBe('array');
});

it('defines RepositoryInterface with findBy(criteria) method', function (): void {
    $reflection = new ReflectionClass(RepositoryInterface::class);

    expect($reflection->hasMethod('findBy'))->toBeTrue();

    $method = $reflection->getMethod('findBy');
    $parameters = $method->getParameters();
    $returnType = $method->getReturnType();
    expect($method->isPublic())->toBeTrue()
        ->and($parameters)->toHaveCount(1)
        ->and($parameters[0]->getName())->toBe('criteria')
        ->and($parameters[0]->getType()->getName())->toBe('array')
        ->and($returnType->getName())->toBe('array');
});

it('defines RepositoryInterface with findOneBy(criteria) method', function (): void {
    $reflection = new ReflectionClass(RepositoryInterface::class);

    expect($reflection->hasMethod('findOneBy'))->toBeTrue();

    $method = $reflection->getMethod('findOneBy');
    $parameters = $method->getParameters();
    $returnType = $method->getReturnType();
    expect($method->isPublic())->toBeTrue()
        ->and($parameters)->toHaveCount(1)
        ->and($parameters[0]->getName())->toBe('criteria')
        ->and($parameters[0]->getType()->getName())->toBe('array')
        ->and($returnType->allowsNull())->toBeTrue()
        ->and($returnType->getName())->toBe(Entity::class);
});

it('defines RepositoryInterface with save(entity) method', function (): void {
    $reflection = new ReflectionClass(RepositoryInterface::class);

    expect($reflection->hasMethod('save'))->toBeTrue();

    $method = $reflection->getMethod('save');
    $parameters = $method->getParameters();
    $returnType = $method->getReturnType();
    expect($method->isPublic())->toBeTrue()
        ->and($parameters)->toHaveCount(1)
        ->and($parameters[0]->getName())->toBe('entity')
        ->and($parameters[0]->getType()->getName())->toBe(Entity::class)
        ->and($returnType->getName())->toBe('void');
});

it('defines RepositoryInterface with delete(entity) method', function (): void {
    $reflection = new ReflectionClass(RepositoryInterface::class);

    expect($reflection->hasMethod('delete'))->toBeTrue();

    $method = $reflection->getMethod('delete');
    $parameters = $method->getParameters();
    $returnType = $method->getReturnType();
    expect($method->isPublic())->toBeTrue()
        ->and($parameters)->toHaveCount(1)
        ->and($parameters[0]->getName())->toBe('entity')
        ->and($parameters[0]->getType()->getName())->toBe(Entity::class)
        ->and($returnType->getName())->toBe('void');
});

it('creates Repository base class implementing interface', function (): void {
    $reflection = new ReflectionClass(Repository::class);

    expect($reflection->implementsInterface(RepositoryInterface::class))->toBeTrue()
        ->and($reflection->isAbstract())->toBeTrue();
});

it('requires ENTITY_CLASS constant in concrete repositories', function (): void {
    $reflection = new ReflectionClass(UserRepository::class);

    expect($reflection->hasConstant('ENTITY_CLASS'))->toBeTrue()
        ->and($reflection->getConstant('ENTITY_CLASS'))->toBe(RepositoryTestUser::class);
});

it('throws RepositoryException if ENTITY_CLASS not defined', function (): void {
    $connection = createMockConnection();
    $metadataFactory = new EntityMetadataFactory();
    $hydrator = new EntityHydrator();

    new InvalidRepository($connection, $metadataFactory, $hydrator);
})->throws(RepositoryException::class, 'does not define ENTITY_CLASS constant');

it('uses EntityMetadata to determine table and columns', function (): void {
    $connection = createMockConnection();
    $metadataFactory = new EntityMetadataFactory();
    $hydrator = new EntityHydrator();

    $repository = new UserRepository($connection, $metadataFactory, $hydrator);

    // Access the metadata via reflection to verify it was set
    $reflection = new ReflectionClass($repository);
    $metadataProperty = $reflection->getProperty('metadata');
    $metadata = $metadataProperty->getValue($repository);

    expect($metadata)->toBeInstanceOf(EntityMetadata::class)
        ->and($metadata->tableName)->toBe('users')
        ->and($metadata->entityClass)->toBe(RepositoryTestUser::class);
});

it('uses EntityHydrator to convert rows to entities', function (): void {
    // Column names must match what EntityMetadataFactory generates:
    // - 'id' maps to 'id' column
    // - 'name' maps to 'name' column
    // - 'email' maps to 'email_address' column (explicit in #[Column('email_address')])
    // - 'isActive' maps to 'isActive' column (no explicit name, uses property name)
    $connection = createMockConnection([
        [
            'id' => 1,
            'name' => 'John Doe',
            'email_address' => 'john@example.com',
            'isActive' => 1,
        ],
    ]);
    $metadataFactory = new EntityMetadataFactory();
    $hydrator = new EntityHydrator();

    $repository = new UserRepository($connection, $metadataFactory, $hydrator);

    $user = $repository->find(1);

    expect($user)->toBeInstanceOf(RepositoryTestUser::class)
        ->and($user->id)->toBe(1)
        ->and($user->name)->toBe('John Doe')
        ->and($user->email)->toBe('john@example.com')
        ->and($user->isActive)->toBeTrue();
});

it('injects ConnectionInterface via constructor', function (): void {
    $connection = createMockConnection();
    $metadataFactory = new EntityMetadataFactory();
    $hydrator = new EntityHydrator();

    $repository = new UserRepository($connection, $metadataFactory, $hydrator);

    $reflection = new ReflectionClass($repository);
    $connectionProperty = $reflection->getProperty('connection');
    $connectionValue = $connectionProperty->getValue($repository);

    expect($connectionValue)->toBeInstanceOf(ConnectionInterface::class);
});

// Test find functionality

it('finds entity by primary key with find(id)', function (): void {
    $connection = createMockConnection([
        [
            'id' => 42,
            'name' => 'Jane Doe',
            'email_address' => 'jane@example.com',
            'isActive' => 1,
        ],
    ]);
    $metadataFactory = new EntityMetadataFactory();
    $hydrator = new EntityHydrator();

    $repository = new UserRepository($connection, $metadataFactory, $hydrator);
    $user = $repository->find(42);

    expect($user)->toBeInstanceOf(RepositoryTestUser::class)
        ->and($user->id)->toBe(42)
        ->and($user->name)->toBe('Jane Doe')
        ->and($user->email)->toBe('jane@example.com');
});

it('returns null when entity not found', function (): void {
    $connection = createMockConnection([]);
    $metadataFactory = new EntityMetadataFactory();
    $hydrator = new EntityHydrator();

    $repository = new UserRepository($connection, $metadataFactory, $hydrator);
    $user = $repository->find(999);

    expect($user)->toBeNull();
});

it('defines RepositoryInterface with findOrFail(id) method', function (): void {
    $reflection = new ReflectionClass(RepositoryInterface::class);

    expect($reflection->hasMethod('findOrFail'))->toBeTrue();

    $method = $reflection->getMethod('findOrFail');
    expect($method->isPublic())->toBeTrue();

    $parameters = $method->getParameters();
    $returnType = $method->getReturnType();
    expect($parameters)->toHaveCount(1)
        ->and($parameters[0]->getName())->toBe('id')
        ->and($parameters[0]->getType()->getName())->toBe('int')
        ->and($returnType->allowsNull())->toBeFalse()
        ->and($returnType->getName())->toBe(Entity::class);
});

it('finds entity by primary key with findOrFail(id)', function (): void {
    $connection = createMockConnection([
        [
            'id' => 42,
            'name' => 'Jane Doe',
            'email_address' => 'jane@example.com',
            'isActive' => 1,
        ],
    ]);
    $metadataFactory = new EntityMetadataFactory();
    $hydrator = new EntityHydrator();

    $repository = new UserRepository($connection, $metadataFactory, $hydrator);
    $user = $repository->findOrFail(42);

    expect($user)->toBeInstanceOf(RepositoryTestUser::class)
        ->and($user->id)->toBe(42)
        ->and($user->name)->toBe('Jane Doe');
});

it('throws RepositoryException when findOrFail() entity not found', function (): void {
    $connection = createMockConnection([]);
    $metadataFactory = new EntityMetadataFactory();
    $hydrator = new EntityHydrator();

    $repository = new UserRepository($connection, $metadataFactory, $hydrator);
    $repository->findOrFail(999);
})->throws(
    RepositoryException::class,
    "Entity 'Marko\\Database\\Tests\\Repository\\RepositoryTestUser' with ID 999 not found",
);

it('finds all entities with findAll()', function (): void {
    $connection = createMockConnection([
        ['id' => 1, 'name' => 'Alice', 'email_address' => 'alice@example.com', 'isActive' => 1],
        ['id' => 2, 'name' => 'Bob', 'email_address' => 'bob@example.com', 'isActive' => 0],
    ]);
    $metadataFactory = new EntityMetadataFactory();
    $hydrator = new EntityHydrator();

    $repository = new UserRepository($connection, $metadataFactory, $hydrator);
    $users = $repository->findAll();

    expect($users)->toHaveCount(2)
        ->and($users[0])->toBeInstanceOf(RepositoryTestUser::class)
        ->and($users[0]->name)->toBe('Alice')
        ->and($users[1]->name)->toBe('Bob');
});

it('finds entities by criteria array with findBy(array)', function (): void {
    $connection = createMockConnection([
        ['id' => 1, 'name' => 'Alice', 'email_address' => 'alice@example.com', 'isActive' => 1],
        ['id' => 3, 'name' => 'Charlie', 'email_address' => 'charlie@example.com', 'isActive' => 1],
    ]);
    $metadataFactory = new EntityMetadataFactory();
    $hydrator = new EntityHydrator();

    $repository = new UserRepository($connection, $metadataFactory, $hydrator);
    $users = $repository->findBy(['isActive' => true]);

    expect($users)->toHaveCount(2)
        ->and($users[0]->isActive)->toBeTrue()
        ->and($users[1]->isActive)->toBeTrue();
});

it('finds single entity by criteria with findOneBy(array)', function (): void {
    $connection = createMockConnection([
        ['id' => 2, 'name' => 'Bob', 'email_address' => 'bob@example.com', 'isActive' => 1],
    ]);
    $metadataFactory = new EntityMetadataFactory();
    $hydrator = new EntityHydrator();

    $repository = new UserRepository($connection, $metadataFactory, $hydrator);
    $user = $repository->findOneBy(['name' => 'Bob']);

    expect($user)->toBeInstanceOf(RepositoryTestUser::class)
        ->and($user->name)->toBe('Bob');
});

it('inserts new entity with save() when no ID', function (): void {
    $executedSql = [];
    $executedBindings = [];

    $connection = new class ($executedSql, $executedBindings) implements ConnectionInterface
    {
        public function __construct(
            private array &$executedSql,
            private array &$executedBindings,
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
            $this->executedSql[] = $sql;
            $this->executedBindings[] = $bindings;

            return 1;
        }

        public function prepare(
            string $sql,
        ): StatementInterface {
            throw new RuntimeException('Not implemented');
        }

        public function lastInsertId(): int
        {
            return 123;
        }
    };

    $metadataFactory = new EntityMetadataFactory();
    $hydrator = new EntityHydrator();

    $repository = new UserRepository($connection, $metadataFactory, $hydrator);

    $user = new RepositoryTestUser();
    $user->name = 'New User';
    $user->email = 'new@example.com';
    $user->isActive = true;

    $repository->save($user);

    expect($executedSql)->toHaveCount(1)
        ->and($executedSql[0])->toContain('INSERT INTO')
        ->and($executedSql[0])->toContain('users');
});

it('updates existing entity with save() when has ID', function (): void {
    $executedSql = [];
    $executedBindings = [];

    $connection = new class ($executedSql, $executedBindings) implements ConnectionInterface
    {
        private bool $firstQuery = true;

        public function __construct(
            private array &$executedSql,
            private array &$executedBindings,
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
            // First query returns the existing entity
            if ($this->firstQuery) {
                $this->firstQuery = false;

                return [
                    ['id' => 1, 'name' => 'Original', 'email_address' => 'orig@example.com', 'isActive' => 1],
                ];
            }

            return [];
        }

        public function execute(
            string $sql,
            array $bindings = [],
        ): int {
            $this->executedSql[] = $sql;
            $this->executedBindings[] = $bindings;

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

    $metadataFactory = new EntityMetadataFactory();
    $hydrator = new EntityHydrator();

    $repository = new UserRepository($connection, $metadataFactory, $hydrator);

    // Fetch an existing entity so hydrator tracks it
    $user = $repository->find(1);
    $user->name = 'Updated';

    $repository->save($user);

    expect($executedSql)->toHaveCount(1)
        ->and($executedSql[0])->toContain('UPDATE')
        ->and($executedSql[0])->toContain('users');
});

it('only updates dirty fields on existing entity', function (): void {
    $executedSql = [];
    $executedBindings = [];

    $connection = new class ($executedSql, $executedBindings) implements ConnectionInterface
    {
        private bool $firstQuery = true;

        public function __construct(
            private array &$executedSql,
            private array &$executedBindings,
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
            if ($this->firstQuery) {
                $this->firstQuery = false;

                return [
                    ['id' => 1, 'name' => 'Original', 'email_address' => 'orig@example.com', 'isActive' => 1],
                ];
            }

            return [];
        }

        public function execute(
            string $sql,
            array $bindings = [],
        ): int {
            $this->executedSql[] = $sql;
            $this->executedBindings[] = $bindings;

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

    $metadataFactory = new EntityMetadataFactory();
    $hydrator = new EntityHydrator();

    $repository = new UserRepository($connection, $metadataFactory, $hydrator);

    // Fetch entity - hydrator will track original values
    $user = $repository->find(1);

    // Only change name, not email or isActive
    $user->name = 'Changed Name';

    $repository->save($user);

    expect($executedSql)->toHaveCount(1);

    // The SQL should only update the dirty field (name)
    $sql = $executedSql[0];
    // Should not contain email_address in SET clause (only in bindings/conditions)
    $setClause = substr($sql, strpos($sql, 'SET'), strpos($sql, 'WHERE') - strpos($sql, 'SET'));
    expect($sql)->toContain('name')
        ->and($setClause)->not->toContain('email_address')
        ->and($setClause)->not->toContain('isActive');
});

it('sets auto-generated ID on entity after insert', function (): void {
    $connection = new class () implements ConnectionInterface
    {
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
            return 1;
        }

        public function prepare(
            string $sql,
        ): StatementInterface {
            throw new RuntimeException('Not implemented');
        }

        public function lastInsertId(): int
        {
            return 456;
        }
    };

    $metadataFactory = new EntityMetadataFactory();
    $hydrator = new EntityHydrator();

    $repository = new UserRepository($connection, $metadataFactory, $hydrator);

    $user = new RepositoryTestUser();
    $user->name = 'New User';
    $user->email = 'new@example.com';
    $user->isActive = true;

    expect($user->id)->toBeNull();

    $repository->save($user);

    expect($user->id)->toBe(456);
});

it('deletes entity with delete()', function (): void {
    $executedSql = [];
    $executedBindings = [];

    $connection = new class ($executedSql, $executedBindings) implements ConnectionInterface
    {
        private bool $firstQuery = true;

        public function __construct(
            private array &$executedSql,
            private array &$executedBindings,
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
            if ($this->firstQuery) {
                $this->firstQuery = false;

                return [
                    ['id' => 5, 'name' => 'ToDelete', 'email_address' => 'del@example.com', 'isActive' => 1],
                ];
            }

            return [];
        }

        public function execute(
            string $sql,
            array $bindings = [],
        ): int {
            $this->executedSql[] = $sql;
            $this->executedBindings[] = $bindings;

            return 1;
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
    };

    $metadataFactory = new EntityMetadataFactory();
    $hydrator = new EntityHydrator();

    $repository = new UserRepository($connection, $metadataFactory, $hydrator);

    $user = $repository->find(5);
    $repository->delete($user);

    expect($executedSql)->toHaveCount(1)
        ->and($executedSql[0])->toContain('DELETE FROM')
        ->and($executedSql[0])->toContain('users')
        ->and($executedBindings[0])->toBe([5]);
});

it('provides query() method returning QueryBuilder for custom queries', function (): void {
    $connection = createMockConnection([]);
    $metadataFactory = new EntityMetadataFactory();
    $hydrator = new EntityHydrator();

    $mockQueryBuilder = createMockQueryBuilder($connection);
    $queryBuilderFactory = fn () => createMockQueryBuilder($connection);

    $repository = new UserRepository($connection, $metadataFactory, $hydrator, $queryBuilderFactory);

    $queryBuilder = $repository->query();

    expect($queryBuilder)->toBeInstanceOf(QueryBuilderInterface::class);
});

it('hydrates results from query() automatically', function (): void {
    $connection = createMockConnection([
        ['id' => 1, 'name' => 'QueryUser', 'email_address' => 'query@example.com', 'isActive' => 1],
    ]);
    $metadataFactory = new EntityMetadataFactory();
    $hydrator = new EntityHydrator();

    $queryBuilderFactory = fn () => createMockQueryBuilder($connection);

    $repository = new UserRepository($connection, $metadataFactory, $hydrator, $queryBuilderFactory);

    // The repository should provide a way to execute custom queries and get hydrated entities
    $users = $repository->query()
        ->where('name', '=', 'QueryUser')
        ->getEntities();

    expect($users)->toHaveCount(1)
        ->and($users[0])->toBeInstanceOf(RepositoryTestUser::class)
        ->and($users[0]->name)->toBe('QueryUser');
});

it('supports count() method returning total count', function (): void {
    $connection = new class () implements ConnectionInterface
    {
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
            if (str_contains($sql, 'COUNT(*)')) {
                return [['aggregate' => 42]];
            }

            return [];
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
    };

    $metadataFactory = new EntityMetadataFactory();
    $hydrator = new EntityHydrator();

    $repository = new UserRepository($connection, $metadataFactory, $hydrator);

    expect($repository->count())->toBe(42);
});

it('supports exists(id) method returning boolean', function (): void {
    $queryCount = 0;

    $connection = new class ($queryCount) implements ConnectionInterface
    {
        public function __construct(
            private int &$queryCount,
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
            $this->queryCount++;

            // First call (id=1) returns exists, second call (id=999) returns not exists
            if ($this->queryCount === 1 && $bindings[0] === 1) {
                return [['id' => 1, 'name' => 'Exists', 'email_address' => 'exists@example.com', 'isActive' => 1]];
            }

            return [];
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
    };

    $metadataFactory = new EntityMetadataFactory();
    $hydrator = new EntityHydrator();

    $repository = new UserRepository($connection, $metadataFactory, $hydrator);

    expect($repository->exists(1))->toBeTrue()
        ->and($repository->exists(999))->toBeFalse();
});

// Helper function to create mock connection

function createMockConnection(
    array $queryResult = [],
): ConnectionInterface {
    return new class ($queryResult) implements ConnectionInterface
    {
        public function __construct(
            private readonly array $queryResult,
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
            return $this->queryResult;
        }

        public function execute(
            string $sql,
            array $bindings = [],
        ): int {
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
}

// Helper function to create mock query builder

function createMockQueryBuilder(
    ConnectionInterface $connection,
): QueryBuilderInterface {
    return new class ($connection) implements QueryBuilderInterface
    {
        private string $table = '';

        /** @var array<array{column: string, operator: string, value: mixed}> */
        private array $wheres = [];

        public function __construct(
            private readonly ConnectionInterface $connection,
        ) {}

        public function table(
            string $table,
        ): static {
            $this->table = $table;

            return $this;
        }

        public function select(
            string ...$columns,
        ): static {
            return $this;
        }

        public function where(
            string $column,
            string $operator,
            mixed $value,
        ): static {
            $this->wheres[] = ['column' => $column, 'operator' => $operator, 'value' => $value];

            return $this;
        }

        public function whereIn(
            string $column,
            array $values,
        ): static {
            return $this;
        }

        public function whereNull(
            string $column,
        ): static {
            return $this;
        }

        public function whereNotNull(
            string $column,
        ): static {
            return $this;
        }

        public function orWhere(
            string $column,
            string $operator,
            mixed $value,
        ): static {
            return $this;
        }

        public function join(
            string $table,
            string $first,
            string $operator,
            string $second,
        ): static {
            return $this;
        }

        public function leftJoin(
            string $table,
            string $first,
            string $operator,
            string $second,
        ): static {
            return $this;
        }

        public function rightJoin(
            string $table,
            string $first,
            string $operator,
            string $second,
        ): static {
            return $this;
        }

        public function orderBy(
            string $column,
            string $direction = 'ASC',
        ): static {
            return $this;
        }

        public function limit(
            int $limit,
        ): static {
            return $this;
        }

        public function offset(
            int $offset,
        ): static {
            return $this;
        }

        public function get(): array
        {
            // Use the connection to execute the query
            $sql = sprintf('SELECT * FROM %s', $this->table);

            return $this->connection->query($sql);
        }

        public function first(): ?array
        {
            $results = $this->get();

            return $results[0] ?? null;
        }

        public function insert(
            array $data,
        ): int {
            return 1;
        }

        public function update(
            array $data,
        ): int {
            return 1;
        }

        public function delete(): int
        {
            return 1;
        }

        public function count(): int
        {
            return 0;
        }

        public function raw(
            string $sql,
            array $bindings = [],
        ): array {
            return $this->connection->query($sql, $bindings);
        }
    };
}
