<?php

declare(strict_types=1);

namespace Marko\Database\Tests\Feature;

use Marko\Database\Attributes\Column;
use Marko\Database\Attributes\Table;
use Marko\Database\Connection\ConnectionInterface;
use Marko\Database\Connection\StatementInterface;
use Marko\Database\Entity\Entity;
use Marko\Database\Entity\EntityHydrator;
use Marko\Database\Entity\EntityMetadataFactory;
use Marko\Database\Repository\Repository;
use RuntimeException;

// Test entity for existence checks

#[Table('exist_users')]
class ExistUser extends Entity
{
    #[Column(primaryKey: true, autoIncrement: true)]
    public ?int $id = null;

    /** @noinspection PhpUnused - Entity property for structural definition */
    #[Column(length: 100)]
    public string $email = '';
}

class ExistUserRepository extends Repository
{
    protected const string ENTITY_CLASS = ExistUser::class;

    /**
     * Expose isColumnUnique publicly for testing.
     */
    public function isEmailUnique(
        string $email,
        int|string|null $excludeId = null,
    ): bool {
        return $this->isColumnUnique('email', $email, $excludeId);
    }
}

/**
 * Build a stub ConnectionInterface that records all query() calls and returns
 * the given rows when the SQL starts with SELECT 1.
 *
 * @param array<array<string, mixed>> $rows Rows to return for SELECT 1 probes
 * @param list<array{sql: string, bindings: array<mixed>}> $log Reference to capture queries
 */
function makeExistsConnection(array $rows, array &$log): ConnectionInterface
{
    return new class ($rows, $log) implements ConnectionInterface
    {
        public function __construct(
            private readonly array $rows,
            /** @noinspection PhpPropertyOnlyWrittenInspection - Reference property modifies external variable */
            private array &$log,
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
            $this->log[] = ['sql' => $sql, 'bindings' => $bindings];

            return $this->rows;
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

        public function driverName(): string
        {
            return 'sqlite';
        }
    };
}

describe('Repository exists family', function (): void {
    it('returns true from exists when a row with the id is present', function (): void {
        $log = [];
        $connection = makeExistsConnection([['1' => 1]], $log);
        $repository = new ExistUserRepository($connection, new EntityMetadataFactory(), new EntityHydrator());

        expect($repository->exists(1))->toBeTrue();
    });

    it('returns false from exists when no row has the id', function (): void {
        $log = [];
        $connection = makeExistsConnection([], $log);
        $repository = new ExistUserRepository($connection, new EntityMetadataFactory(), new EntityHydrator());

        expect($repository->exists(999))->toBeFalse();
    });

    it('probes exists with SELECT 1 and LIMIT 1 and does not hydrate an entity', function (): void {
        $log = [];
        $connection = makeExistsConnection([['1' => 1]], $log);
        $repository = new ExistUserRepository($connection, new EntityMetadataFactory(), new EntityHydrator());

        $repository->exists(42);

        expect($log)->toHaveCount(1)
            ->and($log[0]['sql'])->toStartWith('SELECT 1 FROM')
            ->and($log[0]['sql'])->toContain('LIMIT 1')
            ->and($log[0]['sql'])->not->toContain('SELECT *');
    });

    it('returns true from existsBy when criteria match a row', function (): void {
        $log = [];
        $connection = makeExistsConnection([['1' => 1]], $log);
        $repository = new ExistUserRepository($connection, new EntityMetadataFactory(), new EntityHydrator());

        expect($repository->existsBy(['email' => 'alice@example.com']))->toBeTrue();
    });

    it('returns false from existsBy when criteria match nothing', function (): void {
        $log = [];
        $connection = makeExistsConnection([], $log);
        $repository = new ExistUserRepository($connection, new EntityMetadataFactory(), new EntityHydrator());

        expect($repository->existsBy(['email' => 'nobody@example.com']))->toBeFalse();
    });

    it('probes existsBy with SELECT 1 and LIMIT 1', function (): void {
        $log = [];
        $connection = makeExistsConnection([], $log);
        $repository = new ExistUserRepository($connection, new EntityMetadataFactory(), new EntityHydrator());

        $repository->existsBy(['email' => 'alice@example.com']);

        expect($log)->toHaveCount(1)
            ->and($log[0]['sql'])->toStartWith('SELECT 1 FROM')
            ->and($log[0]['sql'])->toContain('LIMIT 1')
            ->and($log[0]['sql'])->not->toContain('SELECT *');
    });

    it('returns true from isColumnUnique when no other row holds the value', function (): void {
        $log = [];
        $connection = makeExistsConnection([], $log);
        $repository = new ExistUserRepository($connection, new EntityMetadataFactory(), new EntityHydrator());

        expect($repository->isEmailUnique('unique@example.com'))->toBeTrue();
    });

    it('returns false from isColumnUnique when another row holds the value', function (): void {
        $log = [];
        $connection = makeExistsConnection([['1' => 1]], $log);
        $repository = new ExistUserRepository($connection, new EntityMetadataFactory(), new EntityHydrator());

        expect($repository->isEmailUnique('taken@example.com'))->toBeFalse();
    });

    it('excludes the given id from the isColumnUnique uniqueness check', function (): void {
        $log = [];
        $connection = makeExistsConnection([], $log);
        $repository = new ExistUserRepository($connection, new EntityMetadataFactory(), new EntityHydrator());

        $repository->isEmailUnique('alice@example.com', 5);

        expect($log)->toHaveCount(1)
            ->and($log[0]['sql'])->toContain('id != ?')
            ->and($log[0]['bindings'])->toBe(['alice@example.com', 5]);
    });

    it('probes isColumnUnique with SELECT 1 and LIMIT 1 instead of SELECT star', function (): void {
        $log = [];
        $connection = makeExistsConnection([], $log);
        $repository = new ExistUserRepository($connection, new EntityMetadataFactory(), new EntityHydrator());

        $repository->isEmailUnique('alice@example.com');

        expect($log)->toHaveCount(1)
            ->and($log[0]['sql'])->toStartWith('SELECT 1 FROM')
            ->and($log[0]['sql'])->toContain('LIMIT 1')
            ->and($log[0]['sql'])->not->toContain('SELECT *');
    });
});
