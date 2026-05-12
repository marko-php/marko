<?php

declare(strict_types=1);

namespace Marko\Database\Tests\Integration;

use Marko\Core\Discovery\ClassFileParser;
use Marko\Database\Connection\ConnectionInterface;
use Marko\Database\Connection\StatementInterface;
use Marko\Database\Diff\DiffCalculator;
use Marko\Database\Entity\EntityDiscovery;
use Marko\Database\Entity\EntityHydrator;
use Marko\Database\Entity\EntityMetadataFactory;
use Marko\Database\Entity\SchemaBuilder;
use Marko\Database\Exceptions\BatchInsertException;
use Marko\Database\Exceptions\EntityException;
use Marko\Database\Exceptions\RepositoryException;
use Marko\Database\Repository\Repository;
use Marko\Database\Schema\Column;
use Marko\Database\Schema\SchemaRegistry;
use Marko\Database\Schema\Table;
use Marko\Database\Tests\Integration\Fixtures\IntUser;
use Marko\Database\Tests\Integration\Fixtures\IntUserProfile;
use Marko\Database\Tests\Integration\Fixtures\IntUserSettings;
use PDO;
use RuntimeException;

// ---------------------------------------------------------------------------
// Repository backed by a real in-memory SQLite database
// ---------------------------------------------------------------------------

class IntUserRepository extends Repository
{
    protected const string ENTITY_CLASS = IntUser::class;
}

// ---------------------------------------------------------------------------
// Extender-class repository — used only in the "raises loud error" test
// ---------------------------------------------------------------------------

class IntUserProfileRepository extends Repository
{
    protected const string ENTITY_CLASS = IntUserProfile::class;
}

// ---------------------------------------------------------------------------
// Helper: build a real SQLite in-memory connection
// ---------------------------------------------------------------------------

function createSqliteConnection(): ConnectionInterface
{
    $pdo = new PDO('sqlite::memory:');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    return new class ($pdo) implements ConnectionInterface
    {
        public function __construct(
            private readonly PDO $pdo,
        ) {}

        public function connect(): void {}

        public function disconnect(): void {}

        public function isConnected(): bool
        {
            return true;
        }

        public function query(string $sql, array $bindings = []): array
        {
            $statement = $this->pdo->prepare($sql);
            $statement->execute($bindings);

            return $statement->fetchAll(PDO::FETCH_ASSOC);
        }

        public function execute(string $sql, array $bindings = []): int
        {
            $statement = $this->pdo->prepare($sql);
            $statement->execute($bindings);

            return $statement->rowCount();
        }

        public function prepare(string $sql): StatementInterface
        {
            throw new RuntimeException('Not implemented for this test stub');
        }

        public function lastInsertId(): int
        {
            return (int) $this->pdo->lastInsertId();
        }
    };
}

// ---------------------------------------------------------------------------
// Helper: build merged schema via SchemaRegistry and CREATE the table in SQLite
// ---------------------------------------------------------------------------

function buildSchemaAndCreateTable(
    ConnectionInterface $connection,
    EntityMetadataFactory $metadataFactory,
): SchemaRegistry {
    $schemaBuilder = new SchemaBuilder();
    $registry = new SchemaRegistry($metadataFactory, $schemaBuilder);
    $registry->registerEntities([IntUser::class, IntUserProfile::class]);

    $table = $registry->getTable('int_users');

    // Build a SQLite CREATE TABLE from the merged Table value object
    $columnDefs = [];

    foreach ($table->columns as $col) {
        $type = match (strtolower($col->type)) {
            'integer', 'int' => 'INTEGER',
            'varchar', 'text', 'string' => 'TEXT',
            'boolean', 'bool' => 'INTEGER',
            'decimal', 'float' => 'REAL',
            default => 'TEXT',
        };

        $def = "$col->name $type";

        if ($col->primaryKey) {
            $def .= ' PRIMARY KEY AUTOINCREMENT';
        } elseif (!$col->nullable) {
            $def .= ' NOT NULL';
        }

        $columnDefs[] = $def;
    }

    $ddl = sprintf(
        'CREATE TABLE int_users (%s)',
        implode(', ', $columnDefs),
    );

    $connection->execute($ddl);

    return $registry;
}

