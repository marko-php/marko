<?php

declare(strict_types=1);

namespace Marko\Database\Sqlite\Tests\Introspection;

use Marko\Database\Config\DatabaseConfig;
use Marko\Database\Config\DatabaseTimezoneConfig;
use Marko\Database\Diff\DiffCalculator;
use Marko\Database\Diff\ExpressionDefaultCanonicalizer;
use Marko\Database\Diff\SchemaDiff;
use Marko\Database\Exceptions\ExpressionDefaultProbeException;
use Marko\Database\Introspection\ExpressionDefaultMatcherInterface;
use Marko\Database\Introspection\IntrospectorInterface;
use Marko\Database\Schema\Column;
use Marko\Database\Schema\Expression;
use Marko\Database\Schema\ForeignKey;
use Marko\Database\Schema\Index;
use Marko\Database\Schema\IndexType;
use Marko\Database\Schema\Literal;
use Marko\Database\Schema\Table;
use Marko\Database\Sqlite\Connection\SqliteConnection;
use Marko\Database\Sqlite\Introspection\SqliteIntrospector;
use Marko\Database\Sqlite\Sql\SqliteGenerator;

/**
 * Run the generator's CREATE statements for the tables on a fresh in-memory database.
 *
 * @param list<Table> $tables
 */
function introspectorConnection(
    array $tables = [],
): SqliteConnection {
    $connection = new SqliteConnection(DatabaseConfig::fromArray(['driver' => 'sqlite', 'database' => ':memory:']));
    $generator = new SqliteGenerator(DatabaseTimezoneConfig::fromName('UTC'));

    foreach ($generator->generateUp(new SchemaDiff(tablesToCreate: $tables)) as $statement) {
        $connection->execute($statement);
    }

    return $connection;
}

/**
 * @param array<int, Column> $columns
 */
function introspectorTable(
    string $name,
    array $columns,
): Table {
    return new Table(name: $name, columns: $columns);
}

describe('SqliteIntrospector tables', function (): void {
    it('lists user tables excluding sqlite internal and rebuild temp tables', function (): void {
        $connection = introspectorConnection([
            introspectorTable('users', [new Column('id', 'integer', primaryKey: true, autoIncrement: true)]),
            introspectorTable('posts', [new Column('id', 'integer', primaryKey: true, autoIncrement: true)]),
        ]);
        $connection->execute('CREATE TABLE "__marko_rebuild_users" ("id" INTEGER)');
        $connection->execute('CREATE TABLE "__marko_fk_guard" ("id" INTEGER)');
        $connection->execute('CREATE TABLE "markoXrebuild_keep" ("id" INTEGER)');

        $introspector = new SqliteIntrospector($connection);

        expect($introspector)->toBeInstanceOf(IntrospectorInterface::class)
            ->and($introspector->getTables())->toBe(['markoXrebuild_keep', 'posts', 'users'])
            ->and($introspector->getTable('users'))->toBeInstanceOf(Table::class)
            ->and($introspector->getTable('missing'))->toBeNull();
    });

    it('reports whether a table exists', function (): void {
        $introspector = new SqliteIntrospector(introspectorConnection([
            introspectorTable('users', [new Column('id', 'integer', primaryKey: true, autoIncrement: true)]),
        ]));

        expect($introspector->tableExists('users'))->toBeTrue()
            ->and($introspector->tableExists('sqlite_sequence'))->toBeFalse()
            ->and($introspector->tableExists('missing'))->toBeFalse();
    });
});

