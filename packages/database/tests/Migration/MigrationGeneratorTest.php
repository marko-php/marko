<?php

declare(strict_types=1);

use Marko\Core\Path\ProjectPaths;
use Marko\Database\Diff\SchemaDiff;
use Marko\Database\Diff\TableDiff;
use Marko\Database\Migration\Migration;
use Marko\Database\Migration\MigrationGenerator;
use Marko\Database\Schema\Column;
use Marko\Database\Schema\Table;
use Marko\Database\Tests\Migration\Helpers;

describe('MigrationGenerator', function (): void {
    beforeEach(function (): void {
        $this->tempDir = sys_get_temp_dir() . '/marko-test-' . uniqid();
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
