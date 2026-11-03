<?php

declare(strict_types=1);

use Marko\Database\Config\DatabaseTimezoneConfig;
use Marko\Database\Diff\SchemaDiff;
use Marko\Database\Diff\TableDiff;
use Marko\Database\Exceptions\MigrationException;
use Marko\Database\Schema\Column;
use Marko\Database\Schema\ForeignKey;
use Marko\Database\Schema\Index;
use Marko\Database\Schema\IndexType;
use Marko\Database\Schema\Table;
use Marko\Database\Sqlite\Sql\SqliteGenerator;

/** @var list<string> $rebuildFiles */
$GLOBALS['rebuildFiles'] = [];

afterEach(function (): void {
    foreach ($GLOBALS['rebuildFiles'] as $file) {
        foreach (['', '-wal', '-shm'] as $suffix) {
            if (file_exists($file . $suffix)) {
                unlink($file . $suffix);
            }
        }
    }

    $GLOBALS['rebuildFiles'] = [];
});

function rebuildGenerator(): SqliteGenerator
{
    return new SqliteGenerator(DatabaseTimezoneConfig::fromName('UTC'));
}

/**
 * @return list<string>
 */
function rebuildCreate(
    Table $table,
): array {
    return rebuildGenerator()->generateUp(new SchemaDiff(tablesToCreate: [$table]));
}

function rebuildDatabase(): PDO
{
    $file = sys_get_temp_dir() . '/marko-rebuild-' . bin2hex(random_bytes(8)) . '.sqlite';
    $GLOBALS['rebuildFiles'][] = $file;

    return rebuildConnect($file);
}