describe('SqliteIntrospector columns', function (): void {
    it('maps column types nullability and length back to the generator\'s column definitions', function (): void {
        $entityColumns = [
            new Column('id', 'integer', primaryKey: true, autoIncrement: true),
            new Column('name', 'varchar', length: 100),
            new Column('bio', 'text', nullable: true),
            new Column('age', 'smallint'),
            new Column('views', 'bigint'),
            new Column('active', 'boolean'),
            new Column('price', 'decimal'),
            new Column('ratio', 'float'),
            new Column('score', 'double'),
            new Column('meta', 'json'),
            new Column('token', 'uuid'),
            new Column('data', 'blob'),
            new Column('born', 'date'),
            new Column('alarm', 'time'),
            new Column('seen_at', 'timestamp'),
            new Column('created_at', 'datetime'),
            new Column('kind', 'enum', length: 20),
        ];
        $introspector = new SqliteIntrospector(introspectorConnection([introspectorTable('t', $entityColumns)]));

        $columns = $introspector->getColumns('t');

        expect($columns)->toHaveCount(count($entityColumns));

        foreach ($entityColumns as $index => $entity) {
            expect($columns[$index]->equals($entity))->toBeTrue("column $entity->name differs");
        }

        expect($columns[1]->length)->toBe(100)
            ->and($columns[2]->nullable)->toBeTrue()
            ->and($columns[3]->nullable)->toBeFalse()
            ->and($columns[6]->length)->toBeNull();
    });

    it('detects an autoincrement integer primary key', function (): void {
        $introspector = new SqliteIntrospector(introspectorConnection([
            introspectorTable('t', [
                new Column('id', 'integer', primaryKey: true, autoIncrement: true),
                new Column('code', 'integer', primaryKey: false),
            ]),
        ]));

        [$id, $code] = $introspector->getColumns('t');

        expect($id->primaryKey)->toBeTrue()
            ->and($id->autoIncrement)->toBeTrue()
            ->and($id->type)->toBe('integer')
            ->and($id->nullable)->toBeFalse()
            ->and($code->primaryKey)->toBeFalse()
            ->and($code->autoIncrement)->toBeFalse();
    });

    it('does not report autoincrement for a non-autoincrement integer primary key', function (): void {
        $connection = introspectorConnection();
        $connection->execute('CREATE TABLE "plain" ("id" INTEGER PRIMARY KEY)');

        $id = new SqliteIntrospector($connection)->getColumns('plain')[0];

        expect($id->primaryKey)->toBeTrue()
            ->and($id->autoIncrement)->toBeFalse();
    });

    it('parses literal column defaults', function (): void {
        $introspector = new SqliteIntrospector(introspectorConnection([
            introspectorTable('t', [
                new Column('status', 'varchar', length: 20, default: "it's"),
                new Column('count', 'integer', default: 5),
                new Column('rate', 'float', default: 1.5),
                new Column('active', 'boolean', default: true),
                new Column('off', 'boolean', default: false),
                new Column('note', 'text', nullable: true),
                new Column('stamp', 'timestamp', default: new Expression('CURRENT_TIMESTAMP')),
                new Column('label', 'varchar', length: 20, default: new Literal('NOW()')),
            ]),
        ]));

        $defaults = array_column($introspector->getColumns('t'), 'default', 'name');

        expect($defaults['status'])->toBe("it's")
            ->and($defaults['count'])->toBe(5)
            ->and($defaults['rate'])->toBe(1.5)
            ->and($defaults['active'])->toBeTrue()
            ->and($defaults['off'])->toBeFalse()
            ->and($defaults['note'])->toBeNull()
            ->and($defaults['stamp'])->toEqual(new Expression('CURRENT_TIMESTAMP'))
            ->and($defaults['label'])->toEqual(new Literal('NOW()'));
    });
});

describe('SqliteIntrospector primary keys', function (): void {
    it('returns composite primary key columns in key order', function (): void {
        $connection = introspectorConnection();
        $connection->execute(
            'CREATE TABLE "pivot" ("a" INTEGER NOT NULL, "b" INTEGER NOT NULL, "c" TEXT, PRIMARY KEY ("b", "a"))',
        );
        $connection->execute('CREATE TABLE "nokey" ("a" INTEGER)');
        $introspector = new SqliteIntrospector($connection);

        expect($introspector->getPrimaryKey('pivot'))->toBe(['b', 'a'])
            ->and($introspector->getPrimaryKey('nokey'))->toBeEmpty();
    });

    it('introspects a bigint auto-increment primary key as bigint from the marko type comment', function (): void {
        $entity = new Column('id', 'bigint', primaryKey: true, autoIncrement: true);
        $other = new Column('small_id', 'smallint');
        $introspector = new SqliteIntrospector(introspectorConnection([introspectorTable('t', [$entity, $other])]));

        $id = $introspector->getColumns('t')[0];

        expect($id->type)->toBe('bigint')
            ->and($id->equals($entity))->toBeTrue();
    });

    it('falls back to integer when an autoincrement key has no marko type comment', function (): void {
        $connection = introspectorConnection();
        $connection->execute('CREATE TABLE "legacy" ("id" INTEGER PRIMARY KEY AUTOINCREMENT)');

        $id = new SqliteIntrospector($connection)->getColumns('legacy')[0];

        expect($id->type)->toBe('integer')
            ->and($id->autoIncrement)->toBeTrue();
    });
});

