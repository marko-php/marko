<?php

declare(strict_types=1);

use Marko\Database\Config\DatabaseTimezoneConfig;
use Marko\Database\Diff\SchemaDiff;
use Marko\Database\Diff\TableDiff;
use Marko\Database\Exceptions\MigrationException;
use Marko\Database\Schema\Column;
use Marko\Database\Schema\Expression;
use Marko\Database\Schema\ForeignKey;
use Marko\Database\Schema\Index;
use Marko\Database\Schema\IndexType;
use Marko\Database\Schema\Literal;
use Marko\Database\Schema\Table;
use Marko\Database\Sqlite\Sql\SqliteGenerator;

function sqliteGenerator(
    string $timezone = 'UTC',
): SqliteGenerator {
    return new SqliteGenerator(DatabaseTimezoneConfig::fromName($timezone));
}

/**
 * Execute statements on a fresh in-memory database and return its connection.
 *
 * @param list<string> $statements
 */
function sqliteExec(
    array $statements,
): PDO {
    $pdo = new PDO('sqlite::memory:', options: [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

    foreach ($statements as $statement) {
        $pdo->exec($statement);
    }

    return $pdo;
}

/**
 * @return list<string>
 */
function sqliteCreateStatements(
    Table $table,
    string $timezone = 'UTC',
): array {
    return sqliteGenerator($timezone)->generateUp(new SchemaDiff(tablesToCreate: [$table]));
}

class RebuildProbeSqliteGenerator extends SqliteGenerator
{
    public function needsRebuild(
        TableDiff $diff,
        bool $reverse = false,
    ): bool {
        return $this->requiresRebuild($diff, $reverse);
    }
}

function sqlitePostsTable(): Table
{
    return new Table(
        name: 'posts',
        columns: [
            new Column(name: 'id', type: 'integer', primaryKey: true, autoIncrement: true),
            new Column(name: 'title', type: 'string', length: 100),
            new Column(name: 'legacy', type: 'string', nullable: true),
        ],
    );
}

/**
 * A database holding the posts table and two rows.
 *
 * @param list<string> $extra
 */
function sqlitePostsDatabase(
    array $extra = [],
): PDO {
    $pdo = sqliteExec([...sqliteCreateStatements(sqlitePostsTable()), ...$extra]);
    $pdo->exec("INSERT INTO posts (title) VALUES ('one'), ('two')");

    return $pdo;
}

/**
 * @param list<string> $statements
 */
function sqliteRun(
    PDO $pdo,
    array $statements,
): void {
    foreach ($statements as $statement) {
        $pdo->exec($statement);
    }
}

function sqliteAlterDiff(
    TableDiff $diff,
): SchemaDiff {
    return new SchemaDiff(tablesToAlter: [$diff->tableName => $diff]);
}

describe('SqliteGenerator', function (): void {
    it('generates CREATE TABLE with declared types the introspector can map back and DROP TABLE', function (): void {
        $columns = [
            new Column(name: 'name', type: 'string', length: 100),
            new Column(name: 'default_name', type: 'string'),
            new Column(name: 'body', type: 'text', nullable: true),
            new Column(name: 'active', type: 'boolean'),
            new Column(name: 'price', type: 'decimal'),
            new Column(name: 'data', type: 'json'),
            new Column(name: 'uid', type: 'uuid'),
            new Column(name: 'raw', type: 'blob'),
            new Column(name: 'created', type: 'timestamp'),
        ];
        $table = new Table(name: 'items', columns: $columns);
        $generator = sqliteGenerator();

        $sql = $generator->generateCreateTable($table);
        $pdo = sqliteExec([$sql]);
        $declared = $pdo->query('SELECT name, type, "notnull" FROM pragma_table_info(\'items\')')
            ->fetchAll(PDO::FETCH_ASSOC);
        $types = array_column($declared, 'type', 'name');

        expect($sql)->toStartWith('CREATE TABLE "items"')
            ->and($sql)->not->toContain('STRICT')
            ->and($types)->toBe([
                'name' => 'VARCHAR(100)',
                'default_name' => 'VARCHAR(255)',
                'body' => 'TEXT',
                'active' => 'BOOLEAN',
                'price' => 'DECIMAL(10,2)',
                'data' => 'JSON',
                'uid' => 'UUID',
                'raw' => 'BLOB',
                'created' => 'TIMESTAMP',
            ])
            ->and((int) $declared[2]['notnull'])->toBe(0)
            ->and((int) $declared[0]['notnull'])->toBe(1)
            ->and($generator->generateDropTable('items'))->toBe('DROP TABLE "items"');

        $pdo->exec($generator->generateDropTable('items'));

        expect($pdo->query('SELECT count(*) FROM sqlite_schema WHERE name = \'items\'')->fetchColumn())->toBe(0);
    });

    it('maps every abstract column type to a declared type', function (): void {
        $types = array_keys(SqliteGenerator::TYPE_MAP);

        expect($types)->toContain(
            'integer',
            'int',
            'bigint',
            'smallint',
            'tinyint',
            'string',
            'text',
            'boolean',
            'bool',
            'datetime',
            'timestamp',
            'date',
            'time',
            'decimal',
            'float',
            'double',
            'json',
            'uuid',
            'binary',
            'blob',
            'enum',
        );
    });

    it('generates a table-level PRIMARY KEY for a composite primary key', function (): void {
        $table = new Table(name: 'post_tags', columns: [
            new Column(name: 'post_id', type: 'integer', primaryKey: true),
            new Column(name: 'tag_id', type: 'integer', primaryKey: true),
        ]);

        $sql = sqliteGenerator()->generateCreateTable($table);
        $pdo = sqliteExec([$sql]);
        $keys = $pdo->query('SELECT name FROM pragma_table_info(\'post_tags\') WHERE pk > 0 ORDER BY pk')
            ->fetchAll(PDO::FETCH_COLUMN);

        expect($sql)->toContain('PRIMARY KEY ("post_id", "tag_id")')
            ->and($sql)->not->toContain('"post_id" INTEGER NOT NULL PRIMARY KEY')
            ->and($keys)->toBe(['post_id', 'tag_id']);
    });

    it(
        'generates an INTEGER PRIMARY KEY AUTOINCREMENT column carrying a marko type comment for auto-increment primary keys',
        function (): void {
            $table = new Table(name: 'events', columns: [
                new Column(name: 'id', type: 'bigint', primaryKey: true, autoIncrement: true),
                new Column(name: 'label', type: 'string', nullable: true),
            ]);

            $sql = sqliteGenerator()->generateCreateTable($table);
            $pdo = sqliteExec([$sql]);
            $pdo->exec('INSERT INTO "events" ("label") VALUES (\'a\')');
            $pdo->exec('INSERT INTO "events" ("label") VALUES (\'b\')');
            $declared = $pdo->query(
                'SELECT type FROM pragma_table_info(\'events\') WHERE name = \'id\'',
            )->fetchColumn();
            $stored = $pdo->query('SELECT sql FROM sqlite_schema WHERE name = \'events\'')->fetchColumn();

            expect($sql)->toContain('"id" INTEGER /* marko:bigint */ PRIMARY KEY AUTOINCREMENT')
                ->and($declared)->toBe('INTEGER')
                ->and($stored)->toContain('/* marko:bigint */')
                ->and($pdo->query('SELECT max("id") FROM "events"')->fetchColumn())->toBe(2);
        },
    );

    it(
        'generates literal and parenthesised expression defaults and throws for expressions sqlite lacks',
        function (): void {
            $table = new Table(name: 'defaults', columns: [
                new Column(name: 'a', type: 'string', default: "it's"),
                new Column(name: 'b', type: 'boolean', default: true),
                new Column(name: 'c', type: 'integer', default: 5),
                new Column(name: 'd', type: 'string', default: new Literal('now()')),
                new Column(name: 'e', type: 'timestamp', default: new Expression('CURRENT_TIMESTAMP')),
                new Column(name: 'f', type: 'integer', default: new Expression('1 + 2')),
                new Column(name: 'g', type: 'date', default: 'CURRENT_DATE'),
            ]);

            $sql = sqliteGenerator()->generateCreateTable($table);
            $pdo = sqliteExec([$sql]);
            $pdo->exec('INSERT INTO "defaults" DEFAULT VALUES');
            $row = $pdo->query('SELECT * FROM "defaults"')->fetch(PDO::FETCH_ASSOC);

            expect($sql)->toContain("DEFAULT 'it''s'")
                ->toContain('DEFAULT 1')
                ->toContain("DEFAULT 'now()'")
                ->toContain('DEFAULT CURRENT_TIMESTAMP')
                ->toContain('DEFAULT (1 + 2)')
                ->toContain('DEFAULT CURRENT_DATE')
                ->and($row['a'])->toBe("it's")
                ->and($row['f'])->toBe(3);

            foreach (['NOW()', 'LOCALTIMESTAMP', 'CURRENT_TIMESTAMP(6)', 'gen_random_uuid()'] as $unsupported) {
                $bad = new Table(name: 't', columns: [
                    new Column(name: 'stamp', type: 'timestamp', default: new Expression($unsupported)),
                ]);

                expect(fn () => sqliteGenerator()->generateCreateTable($bad))
                    ->toThrow(MigrationException::class, 'stamp');
            }
        },
    );

    it('generates inline foreign keys with on delete and on update actions', function (): void {
        $parent = new Table(name: 'authors', columns: [
            new Column(name: 'id', type: 'integer', primaryKey: true, autoIncrement: true),
        ]);
        $child = new Table(
            name: 'books',
            columns: [
                new Column(name: 'id', type: 'integer', primaryKey: true, autoIncrement: true),
                new Column(name: 'author_id', type: 'integer'),
            ],
            foreignKeys: [
                new ForeignKey(
                    name: 'fk_books_author',
                    columns: ['author_id'],
                    referencedTable: 'authors',
                    referencedColumns: ['id'],
                    onDelete: 'CASCADE',
                    onUpdate: 'SET NULL',
                ),
            ],
        );

        $statements = [...sqliteCreateStatements($parent), ...sqliteCreateStatements($child)];
        $pdo = sqliteExec($statements);
        $fk = $pdo->query('SELECT "table", "from", "to", on_delete, on_update FROM pragma_foreign_key_list(\'books\')')
            ->fetch(PDO::FETCH_ASSOC);
        $stored = $pdo->query('SELECT sql FROM sqlite_schema WHERE name = \'books\'')->fetchColumn();

        expect($statements)->toHaveCount(2)
            ->and($stored)->toContain(
                'CONSTRAINT "fk_books_author" FOREIGN KEY ("author_id") REFERENCES "authors"("id")',
            )
            ->and($fk)->toBe([
                'table' => 'authors',
                'from' => 'author_id',
                'to' => 'id',
                'on_delete' => 'CASCADE',
                'on_update' => 'SET NULL',
            ]);
    });

    it(
        'generates unique columns, composite and partial indexes as named CREATE INDEX statements and drops them with DROP INDEX',
        function (): void {
            $table = new Table(
                name: 'users',
                columns: [
                    new Column(name: 'id', type: 'integer', primaryKey: true, autoIncrement: true),
                    new Column(name: 'email', type: 'string', unique: true),
                    new Column(name: 'a', type: 'integer'),
                    new Column(name: 'b', type: 'integer', nullable: true),
                ],
                indexes: [
                    new Index(name: 'idx_users_a_b', columns: ['a', 'b'], type: IndexType::Btree),
                    new Index(name: 'uniq_users_a', columns: ['a'], type: IndexType::Unique, where: '"b" IS NOT NULL'),
                ],
            );

            $statements = sqliteCreateStatements($table);
            $pdo = sqliteExec($statements);
            $names = $pdo->query('SELECT name FROM sqlite_schema WHERE type = \'index\' ORDER BY name')
                ->fetchAll(PDO::FETCH_COLUMN);
            $generator = sqliteGenerator();

            expect($statements[0])->not->toContain('UNIQUE')
                ->and($statements[1])->toBe('CREATE UNIQUE INDEX "users_email_unique" ON "users" ("email")')
                ->and($statements[2])->toBe('CREATE INDEX "idx_users_a_b" ON "users" ("a", "b")')
                ->and($statements[3])->toBe(
                    'CREATE UNIQUE INDEX "uniq_users_a" ON "users" ("a") WHERE "b" IS NOT NULL',
                )
                ->and($names)->toBe(['idx_users_a_b', 'uniq_users_a', 'users_email_unique'])
                ->and($generator->generateDropIndex('users', 'users_email_unique'))
                ->toBe('DROP INDEX "users_email_unique"');

            $pdo->exec($generator->generateDropIndex('users', 'users_email_unique'));

            expect(
                $pdo->query('SELECT count(*) FROM sqlite_schema WHERE name = \'users_email_unique\'')->fetchColumn(),
            )
                ->toBe(0);
        },
    );

    it(
        'throws a loud exception for fulltext indexes and for adding or dropping a foreign key outside a rebuild',
        function (): void {
            $generator = sqliteGenerator();
            $foreignKey = new ForeignKey(
                name: 'fk_x',
                columns: ['a'],
                referencedTable: 'other',
                referencedColumns: ['id'],
            );

            expect(fn () => $generator->generateAddIndex('posts', new Index('ft_body', ['body'], IndexType::Fulltext)))
                ->toThrow(MigrationException::class, 'ft_body')
                ->and(fn () => $generator->generateAddForeignKey('posts', $foreignKey))
                ->toThrow(MigrationException::class, 'rebuild')
                ->and(fn () => $generator->generateDropForeignKey('posts', 'fk_x'))
                ->toThrow(MigrationException::class, 'rebuild');
        },
    );

    it(
        'throws a migration exception for a CURRENT_TIMESTAMP default when database.timezone is not UTC',
        function (): void {
            $table = new Table(name: 'logs', columns: [
                new Column(name: 'logged_at', type: 'timestamp', default: new Expression('CURRENT_TIMESTAMP')),
            ]);

            expect(fn () => sqliteGenerator('America/New_York')->generateCreateTable($table))
                ->toThrow(MigrationException::class, 'logged_at')
                ->and(sqliteGenerator('UTC')->generateCreateTable($table))->toContain('DEFAULT CURRENT_TIMESTAMP');
        },
    );

    it('adds a nullable column with native ALTER TABLE ADD COLUMN', function (): void {
        $diff = sqliteAlterDiff(new TableDiff(
            tableName: 'posts',
            columnsToAdd: [
                new Column(name: 'subtitle', type: 'string', length: 100, nullable: true),
                new Column(name: 'views', type: 'integer', default: 0),
            ],
        ));
        $statements = sqliteGenerator()->generateUp($diff);
        $pdo = sqlitePostsDatabase();
        sqliteRun($pdo, $statements);

        expect($statements)->toBe([
            'ALTER TABLE "posts" ADD COLUMN "subtitle" VARCHAR(100)',
            'ALTER TABLE "posts" ADD COLUMN "views" INTEGER NOT NULL DEFAULT 0',
        ])->and($pdo->query('SELECT views, subtitle FROM posts')->fetch(PDO::FETCH_NUM))->toBe([0, null]);
    });

    it('adds a unique column with ADD COLUMN followed by a named unique index', function (): void {
        $diff = sqliteAlterDiff(new TableDiff(
            tableName: 'posts',
            columnsToAdd: [new Column(name: 'slug', type: 'string', nullable: true, unique: true)],
        ));
        $statements = sqliteGenerator()->generateUp($diff);
        $pdo = sqlitePostsDatabase();
        sqliteRun($pdo, $statements);
        $pdo->exec("UPDATE posts SET slug = 'a' WHERE id = 1");

        expect($statements)->toBe([
            'ALTER TABLE "posts" ADD COLUMN "slug" VARCHAR(255)',
            'CREATE UNIQUE INDEX "posts_slug_unique" ON "posts" ("slug")',
        ])->and(fn () => $pdo->exec("UPDATE posts SET slug = 'a' WHERE id = 2"))->toThrow(PDOException::class);
    });

    it('adds and drops indexes with CREATE INDEX and DROP INDEX', function (): void {
        $diff = sqliteAlterDiff(new TableDiff(
            tableName: 'posts',
            indexesToAdd: [
                new Index('idx_title', ['title']),
                new Index('uniq_title', ['title', 'legacy'], IndexType::Unique, where: 'legacy IS NOT NULL'),
            ],
            indexesToDrop: [new Index('idx_old', ['legacy'])],
        ));
        $statements = sqliteGenerator()->generateUp($diff);
        $pdo = sqlitePostsDatabase(['CREATE INDEX "idx_old" ON "posts" ("legacy")']);
        sqliteRun($pdo, $statements);
        $names = $pdo->query("SELECT name FROM sqlite_master WHERE type = 'index' ORDER BY name")
            ->fetchAll(PDO::FETCH_COLUMN);

        expect($statements)->toBe([
            'DROP INDEX "idx_old"',
            'CREATE INDEX "idx_title" ON "posts" ("title")',
            'CREATE UNIQUE INDEX "uniq_title" ON "posts" ("title", "legacy") WHERE legacy IS NOT NULL',
        ])->and($names)->toBe(['idx_title', 'uniq_title']);
    });

    it('drops an unindexed unconstrained column with native ALTER TABLE DROP COLUMN', function (): void {
        $current = sqlitePostsTable();
        $diff = sqliteAlterDiff(new TableDiff(
            tableName: 'posts',
            columnsToDrop: [$current->columns[2]],
            currentTable: $current,
        ));
        $statements = sqliteGenerator()->generateUp($diff);
        $pdo = sqlitePostsDatabase();
        sqliteRun($pdo, $statements);
        $columns = $pdo->query("SELECT name FROM pragma_table_info('posts')")->fetchAll(PDO::FETCH_COLUMN);

        expect($statements)->toBe(['ALTER TABLE "posts" DROP COLUMN "legacy"'])
            ->and($columns)->toBe(['id', 'title']);
    });

    it(
        'marks column modifications, foreign key changes and restricted column adds and drops as needing a table rebuild',
        function (): void {
            $generator = new RebuildProbeSqliteGenerator(DatabaseTimezoneConfig::fromName('UTC'));
            $legacy = new Column(name: 'legacy', type: 'string', nullable: true);
            $foreignKey = new ForeignKey('fk_user', ['legacy'], 'users', ['id']);
            $indexed = new Table(
                name: 'posts',
                columns: [new Column(name: 'id', type: 'integer', primaryKey: true), $legacy],
                indexes: [new Index('idx_legacy', ['legacy'])],
            );
            $partial = new Table(
                name: 'posts',
                columns: [new Column(name: 'id', type: 'integer', primaryKey: true), $legacy],
                indexes: [new Index('idx_id', ['id'], where: '"legacy" IS NOT NULL')],
            );
            $referencing = new Table(
                name: 'posts',
                columns: [new Column(name: 'id', type: 'integer', primaryKey: true), $legacy],
                foreignKeys: [$foreignKey],
            );
            $plain = sqlitePostsTable();
            $needs = fn (TableDiff $diff, bool $reverse = false): bool => $generator->needsRebuild($diff, $reverse);

            expect($needs(new TableDiff('posts', columnsToAdd: [new Column('a', 'string', nullable: true)])))
                ->toBeFalse()
                ->and($needs(new TableDiff('posts', columnsToModify: ['legacy' => $legacy])))->toBeTrue()
                ->and($needs(new TableDiff('posts', foreignKeysToAdd: [$foreignKey])))->toBeTrue()
                ->and($needs(new TableDiff('posts', foreignKeysToDrop: [$foreignKey])))->toBeTrue()
                ->and($needs(new TableDiff('posts', columnsToAdd: [new Column('a', 'string')])))->toBeTrue()
                ->and($needs(new TableDiff('posts', columnsToAdd: [new Column('a', 'integer', default: 1)])))
                ->toBeFalse()
                ->and($needs(new TableDiff('posts', columnsToAdd: [
                    new Column('a', 'timestamp', nullable: true, default: new Expression('CURRENT_TIMESTAMP')),
                ])))->toBeTrue()
                ->and($needs(new TableDiff('posts', columnsToAdd: [
                    new Column('a', 'integer', nullable: true, references: 'users.id'),
                ])))->toBeTrue()
                ->and($needs(new TableDiff('posts', columnsToAdd: [
                    new Column('a', 'integer', primaryKey: true),
                ])))->toBeTrue()
                ->and($needs(new TableDiff('posts', columnsToDrop: [$legacy], currentTable: $plain)))->toBeFalse()
                ->and($needs(new TableDiff('posts', columnsToDrop: [$legacy], currentTable: $indexed)))->toBeTrue()
                ->and($needs(new TableDiff(
                    'posts',
                    columnsToDrop: [$legacy],
                    indexesToDrop: [new Index('idx_legacy', ['legacy'])],
                    currentTable: $indexed,
                )))->toBeFalse()
                ->and($needs(new TableDiff('posts', columnsToDrop: [$legacy], currentTable: $partial)))->toBeTrue()
                ->and($needs(new TableDiff('posts', columnsToDrop: [$legacy], currentTable: $referencing)))
                ->toBeTrue()
                ->and($needs(new TableDiff(
                    'posts',
                    columnsToDrop: [$plain->columns[0]],
                    currentPrimaryKey: ['id'],
                    currentTable: $plain,
                )))->toBeTrue()
                ->and($needs(new TableDiff('posts', columnsToDrop: [new Column('req', 'string')])))->toBeFalse()
                ->and($needs(new TableDiff('posts', columnsToDrop: [new Column('req', 'string')]), reverse: true))
                ->toBeTrue()
                ->and(fn () => sqliteGenerator()->generateUp(sqliteAlterDiff(
                    new TableDiff('posts', columnsToModify: ['legacy' => $legacy]),
                )))->toThrow(MigrationException::class, 'rebuild');
        },
    );

    it('refuses unsupported primary key changes with the SQLite driver name', function (): void {
        $diff = sqliteAlterDiff(new TableDiff(
            tableName: 'links',
            columnsToDrop: [new Column('a', 'integer', primaryKey: true)],
            currentPrimaryKey: ['a', 'b'],
        ));

        expect(fn () => sqliteGenerator()->generateUp($diff))->toThrow(MigrationException::class, 'SQLite');
    });

    it('generates down statements that reverse each native alteration', function (): void {
        $current = new Table(
            name: 'posts',
            columns: [...sqlitePostsTable()->columns, new Column(
                name: 'code',
                type: 'string',
                nullable: true,
                unique: true,
            )],
        );
        $diff = sqliteAlterDiff(new TableDiff(
            tableName: 'posts',
            columnsToAdd: [new Column(name: 'slug', type: 'string', nullable: true, unique: true)],
            columnsToDrop: [$current->columns[2], $current->columns[3]],
            indexesToAdd: [new Index('idx_title', ['title'])],
            indexesToDrop: [new Index('idx_old', ['legacy']), new Index(
                'posts_code_unique',
                ['code'],
                IndexType::Unique,
            )],
            currentTable: new Table(
                name: 'posts',
                columns: $current->columns,
                indexes: [new Index('idx_old', ['legacy']), new Index(
                    'posts_code_unique',
                    ['code'],
                    IndexType::Unique,
                )],
            ),
        ));
        $generator = sqliteGenerator();
        $before = [
            ...sqliteCreateStatements($current),
            'CREATE INDEX "idx_old" ON "posts" ("legacy")',
        ];
        $pdo = sqliteExec($before);
        $pdo->exec("INSERT INTO posts (title, legacy, code) VALUES ('one', 'x', 'c1')");
        $schema = static fn (): array => $pdo->query(
            "SELECT type, name FROM sqlite_master WHERE name NOT LIKE 'sqlite_%' ORDER BY type, name",
        )->fetchAll(PDO::FETCH_NUM);
        $original = $schema();
        $down = $generator->generateDown($diff);
        sqliteRun($pdo, $generator->generateUp($diff));
        sqliteRun($pdo, $down);

        expect($down)->toBe([
            'DROP INDEX "idx_title"',
            'DROP INDEX "posts_slug_unique"',
            'ALTER TABLE "posts" DROP COLUMN "slug"',
            'ALTER TABLE "posts" ADD COLUMN "legacy" VARCHAR(255)',
            'ALTER TABLE "posts" ADD COLUMN "code" VARCHAR(255)',
            'CREATE UNIQUE INDEX "posts_code_unique" ON "posts" ("code")',
            'CREATE INDEX "idx_old" ON "posts" ("legacy")',
        ])->and($schema())->toBe($original);
    });
});