function rebuildConnect(
    string $file,
): PDO {
    return new PDO("sqlite:$file", options: [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
}

function rebuildFile(): string
{
    return $GLOBALS['rebuildFiles'][array_key_last($GLOBALS['rebuildFiles'])];
}

/**
 * @param list<string> $statements
 */
function rebuildRun(
    PDO $pdo,
    array $statements,
): void {
    foreach ($statements as $statement) {
        $pdo->exec($statement);
    }
}

/**
 * The parent/child schema: posts(id, title, views) with a comments child that references posts.
 */
function rebuildPostsTable(
    array $extraColumns = [],
    array $indexes = [],
): Table {
    return new Table(
        name: 'posts',
        columns: [
            new Column('id', 'integer', primaryKey: true, autoIncrement: true),
            new Column('title', 'string', length: 100),
            new Column('views', 'string', length: 20, default: '0'),
            ...$extraColumns,
        ],
        indexes: $indexes,
    );
}

function rebuildCommentsTable(): Table
{
    return new Table(
        name: 'comments',
        columns: [
            new Column('id', 'integer', primaryKey: true, autoIncrement: true),
            new Column('post_id', 'integer'),
        ],
        foreignKeys: [new ForeignKey('fk_comments_post', ['post_id'], 'posts', ['id'], onDelete: 'CASCADE')],
    );
}

function rebuildSeed(
    PDO $pdo,
): void {
    rebuildRun($pdo, rebuildCreate(rebuildPostsTable(indexes: [new Index('posts_title_idx', ['title'])])));
    rebuildRun($pdo, rebuildCreate(rebuildCommentsTable()));
    $pdo->exec("INSERT INTO posts (title, views) VALUES ('One', '10'), ('Two', '20')");
    $pdo->exec('INSERT INTO comments (post_id) VALUES (1), (1), (2)');
}

function rebuildCurrent(): Table
{
    return rebuildPostsTable(indexes: [new Index('posts_title_idx', ['title'])]);
}

/**
 * @return list<string>
 */
function rebuildDiffUp(
    TableDiff $diff,
    bool $reverse = false,
): array {
    $schema = new SchemaDiff(tablesToAlter: [$diff]);

    return $reverse ? rebuildGenerator()->generateDown($schema) : rebuildGenerator()->generateUp($schema);
}

describe('SqliteGenerator table rebuild', function (): void {
    it('rebuilds the table to change a column type and keeps existing rows', function (): void {
        $pdo = rebuildDatabase();
        rebuildSeed($pdo);
        $diff = new TableDiff(
            tableName: 'posts',
            columnsToModify: ['views' => new Column('views', 'integer', default: 0)],
            currentTable: rebuildCurrent(),
        );

        $statements = rebuildDiffUp($diff);
        rebuildRun($pdo, $statements);

        $columns = $pdo->query("SELECT name, type FROM pragma_table_info('posts') ORDER BY cid")->fetchAll(
            PDO::FETCH_KEY_PAIR,
        );
        $rows = $pdo->query('SELECT title, views, typeof(views) AS t FROM posts ORDER BY id')->fetchAll(
            PDO::FETCH_ASSOC,
        );

        expect($statements[0])->toBe('PRAGMA foreign_keys = OFF')
            ->and(end($statements))->toBe('PRAGMA foreign_keys = ON')
            ->and($columns['views'])->toBe('INTEGER')
            ->and($rows)->toBe([
                ['title' => 'One', 'views' => 10, 't' => 'integer'],
                ['title' => 'Two', 'views' => 20, 't' => 'integer'],
            ])
            ->and($pdo->query('SELECT COUNT(*) FROM comments')->fetchColumn())->toBe(3)
            ->and($pdo->query('PRAGMA foreign_keys')->fetchColumn())->toBe(1)
            ->and(
                $pdo->query(
                    "SELECT COUNT(*) FROM sqlite_schema WHERE name LIKE '\\_\\_marko%' ESCAPE '\\'",
                )->fetchColumn(),
            )->toBe(0);
    });

    it('rebuilds the table to drop a column that has an index or constraint', function (): void {
        $pdo = rebuildDatabase();
        rebuildSeed($pdo);
        $diff = new TableDiff(
            tableName: 'comments',
            columnsToDrop: [new Column('post_id', 'integer')],
            foreignKeysToDrop: [rebuildCommentsTable()->foreignKeys[0]],
            currentTable: rebuildCommentsTable(),
        );

        $statements = rebuildDiffUp($diff);
        rebuildRun($pdo, $statements);

        $columns = $pdo->query("SELECT name FROM pragma_table_info('comments') ORDER BY cid")->fetchAll(
            PDO::FETCH_COLUMN,
        );

        expect($statements[2])->toStartWith('CREATE TABLE "__marko_rebuild_comments"')
            ->and($columns)->toBe(['id'])
            ->and($pdo->query('SELECT COUNT(*) FROM comments')->fetchColumn())->toBe(3)
            ->and($pdo->query("SELECT COUNT(*) FROM pragma_foreign_key_list('comments')")->fetchColumn())->toBe(0);
    });

    it('rebuilds the table to add and to drop a foreign key', function (): void {
        $pdo = rebuildDatabase();
        rebuildRun($pdo, [
            'CREATE TABLE "authors" ("id" INTEGER PRIMARY KEY AUTOINCREMENT)',
            'CREATE TABLE "books" ("id" INTEGER PRIMARY KEY AUTOINCREMENT, "author_id" INTEGER NOT NULL)',
            'INSERT INTO "authors" DEFAULT VALUES',
            'INSERT INTO "books" ("author_id") VALUES (1)',
        ]);
        $current = new Table(
            name: 'books',
            columns: [
                new Column('id', 'integer', primaryKey: true, autoIncrement: true),
                new Column('author_id', 'integer'),
            ],
        );
        $foreignKey = new ForeignKey('fk_books_author', ['author_id'], 'authors', ['id'], onDelete: 'CASCADE');

        rebuildRun(
            $pdo,
            rebuildDiffUp(new TableDiff('books', foreignKeysToAdd: [$foreignKey], currentTable: $current)),
        );
        $added = $pdo->query("SELECT \"table\" FROM pragma_foreign_key_list('books')")->fetchAll(PDO::FETCH_COLUMN);

        $withKey = new Table($current->name, $current->columns, foreignKeys: [$foreignKey]);
        rebuildRun(
            $pdo,
            rebuildDiffUp(new TableDiff('books', foreignKeysToDrop: [$foreignKey], currentTable: $withKey)),
        );
        $dropped = $pdo->query("SELECT \"table\" FROM pragma_foreign_key_list('books')")->fetchAll(PDO::FETCH_COLUMN);

        expect($added)->toBe(['authors'])
            ->and($dropped)->toBeEmpty()
            ->and($pdo->query('SELECT COUNT(*) FROM books')->fetchColumn())->toBe(1);
    });

    it('recreates every current index after a rebuild, including unmanaged ones', function (): void {
        $pdo = rebuildDatabase();
        $current = rebuildPostsTable(indexes: [
            new Index('posts_title_idx', ['title']),
            new Index('legacy_unmanaged_idx', ['views'], where: 'views IS NOT NULL'),
            new Index('posts_slug_unique', ['title'], IndexType::Unique),
        ]);
        rebuildRun($pdo, rebuildCreate($current));
        $pdo->exec("INSERT INTO posts (title, views) VALUES ('One', '10')");
        $diff = new TableDiff(
            tableName: 'posts',
            columnsToModify: ['views' => new Column('views', 'integer', default: 0)],
            currentTable: $current,
        );

        rebuildRun($pdo, rebuildDiffUp($diff));

        $indexes = $pdo->query(
            "SELECT name FROM sqlite_schema WHERE type = 'index' AND tbl_name = 'posts' ORDER BY name",
        )->fetchAll(PDO::FETCH_COLUMN);
        $partial = $pdo->query("SELECT sql FROM sqlite_schema WHERE name = 'legacy_unmanaged_idx'")->fetchColumn();

        expect($indexes)->toBe(['legacy_unmanaged_idx', 'posts_slug_unique', 'posts_title_idx'])
            ->and($partial)->toContain('WHERE views IS NOT NULL');
    });

    it('fails loudly when the foreign key guard finds violations after a rebuild', function (): void {
        $pdo = rebuildDatabase();
        rebuildRun($pdo, [
            'CREATE TABLE "authors" ("id" INTEGER PRIMARY KEY AUTOINCREMENT)',
            'CREATE TABLE "books" ("id" INTEGER PRIMARY KEY AUTOINCREMENT, "author_id" INTEGER NOT NULL)',
            'INSERT INTO "books" ("author_id") VALUES (99)',
        ]);
        $current = new Table('books', [
            new Column('id', 'integer', primaryKey: true, autoIncrement: true),
            new Column('author_id', 'integer'),
        ]);
        $foreignKey = new ForeignKey('fk_books_author', ['author_id'], 'authors', ['id']);
        $statements = rebuildDiffUp(new TableDiff('books', foreignKeysToAdd: [$foreignKey], currentTable: $current));

        $failure = null;

        try {
            rebuildRun($pdo, $statements);
        } catch (PDOException $exception) {
            $failure = $exception;
        }

        $pdo->exec('ROLLBACK');

        expect($failure)->toBeInstanceOf(PDOException::class)
            ->and($failure->getMessage())->toContain('CHECK constraint failed')
            ->and($pdo->query("SELECT COUNT(*) FROM pragma_foreign_key_list('books')")->fetchColumn())->toBe(0)
            ->and($pdo->query('SELECT COUNT(*) FROM books')->fetchColumn())->toBe(1);
    });

    it('leaves the original table and rows intact when a rebuild statement fails', function (): void {
        $pdo = rebuildDatabase();
        rebuildSeed($pdo);
        $file = rebuildFile();
        $statements = rebuildDiffUp(new TableDiff(
            tableName: 'posts',
            columnsToModify: ['views' => new Column('views', 'integer', default: 0)],
            currentTable: rebuildCurrent(),
        ));
        $dropIndex = array_search('DROP TABLE "posts"', $statements, true);
        $statements[$dropIndex + 1] = 'ALTER TABLE "no_such_table" RENAME TO "posts"';

        $failure = null;

        try {
            rebuildRun($pdo, $statements);
        } catch (PDOException $exception) {
            $failure = $exception;
        }

        unset($pdo);
        $reconnected = rebuildConnect($file);

        expect($failure)->toBeInstanceOf(PDOException::class)
            ->and($reconnected->query('SELECT COUNT(*) FROM posts')->fetchColumn())->toBe(2)
            ->and(
                $reconnected->query("SELECT type FROM pragma_table_info('posts') WHERE name = 'views'")->fetchColumn(),
            )
            ->toBe('VARCHAR(20)')
            ->and($reconnected->query('SELECT COUNT(*) FROM comments')->fetchColumn())->toBe(3);
    });

    it('generates down statements that rebuild the table back to its current definition', function (): void {
        $pdo = rebuildDatabase();
        rebuildSeed($pdo);
        $diff = new TableDiff(
            tableName: 'posts',
            columnsToModify: ['views' => new Column('views', 'integer', default: 0)],
            currentTable: rebuildCurrent(),
        );

        rebuildRun($pdo, rebuildDiffUp($diff));
        rebuildRun($pdo, rebuildDiffUp($diff, reverse: true));

        $type = $pdo->query("SELECT type FROM pragma_table_info('posts') WHERE name = 'views'")->fetchColumn();
        $indexes = $pdo->query("SELECT name FROM sqlite_schema WHERE type = 'index' AND tbl_name = 'posts'")
            ->fetchAll(PDO::FETCH_COLUMN);

        expect($type)->toBe('VARCHAR(20)')
            ->and($indexes)->toBe(['posts_title_idx'])
            ->and($pdo->query('SELECT COUNT(*) FROM posts')->fetchColumn())->toBe(2);
    });

    it('refuses to rebuild a diff that does not carry the current table', function (): void {
        $diff = new TableDiff('posts', columnsToModify: ['views' => new Column('views', 'integer')]);

        expect(fn () => rebuildDiffUp($diff))->toThrow(MigrationException::class, 'current table');
    });
});
