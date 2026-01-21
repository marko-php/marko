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
    expect($parameters)->toHaveCount(1);
    expect($parameters[0]->getName())->toBe('id');
    expect($parameters[0]->getType()->getName())->toBe('int');

    $returnType = $method->getReturnType();
    expect($returnType->allowsNull())->toBeTrue();
    expect($returnType->getName())->toBe(Entity::class);
});

it('defines RepositoryInterface with findAll() method', function (): void {
    $reflection = new ReflectionClass(RepositoryInterface::class);

    expect($reflection->hasMethod('findAll'))->toBeTrue();

    $method = $reflection->getMethod('findAll');
    expect($method->isPublic())->toBeTrue();
    expect($method->getParameters())->toHaveCount(0);

    $returnType = $method->getReturnType();
    expect($returnType->getName())->toBe('array');
});

it('defines RepositoryInterface with findBy(criteria) method', function (): void {
    $reflection = new ReflectionClass(RepositoryInterface::class);

    expect($reflection->hasMethod('findBy'))->toBeTrue();

    $method = $reflection->getMethod('findBy');
    expect($method->isPublic())->toBeTrue();

    $parameters = $method->getParameters();
    expect($parameters)->toHaveCount(1);
    expect($parameters[0]->getName())->toBe('criteria');
    expect($parameters[0]->getType()->getName())->toBe('array');

    $returnType = $method->getReturnType();
    expect($returnType->getName())->toBe('array');
});

it('defines RepositoryInterface with findOneBy(criteria) method', function (): void {
    $reflection = new ReflectionClass(RepositoryInterface::class);

    expect($reflection->hasMethod('findOneBy'))->toBeTrue();

    $method = $reflection->getMethod('findOneBy');
    expect($method->isPublic())->toBeTrue();

    $parameters = $method->getParameters();
    expect($parameters)->toHaveCount(1);
    expect($parameters[0]->getName())->toBe('criteria');
    expect($parameters[0]->getType()->getName())->toBe('array');

    $returnType = $method->getReturnType();
    expect($returnType->allowsNull())->toBeTrue();
    expect($returnType->getName())->toBe(Entity::class);
});

it('defines RepositoryInterface with save(entity) method', function (): void {
    $reflection = new ReflectionClass(RepositoryInterface::class);

    expect($reflection->hasMethod('save'))->toBeTrue();

    $method = $reflection->getMethod('save');
    expect($method->isPublic())->toBeTrue();

    $parameters = $method->getParameters();
    expect($parameters)->toHaveCount(1);
    expect($parameters[0]->getName())->toBe('entity');
    expect($parameters[0]->getType()->getName())->toBe(Entity::class);

    $returnType = $method->getReturnType();
    expect($returnType->getName())->toBe('void');
});

it('defines RepositoryInterface with delete(entity) method', function (): void {
    $reflection = new ReflectionClass(RepositoryInterface::class);

    expect($reflection->hasMethod('delete'))->toBeTrue();

    $method = $reflection->getMethod('delete');
    expect($method->isPublic())->toBeTrue();

    $parameters = $method->getParameters();
    expect($parameters)->toHaveCount(1);
    expect($parameters[0]->getName())->toBe('entity');
    expect($parameters[0]->getType()->getName())->toBe(Entity::class);

    $returnType = $method->getReturnType();
    expect($returnType->getName())->toBe('void');
});

it('creates Repository base class implementing interface', function (): void {
    $reflection = new ReflectionClass(Repository::class);

    expect($reflection->implementsInterface(RepositoryInterface::class))->toBeTrue();
    expect($reflection->isAbstract())->toBeTrue();
});

it('requires ENTITY_CLASS constant in concrete repositories', function (): void {
    $reflection = new ReflectionClass(UserRepository::class);

    expect($reflection->hasConstant('ENTITY_CLASS'))->toBeTrue();
    expect($reflection->getConstant('ENTITY_CLASS'))->toBe(RepositoryTestUser::class);
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

    expect($metadata)->toBeInstanceOf(EntityMetadata::class);
    expect($metadata->tableName)->toBe('users');
    expect($metadata->entityClass)->toBe(RepositoryTestUser::class);
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

    expect($user)->toBeInstanceOf(RepositoryTestUser::class);
    expect($user->id)->toBe(1);
    expect($user->name)->toBe('John Doe');
    expect($user->email)->toBe('john@example.com');
    expect($user->isActive)->toBeTrue();
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
