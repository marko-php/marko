<?php

declare(strict_types=1);

use Marko\Database\Diff\SchemaDiff;
use Marko\Database\Diff\SqlGeneratorInterface;
use Marko\Database\Diff\TableDiff;
use Marko\Database\Migration\Migration;
use Marko\Database\Migration\MigrationGenerator;
use Marko\Database\Schema\Column;
use Marko\Database\Schema\Table;

describe('MigrationGenerator', function (): void {
    beforeEach(function (): void {
        $this->tempDir = sys_get_temp_dir() . '/marko-test-' . uniqid();
        mkdir($this->tempDir, 0755, true);
    });

    afterEach(function (): void {
        // Clean up temp directory
        if (is_dir($this->tempDir)) {
            $files = glob($this->tempDir . '/database/migrations/*.php');
            foreach ($files as $file) {
                unlink($file);
            }
            if (is_dir($this->tempDir . '/database/migrations')) {
                rmdir($this->tempDir . '/database/migrations');
            }
            if (is_dir($this->tempDir . '/database')) {
                rmdir($this->tempDir . '/database');
            }
            rmdir($this->tempDir);
        }
    });

    it('generates migration filename with timestamp prefix', function (): void {
        $sqlGenerator = $this->createMock(SqlGeneratorInterface::class);
        $sqlGenerator->method('generateUp')->willReturn(['CREATE TABLE "posts" (id INT)']);
        $sqlGenerator->method('generateDown')->willReturn(['DROP TABLE "posts"']);

        $generator = new MigrationGenerator($sqlGenerator, $this->tempDir);

        $table = new Table('posts', [new Column('id', 'INTEGER', primaryKey: true)]);
        $diff = new SchemaDiff(tablesToCreate: [$table]);

        $paths = $generator->generate($diff);

        expect($paths)->toHaveCount(1);
        $filename = basename($paths[0]);
        expect($filename)->toMatch('/^\d{14}_/');
    });

    it('generates migration filename with descriptive suffix from changes', function (): void {
        $sqlGenerator = $this->createMock(SqlGeneratorInterface::class);
        $sqlGenerator->method('generateUp')->willReturn(['CREATE TABLE "posts" (id INT)']);
        $sqlGenerator->method('generateDown')->willReturn(['DROP TABLE "posts"']);

        $generator = new MigrationGenerator($sqlGenerator, $this->tempDir);

        $table = new Table('posts', [new Column('id', 'INTEGER', primaryKey: true)]);
        $diff = new SchemaDiff(tablesToCreate: [$table]);

        $paths = $generator->generate($diff);

        $filename = basename($paths[0]);
        expect($filename)->toContain('create_posts');
    });

    it('generates valid PHP migration class', function (): void {
        $sqlGenerator = $this->createMock(SqlGeneratorInterface::class);
        $sqlGenerator->method('generateUp')->willReturn(['CREATE TABLE "posts" (id INT)']);
        $sqlGenerator->method('generateDown')->willReturn(['DROP TABLE "posts"']);

        $generator = new MigrationGenerator($sqlGenerator, $this->tempDir);

        $table = new Table('posts', [new Column('id', 'INTEGER', primaryKey: true)]);
        $diff = new SchemaDiff(tablesToCreate: [$table]);

        $paths = $generator->generate($diff);

        $content = file_get_contents($paths[0]);

        expect($content)->toContain('<?php');
        expect($content)->toContain('declare(strict_types=1);');
        expect($content)->toContain('use Marko\Database\Connection\ConnectionInterface;');
        expect($content)->toContain('use Marko\Database\Migration\Migration;');
        expect($content)->toContain('return new class extends Migration');
    });

    it('includes up() method with SQL statements', function (): void {
        $sqlGenerator = $this->createMock(SqlGeneratorInterface::class);
        $sqlGenerator->method('generateUp')->willReturn(['CREATE TABLE "posts" (id INT)']);
        $sqlGenerator->method('generateDown')->willReturn(['DROP TABLE "posts"']);

        $generator = new MigrationGenerator($sqlGenerator, $this->tempDir);

        $table = new Table('posts', [new Column('id', 'INTEGER', primaryKey: true)]);
        $diff = new SchemaDiff(tablesToCreate: [$table]);

        $paths = $generator->generate($diff);

        $content = file_get_contents($paths[0]);

        expect($content)->toContain('public function up(');
        expect($content)->toContain('CREATE TABLE "posts"');
    });

    it('includes down() method with rollback SQL', function (): void {
        $sqlGenerator = $this->createMock(SqlGeneratorInterface::class);
        $sqlGenerator->method('generateUp')->willReturn(['CREATE TABLE "posts" (id INT)']);
        $sqlGenerator->method('generateDown')->willReturn(['DROP TABLE "posts"']);

        $generator = new MigrationGenerator($sqlGenerator, $this->tempDir);

        $table = new Table('posts', [new Column('id', 'INTEGER', primaryKey: true)]);
        $diff = new SchemaDiff(tablesToCreate: [$table]);

        $paths = $generator->generate($diff);

        $content = file_get_contents($paths[0]);

        expect($content)->toContain('public function down(');
        expect($content)->toContain('DROP TABLE "posts"');
    });

    it('uses nowdoc syntax for SQL statements', function (): void {
        $sqlGenerator = $this->createMock(SqlGeneratorInterface::class);
        $sqlGenerator->method('generateUp')->willReturn(['CREATE TABLE "posts" (id INT)']);
        $sqlGenerator->method('generateDown')->willReturn(['DROP TABLE "posts"']);

        $generator = new MigrationGenerator($sqlGenerator, $this->tempDir);

        $table = new Table('posts', [new Column('id', 'INTEGER', primaryKey: true)]);
        $diff = new SchemaDiff(tablesToCreate: [$table]);

        $paths = $generator->generate($diff);

        $content = file_get_contents($paths[0]);

        expect($content)->toContain("<<<'SQL'");
        expect($content)->toContain('SQL);');
    });

    it('includes semicolons at end of SQL statements', function (): void {
        $sqlGenerator = $this->createMock(SqlGeneratorInterface::class);
        $sqlGenerator->method('generateUp')->willReturn(['CREATE TABLE "posts" (id INT)']);
        $sqlGenerator->method('generateDown')->willReturn(['DROP TABLE "posts"']);

        $generator = new MigrationGenerator($sqlGenerator, $this->tempDir);

        $table = new Table('posts', [new Column('id', 'INTEGER', primaryKey: true)]);
        $diff = new SchemaDiff(tablesToCreate: [$table]);

        $paths = $generator->generate($diff);

        $content = file_get_contents($paths[0]);

        // SQL statement should end with semicolon before nowdoc closing
        expect($content)->toMatch('/CREATE TABLE "posts" \(id INT\);\s+SQL\)/');
        expect($content)->toMatch('/DROP TABLE "posts";\s+SQL\)/');
    });

    it('formats SQL with proper indentation inside nowdoc', function (): void {
        $sqlGenerator = $this->createMock(SqlGeneratorInterface::class);
        $sqlGenerator->method('generateUp')->willReturn(['CREATE TABLE "posts" (id INT)']);
        $sqlGenerator->method('generateDown')->willReturn(['DROP TABLE "posts"']);

        $generator = new MigrationGenerator($sqlGenerator, $this->tempDir);

        $table = new Table('posts', [new Column('id', 'INTEGER', primaryKey: true)]);
        $diff = new SchemaDiff(tablesToCreate: [$table]);

        $paths = $generator->generate($diff);

        $content = file_get_contents($paths[0]);

        // SQL should be indented consistently inside nowdoc
        expect($content)->toContain('            CREATE TABLE');
    });

    it('uses $this->execute() for each SQL statement', function (): void {
        $sqlGenerator = $this->createMock(SqlGeneratorInterface::class);
        $sqlGenerator->method('generateUp')->willReturn(['CREATE TABLE "posts" (id INT)']);
        $sqlGenerator->method('generateDown')->willReturn(['DROP TABLE "posts"']);

        $generator = new MigrationGenerator($sqlGenerator, $this->tempDir);

        $table = new Table('posts', [new Column('id', 'INTEGER', primaryKey: true)]);
        $diff = new SchemaDiff(tablesToCreate: [$table]);

        $paths = $generator->generate($diff);

        $content = file_get_contents($paths[0]);

        expect($content)->toContain('$this->execute($connection,');
    });

    it('writes file to database/migrations/ directory', function (): void {
        $sqlGenerator = $this->createMock(SqlGeneratorInterface::class);
        $sqlGenerator->method('generateUp')->willReturn(['CREATE TABLE "posts" (id INT)']);
        $sqlGenerator->method('generateDown')->willReturn(['DROP TABLE "posts"']);

        $generator = new MigrationGenerator($sqlGenerator, $this->tempDir);

        $table = new Table('posts', [new Column('id', 'INTEGER', primaryKey: true)]);
        $diff = new SchemaDiff(tablesToCreate: [$table]);

        $paths = $generator->generate($diff);

        expect($paths[0])->toStartWith($this->tempDir . '/database/migrations/');
        expect(file_exists($paths[0]))->toBeTrue();
    });

    it('creates migrations directory if not exists', function (): void {
        $sqlGenerator = $this->createMock(SqlGeneratorInterface::class);
        $sqlGenerator->method('generateUp')->willReturn(['CREATE TABLE "posts" (id INT)']);
        $sqlGenerator->method('generateDown')->willReturn(['DROP TABLE "posts"']);

        $generator = new MigrationGenerator($sqlGenerator, $this->tempDir);

        // Confirm directory doesn't exist yet
        expect(is_dir($this->tempDir . '/database/migrations'))->toBeFalse();

        $table = new Table('posts', [new Column('id', 'INTEGER', primaryKey: true)]);
        $diff = new SchemaDiff(tablesToCreate: [$table]);

        $generator->generate($diff);

        expect(is_dir($this->tempDir . '/database/migrations'))->toBeTrue();
    });

    it('returns path to generated file', function (): void {
        $sqlGenerator = $this->createMock(SqlGeneratorInterface::class);
        $sqlGenerator->method('generateUp')->willReturn(['CREATE TABLE "posts" (id INT)']);
        $sqlGenerator->method('generateDown')->willReturn(['DROP TABLE "posts"']);

        $generator = new MigrationGenerator($sqlGenerator, $this->tempDir);

        $table = new Table('posts', [new Column('id', 'INTEGER', primaryKey: true)]);
        $diff = new SchemaDiff(tablesToCreate: [$table]);

        $paths = $generator->generate($diff);

        expect($paths)->toBeArray();
        expect($paths)->toHaveCount(1);
        expect($paths[0])->toBeString();
        expect(file_exists($paths[0]))->toBeTrue();
    });

    it('generates separate migrations per table change', function (): void {
        $sqlGenerator = $this->createMock(SqlGeneratorInterface::class);
        $sqlGenerator->method('generateUp')->willReturn([
            'CREATE TABLE "posts" (id INT)',
            'CREATE TABLE "comments" (id INT)',
        ]);
        $sqlGenerator->method('generateDown')->willReturn([
            'DROP TABLE "posts"',
            'DROP TABLE "comments"',
        ]);

        $generator = new MigrationGenerator($sqlGenerator, $this->tempDir);

        $postsTable = new Table('posts', [new Column('id', 'INTEGER', primaryKey: true)]);
        $commentsTable = new Table('comments', [new Column('id', 'INTEGER', primaryKey: true)]);
        $diff = new SchemaDiff(tablesToCreate: [$postsTable, $commentsTable]);

        $paths = $generator->generate($diff);

        expect($paths)->toHaveCount(2);
        expect(basename($paths[0]))->toContain('create_posts');
        expect(basename($paths[1]))->toContain('create_comments');
    });

    it('handles empty diff with no file generated', function (): void {
        $sqlGenerator = $this->createMock(SqlGeneratorInterface::class);
        $sqlGenerator->method('generateUp')->willReturn([]);
        $sqlGenerator->method('generateDown')->willReturn([]);

        $generator = new MigrationGenerator($sqlGenerator, $this->tempDir);

        $diff = new SchemaDiff();

        $paths = $generator->generate($diff);

        expect($paths)->toBeArray();
        expect($paths)->toBeEmpty();
    });

    it('generates migration for alter table operations', function (): void {
        $sqlGenerator = $this->createMock(SqlGeneratorInterface::class);
        $sqlGenerator->method('generateUp')->willReturn(['ALTER TABLE "posts" ADD COLUMN "title" VARCHAR(255)']);
        $sqlGenerator->method('generateDown')->willReturn(['ALTER TABLE "posts" DROP COLUMN "title"']);

        $generator = new MigrationGenerator($sqlGenerator, $this->tempDir);

        $tableDiff = new TableDiff(
            tableName: 'posts',
            columnsToAdd: [new Column('title', 'VARCHAR', length: 255)],
        );
        $diff = new SchemaDiff(tablesToAlter: ['posts' => $tableDiff]);

        $paths = $generator->generate($diff);

        expect($paths)->toHaveCount(1);
        expect(basename($paths[0]))->toContain('alter_posts');
    });

    it('generates migration for drop table operations', function (): void {
        $sqlGenerator = $this->createMock(SqlGeneratorInterface::class);
        $sqlGenerator->method('generateUp')->willReturn(['DROP TABLE "posts"']);
        $sqlGenerator->method('generateDown')->willReturn(['CREATE TABLE "posts" (id INT)']);

        $generator = new MigrationGenerator($sqlGenerator, $this->tempDir);

        $table = new Table('posts', [new Column('id', 'INTEGER', primaryKey: true)]);
        $diff = new SchemaDiff(tablesToDrop: [$table]);

        $paths = $generator->generate($diff);

        expect($paths)->toHaveCount(1);
        expect(basename($paths[0]))->toContain('drop_posts');
    });

    it('generates syntactically valid PHP that can be included', function (): void {
        $sqlGenerator = $this->createMock(SqlGeneratorInterface::class);
        $sqlGenerator->method('generateUp')->willReturn(['CREATE TABLE "posts" (id INT)']);
        $sqlGenerator->method('generateDown')->willReturn(['DROP TABLE "posts"']);

        $generator = new MigrationGenerator($sqlGenerator, $this->tempDir);

        $table = new Table('posts', [new Column('id', 'INTEGER', primaryKey: true)]);
        $diff = new SchemaDiff(tablesToCreate: [$table]);

        $paths = $generator->generate($diff);

        // The file should be valid PHP and return a Migration instance
        $migration = require $paths[0];

        expect($migration)->toBeInstanceOf(Migration::class);
    });
});
