<?php

declare(strict_types=1);

use Marko\Core\Path\ProjectPaths;
use Marko\Database\Diff\SchemaDiff;
use Marko\Database\Diff\TableDiff;
use Marko\Database\Migration\Migration;
use Marko\Database\Migration\MigrationGenerator;
use Marko\Database\Schema\Column;
use Marko\Database\Schema\ForeignKey;
use Marko\Database\Schema\Table;
use Marko\Database\Tests\Migration\Helpers;

describe('MigrationGenerator', function (): void {
    beforeEach(function (): void {
        $this->tempDir = sys_get_temp_dir() . '/marko-test-' . bin2hex(random_bytes(8));
        mkdir($this->tempDir, 0755, true);
    });

    afterEach(function (): void {
        Helpers::removeDirectory($this->tempDir);
    });

    it('generates migration filename with timestamp prefix', function (): void {
        ['paths' => $paths] = Helpers::generateTestMigration($this->tempDir);

        expect($paths)->toHaveCount(1)
            ->and(basename($paths[0]))->toMatch('/^\d{14}_/');
    });

    it('generates migration filename with descriptive suffix from changes', function (): void {
        ['paths' => $paths] = Helpers::generateTestMigration($this->tempDir);

        expect(basename($paths[0]))->toContain('create_posts');
    });

    it('generates valid PHP migration class', function (): void {
        ['content' => $content] = Helpers::generateTestMigration($this->tempDir);

        expect($content)
            ->toContain('<?php')
            ->toContain('declare(strict_types=1);')
            ->toContain('use Marko\Database\Connection\ConnectionInterface;')
            ->toContain('use Marko\Database\Migration\Migration;')
            ->toContain('return new class extends Migration');
    });

    it('includes up() method with SQL statements', function (): void {
        ['content' => $content] = Helpers::generateTestMigration($this->tempDir);

        expect($content)
            ->toContain('public function up(')
            ->toContain('CREATE TABLE "posts"');
    });

    it('includes down() method with rollback SQL', function (): void {
        ['content' => $content] = Helpers::generateTestMigration($this->tempDir);

        expect($content)
            ->toContain('public function down(')
            ->toContain('DROP TABLE "posts"');
    });

    it('uses nowdoc syntax for SQL statements', function (): void {
        ['content' => $content] = Helpers::generateTestMigration($this->tempDir);

        expect($content)
            ->toContain("<<<'SQL'")
            ->toContain('SQL);');
    });

    it('includes semicolons at end of SQL statements', function (): void {
        ['content' => $content] = Helpers::generateTestMigration($this->tempDir);

        expect($content)
            ->toMatch('/CREATE TABLE "posts" \(id INT\);\s+SQL\)/')
            ->toMatch('/DROP TABLE "posts";\s+SQL\)/');
    });

    it('formats SQL with proper indentation inside nowdoc', function (): void {
        ['content' => $content] = Helpers::generateTestMigration($this->tempDir);

        expect($content)->toContain('            CREATE TABLE');
    });

    it('uses $this->execute() for each SQL statement', function (): void {
        ['content' => $content] = Helpers::generateTestMigration($this->tempDir);

        expect($content)->toContain('$this->execute($connection,');
    });

    it('writes file to database/migrations/ directory', function (): void {
        ['paths' => $paths] = Helpers::generateTestMigration($this->tempDir);

        expect($paths[0])->toStartWith($this->tempDir . '/database/migrations/')
            ->and(file_exists($paths[0]))->toBeTrue();
    });

    it('creates migrations directory if not exists', function (): void {
        expect(is_dir($this->tempDir . '/database/migrations'))->toBeFalse();

        $sqlGenerator = Helpers::createSqlGeneratorStub();
        $paths = new ProjectPaths($this->tempDir);
        $generator = new MigrationGenerator($sqlGenerator, $paths);
        $generator->generate(Helpers::createPostsTableDiff());

        expect(is_dir($this->tempDir . '/database/migrations'))->toBeTrue();
    });

    it('returns path to generated file', function (): void {
        ['paths' => $paths] = Helpers::generateTestMigration($this->tempDir);

        expect($paths)
            ->toBeArray()
            ->toHaveCount(1)
            ->and($paths[0])->toBeString()
            ->and(file_exists($paths[0]))->toBeTrue();
    });

    it('generates separate migrations per table change', function (): void {
        $postsTable = new Table('posts', [new Column('id', 'INTEGER', primaryKey: true)]);
        $commentsTable = new Table('comments', [new Column('id', 'INTEGER', primaryKey: true)]);
        $diff = new SchemaDiff(tablesToCreate: [$postsTable, $commentsTable]);

        ['paths' => $paths] = Helpers::generateTestMigration(
            $this->tempDir,
            $diff,
            ['CREATE TABLE "posts" (id INT)', 'CREATE TABLE "comments" (id INT)'],
            ['DROP TABLE "posts"', 'DROP TABLE "comments"'],
        );

        expect($paths)
            ->toHaveCount(2)
            ->and(basename($paths[0]))->toContain('create_posts')
            ->and(basename($paths[1]))->toContain('create_comments');
    });

    it('orders migrations by foreign key dependencies', function (): void {
        // Comments references posts, so posts must be created first
        $postsTable = new Table('posts', [new Column('id', 'INTEGER', primaryKey: true)]);
        $commentsTable = new Table(
            'comments',
            [
                new Column('id', 'INTEGER', primaryKey: true),
                new Column('post_id', 'INTEGER'),
            ],
            foreignKeys: [
                new ForeignKey(
                    'fk_comments_post_id',
                    ['post_id'],
                    'posts',
                    ['id'],
                ),
            ],
        );

        // Pass comments first to verify reordering happens
        $diff = new SchemaDiff(tablesToCreate: [$commentsTable, $postsTable]);

        ['paths' => $paths] = Helpers::generateTestMigration(
            $this->tempDir,
            $diff,
            ['CREATE TABLE "posts" (id INT)', 'CREATE TABLE "comments" (id INT, post_id INT)'],
            ['DROP TABLE "comments"', 'DROP TABLE "posts"'],
        );

        // Posts should come first due to foreign key dependency
        expect($paths)
            ->toHaveCount(2)
            ->and(basename($paths[0]))->toContain('create_posts')
            ->and(basename($paths[1]))->toContain('create_comments');

        // Verify timestamps are sequential
        $timestamp1 = substr(basename($paths[0]), 0, 14);
        $timestamp2 = substr(basename($paths[1]), 0, 14);
        expect((int) $timestamp2)->toBeGreaterThanOrEqual((int) $timestamp1);
    });

    it('orders complex dependency graph correctly (blog-like schema)', function (): void {
        // Create a blog-like schema:
        // - authors (no deps)
        // - categories (no deps)
        // - posts (depends on authors)
        // - comments (depends on posts)
        $authors = new Table('authors', [new Column('id', 'INTEGER', primaryKey: true)]);
        $categories = new Table('categories', [new Column('id', 'INTEGER', primaryKey: true)]);
        $posts = new Table(
            'posts',
            [
                new Column('id', 'INTEGER', primaryKey: true),
                new Column('author_id', 'INTEGER'),
            ],
            foreignKeys: [
                new ForeignKey('fk_posts_author_id', ['author_id'], 'authors', ['id']),
            ],
        );
        $comments = new Table(
            'comments',
            [
                new Column('id', 'INTEGER', primaryKey: true),
                new Column('post_id', 'INTEGER'),
            ],
            foreignKeys: [
                new ForeignKey('fk_comments_post_id', ['post_id'], 'posts', ['id']),
            ],
        );

        // Pass in scrambled order: posts, comments, authors, categories
        // The algorithm should reorder to: authors, categories, posts, comments
        $diff = new SchemaDiff(tablesToCreate: [$posts, $comments, $authors, $categories]);

        ['paths' => $paths] = Helpers::generateTestMigration(
            $this->tempDir,
            $diff,
            [
                'CREATE TABLE "authors"',
                'CREATE TABLE "categories"',
                'CREATE TABLE "posts"',
                'CREATE TABLE "comments"',
            ],
            [
                'DROP TABLE "comments"',
                'DROP TABLE "posts"',
                'DROP TABLE "categories"',
                'DROP TABLE "authors"',
            ],
        );

        // Verify correct dependency order
        expect($paths)->toHaveCount(4);

        // Extract table names from paths
        $tableOrder = array_map(function ($path) {
            // Extract table name from filename like "20260130123456_create_tablename.php"
            preg_match('/_create_(\w+)\.php$/', basename($path), $matches);

            return $matches[1] ?? '';
        }, $paths);

        // Authors must come before posts (posts depends on authors)
        $authorsIndex = array_search('authors', $tableOrder, true);
        $postsIndex = array_search('posts', $tableOrder, true);
        expect($authorsIndex)->toBeLessThan($postsIndex);

        // Posts must come before comments (comments depends on posts)
        $commentsIndex = array_search('comments', $tableOrder, true);
        expect($postsIndex)->toBeLessThan($commentsIndex);

        // Categories has no dependencies, so should maintain relative original order
        // (it was passed after authors in original order, so should appear after authors)
    });

    it('handles self-referential foreign keys (parent_id pattern)', function (): void {
        // Comments has a self-referential FK (parent_id -> comments.id)
        // This should not cause a circular dependency detection
        $posts = new Table('posts', [new Column('id', 'INTEGER', primaryKey: true)]);
        $comments = new Table(
            'comments',
            [
                new Column('id', 'INTEGER', primaryKey: true),
                new Column('post_id', 'INTEGER'),
                new Column('parent_id', 'INTEGER', nullable: true),
            ],
            foreignKeys: [
                new ForeignKey('fk_comments_post_id', ['post_id'], 'posts', ['id']),
                new ForeignKey('fk_comments_parent_id', ['parent_id'], 'comments', ['id']),
            ],
        );

        // Pass comments first - should still work because self-ref is ignored
        $diff = new SchemaDiff(tablesToCreate: [$comments, $posts]);

        ['paths' => $paths] = Helpers::generateTestMigration(
            $this->tempDir,
            $diff,
            ['CREATE TABLE "posts"', 'CREATE TABLE "comments"'],
            ['DROP TABLE "comments"', 'DROP TABLE "posts"'],
        );

        expect($paths)->toHaveCount(2);

        // Extract table names
        $tableOrder = array_map(function ($path) {
            preg_match('/_create_(\w+)\.php$/', basename($path), $matches);

            return $matches[1] ?? '';
        }, $paths);

        // Posts must come before comments
        $postsIndex = array_search('posts', $tableOrder, true);
        $commentsIndex = array_search('comments', $tableOrder, true);
        expect($postsIndex)->toBeLessThan($commentsIndex);
    });

    it('handles empty diff with no file generated', function (): void {
        ['paths' => $paths] = Helpers::generateTestMigration(
            $this->tempDir,
            new SchemaDiff(),
            [],
            [],
        );

        expect($paths)
            ->toBeArray()
            ->toBeEmpty();
    });

    it('generates migration for alter table operations', function (): void {
        $tableDiff = new TableDiff(
            tableName: 'posts',
            columnsToAdd: [new Column('title', 'VARCHAR', length: 255)],
        );
        $diff = new SchemaDiff(tablesToAlter: ['posts' => $tableDiff]);

        ['paths' => $paths] = Helpers::generateTestMigration(
            $this->tempDir,
            $diff,
            ['ALTER TABLE "posts" ADD COLUMN "title" VARCHAR(255)'],
            ['ALTER TABLE "posts" DROP COLUMN "title"'],
        );

        expect($paths)->toHaveCount(1)
            ->and(basename($paths[0]))->toContain('alter_posts');
    });

    it('generates migration for drop table operations', function (): void {
        $table = new Table('posts', [new Column('id', 'INTEGER', primaryKey: true)]);
        $diff = new SchemaDiff(tablesToDrop: [$table]);

        ['paths' => $paths] = Helpers::generateTestMigration(
            $this->tempDir,
            $diff,
            ['DROP TABLE "posts"'],
            ['CREATE TABLE "posts" (id INT)'],
        );

        expect($paths)->toHaveCount(1)
            ->and(basename($paths[0]))->toContain('drop_posts');
    });

    it('generates syntactically valid PHP that can be included', function (): void {
        ['paths' => $paths] = Helpers::generateTestMigration($this->tempDir);

        $migration = require $paths[0];

        expect($migration)->toBeInstanceOf(Migration::class);
    });
});