describe('SqliteIntrospector indexes', function (): void {
    it('returns unique and composite indexes with columns in order', function (): void {
        $connection = introspectorConnection([
            new Table(
                name: 'posts',
                columns: [
                    new Column('id', 'integer', primaryKey: true, autoIncrement: true),
                    new Column('slug', 'varchar', length: 100, unique: true),
                    new Column('author_id', 'integer'),
                    new Column('created_at', 'datetime'),
                ],
                indexes: [new Index('posts_author_created_index', ['created_at', 'author_id'])],
            ),
        ]);
        $connection->execute('CREATE TABLE "legacy" ("a" TEXT, UNIQUE ("a"))');
        $introspector = new SqliteIntrospector($connection);

        $indexes = array_column($introspector->getIndexes('posts'), null, 'name');
        $legacy = $introspector->getIndexes('legacy');

        expect($indexes)->toHaveCount(2)
            ->and($indexes['posts_author_created_index']->columns)->toBe(['created_at', 'author_id'])
            ->and($indexes['posts_author_created_index']->type)->toBe(IndexType::Btree)
            ->and($indexes['posts_slug_unique']->columns)->toBe(['slug'])
            ->and($indexes['posts_slug_unique']->type)->toBe(IndexType::Unique)
            ->and($indexes['posts_slug_unique']->constraint)->toBeFalse()
            ->and($legacy)->toHaveCount(1)
            ->and($legacy[0]->type)->toBe(IndexType::Unique)
            ->and($legacy[0]->constraint)->toBeTrue();
    });

    it('returns a partial index with its where predicate', function (): void {
        $connection = introspectorConnection([
            new Table(
                name: 'posts',
                columns: [
                    new Column('id', 'integer', primaryKey: true),
                    new Column('deleted_at', 'datetime', nullable: true),
                ],
                indexes: [new Index('posts_live_index', ['id'], where: '"deleted_at" IS NULL')],
            ),
        ]);

        $index = new SqliteIntrospector($connection)->getIndexes('posts')[0];

        expect($index->where)->toBe('"deleted_at" IS NULL')
            ->and($index->columns)->toBe(['id']);
    });

    it('excludes sqlite auto indexes for primary keys', function (): void {
        $connection = introspectorConnection();
        $connection->execute('CREATE TABLE "pivot" ("a" TEXT NOT NULL, "b" TEXT NOT NULL, PRIMARY KEY ("a", "b"))');

        expect(new SqliteIntrospector($connection)->getIndexes('pivot'))->toBeEmpty();
    });
});