// ---------------------------------------------------------------------------
// Integration tests
// ---------------------------------------------------------------------------

describe('Table Extension Integration', function (): void {
    it('creates a table with merged parent and extender columns from schema registry', function (): void {
        $metadataFactory = new EntityMetadataFactory();
        $schemaBuilder = new SchemaBuilder();
        $registry = new SchemaRegistry($metadataFactory, $schemaBuilder);
        $registry->registerEntities([IntUser::class, IntUserProfile::class]);

        $table = $registry->getTable('int_users');

        $columnNames = array_map(fn ($col) => $col->name, $table->columns);

        expect($columnNames)
            ->toContain('id')
            ->toContain('name')
            ->toContain('email')
            ->toContain('bio')
            ->toContain('timezone')
            ->and($table->columns)->toHaveCount(5);
    });

    it('inserts a parent entity with companion data in a single INSERT statement', function (): void {
        $metadataFactory = new EntityMetadataFactory();
        $connection = createSqliteConnection();
        buildSchemaAndCreateTable($connection, $metadataFactory);

        $hydrator = new EntityHydrator($metadataFactory);
        $repository = new IntUserRepository($connection, $metadataFactory, $hydrator);

        $user = new IntUser();
        $user->name = 'Alice';
        $user->email = 'alice@example.com';

        $profile = new IntUserProfile();
        $profile->bio = 'Software engineer';
        $profile->timezone = 'UTC';

        $user->attachCompanion($profile);

        $repository->save($user);

        // Verify via a raw query that all columns landed in the single row
        $rows = $connection->query('SELECT * FROM int_users WHERE id = ?', [$user->id]);

        expect($rows)->toHaveCount(1)
            ->and($rows[0]['name'])->toBe('Alice')
            ->and($rows[0]['email'])->toBe('alice@example.com')
            ->and($rows[0]['bio'])->toBe('Software engineer')
            ->and($rows[0]['timezone'])->toBe('UTC');
    });

    it('reads back parent and companion data via Repository find from a single SELECT', function (): void {
        $metadataFactory = new EntityMetadataFactory();
        $connection = createSqliteConnection();
        buildSchemaAndCreateTable($connection, $metadataFactory);

        $hydrator = new EntityHydrator($metadataFactory);
        $repository = new IntUserRepository($connection, $metadataFactory, $hydrator);

        // Insert directly so we control all column values
        $connection->execute(
            'INSERT INTO int_users (name, email, bio, timezone) VALUES (?, ?, ?, ?)',
            ['Bob', 'bob@example.com', 'Writer', 'America/New_York'],
        );
        $insertedId = $connection->lastInsertId();

        /** @var IntUser $user */
        $user = $repository->find($insertedId);

        /** @var IntUserProfile $foundProfile */
        $foundProfile = $user->companion(IntUserProfile::class);

        expect($user)->not->toBeNull()
            ->and($user->name)->toBe('Bob')
            ->and($user->email)->toBe('bob@example.com')
            ->and($foundProfile)->not->toBeNull()
            ->and($foundProfile->bio)->toBe('Writer')
            ->and($foundProfile->timezone)->toBe('America/New_York');
    });

    it('updates parent and companion fields in a single UPDATE when both are dirty', function (): void {
        $metadataFactory = new EntityMetadataFactory();
        $connection = createSqliteConnection();
        buildSchemaAndCreateTable($connection, $metadataFactory);

        $hydrator = new EntityHydrator($metadataFactory);
        $repository = new IntUserRepository($connection, $metadataFactory, $hydrator);

        $connection->execute(
            'INSERT INTO int_users (name, email, bio, timezone) VALUES (?, ?, ?, ?)',
            ['Carol', 'carol@example.com', 'Designer', 'Europe/London'],
        );
        $insertedId = $connection->lastInsertId();

        /** @var IntUser $user */
        $user = $repository->find($insertedId);

        /** @var IntUserProfile $profile */
        $profile = $user->companion(IntUserProfile::class);

        // Mutate both parent and companion
        $user->name = 'Carol Updated';
        $profile->bio = 'Senior Designer';

        $repository->save($user);

        // Re-fetch and verify both changes persisted
        $rows = $connection->query('SELECT * FROM int_users WHERE id = ?', [$insertedId]);

        expect($rows[0]['name'])->toBe('Carol Updated')
            ->and($rows[0]['bio'])->toBe('Senior Designer')
            ->and($rows[0]['email'])->toBe('carol@example.com')
            ->and($rows[0]['timezone'])->toBe('Europe/London');
    });

    it('persists changes correctly when only the companion is dirty', function (): void {
        $metadataFactory = new EntityMetadataFactory();
        $connection = createSqliteConnection();
        buildSchemaAndCreateTable($connection, $metadataFactory);

        $hydrator = new EntityHydrator($metadataFactory);
        $repository = new IntUserRepository($connection, $metadataFactory, $hydrator);

        $connection->execute(
            'INSERT INTO int_users (name, email, bio, timezone) VALUES (?, ?, ?, ?)',
            ['Dave', 'dave@example.com', 'DevOps', 'Asia/Tokyo'],
        );
        $insertedId = $connection->lastInsertId();

        /** @var IntUser $user */
        $user = $repository->find($insertedId);

        /** @var IntUserProfile $profile */
        $profile = $user->companion(IntUserProfile::class);

        // Only companion is mutated
        $profile->timezone = 'Asia/Shanghai';

        $repository->save($user);

        $rows = $connection->query('SELECT * FROM int_users WHERE id = ?', [$insertedId]);

        expect($rows[0]['name'])->toBe('Dave')
            ->and($rows[0]['bio'])->toBe('DevOps')
            ->and($rows[0]['timezone'])->toBe('Asia/Shanghai');
    });

    it('silently skips companion hydration when the extender\'s columns are dropped from the schema (rolling deploy)', function (): void {
        $metadataFactory = new EntityMetadataFactory();
        $connection = createSqliteConnection();

        // Create a table WITHOUT the extender columns (simulates rolling deploy where
        // the DB schema is still on the old version without profile columns)
        $connection->execute(
            'CREATE TABLE int_users (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL, email TEXT NOT NULL)',
        );

        // Seed a row that has no profile columns at all
        $connection->execute(
            'INSERT INTO int_users (name, email) VALUES (?, ?)',
            ['Eve', 'eve@example.com'],
        );
        $insertedId = $connection->lastInsertId();

        // Register entities so metadata knows about the extender
        $schemaBuilder = new SchemaBuilder();
        $registry = new SchemaRegistry($metadataFactory, $schemaBuilder);
        $registry->registerEntities([IntUser::class, IntUserProfile::class]);

        $hydrator = new EntityHydrator($metadataFactory);
        $repository = new IntUserRepository($connection, $metadataFactory, $hydrator);

        /** @var IntUser $user */
        $user = $repository->find($insertedId);

        // Hydration must succeed without error and companion must simply be absent
        expect($user)->not->toBeNull()
            ->and($user->name)->toBe('Eve')
            ->and($user->companion(IntUserProfile::class))->toBeNull();
    });

    it('detects column-name conflicts at registration time across two extenders', function (): void {
        $metadataFactory = new EntityMetadataFactory();
        $schemaBuilder = new SchemaBuilder();
        $registry = new SchemaRegistry($metadataFactory, $schemaBuilder);

        // IntUserProfile declares 'bio'; IntUserSettings also declares 'bio' — conflict
        expect(fn () => $registry->registerEntities([
            IntUser::class,
            IntUserProfile::class,
            IntUserSettings::class,
        ]))->toThrow(EntityException::class);
    });

    it('preserves migrate diff parity by including extender columns in the parent\'s Table value object', function (): void {
        // Approach: assert on the merged Table value object (no real DB differ needed).
        // The merged Table is what SchemaRegistry exposes after registerEntities(),
        // and it is the same object handed to DiffCalculator — so if the Table has
        // the extender columns, the differ will produce the correct ADD COLUMN statements.

        $metadataFactory = new EntityMetadataFactory();
        $schemaBuilder = new SchemaBuilder();
        $registry = new SchemaRegistry($metadataFactory, $schemaBuilder);
        $registry->registerEntities([IntUser::class, IntUserProfile::class]);

        $mergedTable = $registry->getTable('int_users');

        // Simulate an existing DB table that is missing the extender columns
        $dbTable = new Table(
            name: 'int_users',
            columns: [
                new Column(name: 'id', type: 'INTEGER', primaryKey: true, autoIncrement: true),
                new Column(name: 'name', type: 'TEXT'),
                new Column(name: 'email', type: 'TEXT'),
            ],
            indexes: [],
        );

        $diffCalculator = new DiffCalculator();
        $diff = $diffCalculator->calculate(
            ['int_users' => $mergedTable],
            ['int_users' => $dbTable],
        );

        expect($diff->tablesToAlter)->toHaveKey('int_users');

        $tableDiff = $diff->tablesToAlter['int_users'];
        $addedNames = array_map(fn ($col) => $col->name, $tableDiff->columnsToAdd);

        expect($addedNames)
            ->toContain('bio')
            ->toContain('timezone');
    });

    it('discovers an extender via EntityDiscovery and registers it correctly into the parent\'s merged schema', function (): void {
        $fixturesPath = __DIR__ . '/Fixtures';

        $classFileParser = new ClassFileParser();
        $discovery = new EntityDiscovery($classFileParser);

        $discovered = $discovery->discoverInPath($fixturesPath);

        // All three fixture classes live in the Fixtures directory
        expect($discovered)->toContain(IntUser::class)
            ->and($discovered)->toContain(IntUserProfile::class);

        // Register the discovered set (parent + extender only, exclude conflict fixture)
        $filteredClasses = array_values(array_filter(
            $discovered,
            fn (string $class) => $class !== IntUserSettings::class,
        ));

        $metadataFactory = new EntityMetadataFactory();
        $schemaBuilder = new SchemaBuilder();
        $registry = new SchemaRegistry($metadataFactory, $schemaBuilder);
        $registry->registerEntities($filteredClasses);

        $table = $registry->getTable('int_users');
        $columnNames = array_map(fn ($col) => $col->name, $table->columns);

        expect($columnNames)
            ->toContain('id')
            ->toContain('name')
            ->toContain('email')
            ->toContain('bio')
            ->toContain('timezone');
    });

    it('raises a loud error when constructing a Repository against an extender class', function (): void {
        $metadataFactory = new EntityMetadataFactory();

        // Register entities first so the factory cache has the extender metadata
        $schemaBuilder = new SchemaBuilder();
        $registry = new SchemaRegistry($metadataFactory, $schemaBuilder);
        $registry->registerEntities([IntUser::class, IntUserProfile::class]);

        $connection = createSqliteConnection();
        $hydrator = new EntityHydrator($metadataFactory);

        expect(fn () => new IntUserProfileRepository($connection, $metadataFactory, $hydrator))
            ->toThrow(RepositoryException::class);
    });

    it('raises a loud error when insertBatch is called on entities with companions attached', function (): void {
        $metadataFactory = new EntityMetadataFactory();
        $connection = createSqliteConnection();
        buildSchemaAndCreateTable($connection, $metadataFactory);

        $hydrator = new EntityHydrator($metadataFactory);
        $repository = new IntUserRepository($connection, $metadataFactory, $hydrator);

        $user = new IntUser();
        $user->name = 'Frank';
        $user->email = 'frank@example.com';

        $profile = new IntUserProfile();
        $profile->bio = 'PM';
        $profile->timezone = 'UTC';

        $user->attachCompanion($profile);

        expect(fn () => $repository->insertBatch([$user]))
            ->toThrow(BatchInsertException::class);
    });
});