describe('SqliteIntrospector foreign keys', function (): void {
    it('returns foreign keys with referenced table columns and actions', function (): void {
        $connection = introspectorConnection([
            new Table('users', [new Column('id', 'integer', primaryKey: true)]),
            new Table(
                name: 'posts',
                columns: [
                    new Column('id', 'integer', primaryKey: true),
                    new Column('user_id', 'integer'),
                    new Column('pa', 'integer'),
                    new Column('pb', 'integer'),
                ],
                foreignKeys: [
                    new ForeignKey('fk_posts_user', ['user_id'], 'users', ['id'], 'CASCADE', 'SET NULL'),
                    new ForeignKey('fk_posts_pair', ['pa', 'pb'], 'pairs', ['a', 'b']),
                ],
            ),
        ]);
        $connection->execute('CREATE TABLE "pairs" ("a" INTEGER, "b" INTEGER, PRIMARY KEY ("a", "b"))');
        $connection->execute('CREATE TABLE "bare" ("uid" INTEGER REFERENCES "users")');
        $introspector = new SqliteIntrospector($connection);

        $keys = array_column($introspector->getForeignKeys('posts'), null, 'name');
        $bare = $introspector->getForeignKeys('bare');

        expect($keys)->toHaveCount(2)
            ->and($keys['fk_posts_user']->columns)->toBe(['user_id'])
            ->and($keys['fk_posts_user']->referencedTable)->toBe('users')
            ->and($keys['fk_posts_user']->referencedColumns)->toBe(['id'])
            ->and($keys['fk_posts_user']->onDelete)->toBe('CASCADE')
            ->and($keys['fk_posts_user']->onUpdate)->toBe('SET NULL')
            ->and($keys['fk_posts_pair']->columns)->toBe(['pa', 'pb'])
            ->and($keys['fk_posts_pair']->referencedColumns)->toBe(['a', 'b'])
            ->and($bare[0]->name)->toBe('fk_bare_uid')
            ->and($bare[0]->referencedColumns)->toBe(['id']);
    });
});

describe('SqliteIntrospector expression defaults', function (): void {
    it('matches a stored expression default against the entity expression', function (): void {
        $connection = introspectorConnection([
            introspectorTable('events', [
                new Column('id', 'integer', primaryKey: true),
                new Column('code', 'varchar', length: 20, default: new Expression("lower('ABC')")),
            ]),
        ]);
        $introspector = new SqliteIntrospector($connection);

        expect($introspector)->toBeInstanceOf(ExpressionDefaultMatcherInterface::class)
            ->and($introspector->matchesStoredDefault('events', 'code', new Expression("lower('ABC')")))->toBeTrue()
            ->and($introspector->matchesStoredDefault('events', 'code', new Expression("upper('ABC')")))->toBeFalse()
            ->and($introspector->matchesStoredDefault('events', 'id', new Expression('1')))->toBeFalse()
            ->and($introspector->tableExists('__marko_default_probe'))->toBeFalse();
    });

    it('throws when the database rejects the probe expression', function (): void {
        $connection = introspectorConnection([
            introspectorTable(
                'events',
                [new Column('code', 'varchar', length: 20, default: new Expression('lower(1)'))],
            ),
        ]);

        expect(fn () => new SqliteIntrospector($connection)->matchesStoredDefault(
            'events',
            'code',
            new Expression('bogus_fn('),
        ))->toThrow(ExpressionDefaultProbeException::class);
    });
});

describe('SqliteIntrospector round trip', function (): void {
    it(
        'produces no schema diff after migrating a table with indexes foreign keys and expression defaults',
        function (): void {
            $users = new Table('users', [
                new Column('id', 'integer', primaryKey: true, autoIncrement: true),
                new Column('email', 'varchar', length: 190, unique: true),
            ]);
            $posts = new Table(
                name: 'posts',
                columns: [
                    new Column('id', 'integer', primaryKey: true, autoIncrement: true),
                    new Column('user_id', 'integer'),
                    new Column('code', 'varchar', length: 20, default: new Expression("lower('ABC')")),
                    new Column('stamp', 'datetime', default: new Expression('CURRENT_TIMESTAMP')),
                    new Column('deleted_at', 'datetime', nullable: true),
                ],
                indexes: [
                    new Index('posts_user_code_index', ['user_id', 'code']),
                    new Index('posts_live_index', ['code'], IndexType::Unique, '"deleted_at" IS NULL'),
                ],
                foreignKeys: [new ForeignKey('fk_posts_user_id', ['user_id'], 'users', ['id'], 'CASCADE')],
            );
            $introspector = new SqliteIntrospector(introspectorConnection([$users, $posts]));
            $database = [];
            foreach ($introspector->getTables() as $name) {
                $database[$name] = $introspector->getTable($name);
            }
            $entity = ['users' => $users, 'posts' => $posts];

            $canonical = new ExpressionDefaultCanonicalizer($introspector)->canonicalize($entity, $database);
            $diff = new DiffCalculator()->calculate($canonical, $database);

            expect($diff->isEmpty())->toBeTrue();
        },
    );
});
