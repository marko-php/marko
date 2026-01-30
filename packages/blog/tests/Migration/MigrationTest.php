<?php

declare(strict_types=1);

use Marko\Database\Connection\ConnectionInterface;
use Marko\Database\Connection\StatementInterface;
use Marko\Database\Migration\Migration;

/**
 * Helper to create a mock connection that captures executed SQL.
 *
 * @param array<string> $capturedSql
 */
function blogMigrationCreateMockConnection(
    array &$capturedSql,
): ConnectionInterface {
    return new class ($capturedSql) implements ConnectionInterface
    {
        /**
         * @param array<string> $sql
         */
        public function __construct(
            /** @noinspection PhpPropertyOnlyWrittenInspection - Reference property modifies external variable */
            private array &$sql,
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
            $this->sql[] = $sql;

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

/**
 * Load a migration file and return the migration instance.
 */
function blogMigrationLoadMigration(
    string $filename,
): Migration {
    $path = dirname(__DIR__, 2) . '/database/migrations/' . $filename;

    return require $path;
}

describe('Blog Migrations', function (): void {
    it('creates authors table with correct columns', function (): void {
        $capturedSql = [];
        $connection = blogMigrationCreateMockConnection($capturedSql);
        $migration = blogMigrationLoadMigration('001_create_authors_table.php');

        $migration->up($connection);

        expect($capturedSql)->toHaveCount(1);

        $sql = $capturedSql[0];
        expect($sql)
            ->toContain('CREATE TABLE')
            ->and($sql)->toContain('authors')
            ->and($sql)->toContain('id')
            ->and($sql)->toContain('name')
            ->and($sql)->toContain('email')
            ->and($sql)->toContain('bio')
            ->and($sql)->toContain('slug')
            ->and($sql)->toContain('created_at')
            ->and($sql)->toContain('updated_at')
            ->and($sql)->toContain('PRIMARY KEY')
            ->and($sql)->toContain('AUTO_INCREMENT')
            ->and($sql)->toContain('UNIQUE');
    });

    it('creates categories table with self-referential parent_id', function (): void {
        $capturedSql = [];
        $connection = blogMigrationCreateMockConnection($capturedSql);
        $migration = blogMigrationLoadMigration('002_create_categories_table.php');

        $migration->up($connection);

        expect($capturedSql)->toHaveCount(1);

        $sql = $capturedSql[0];
        expect($sql)
            ->toContain('CREATE TABLE')
            ->and($sql)->toContain('categories')
            ->and($sql)->toContain('id')
            ->and($sql)->toContain('name')
            ->and($sql)->toContain('slug')
            ->and($sql)->toContain('parent_id')
            ->and($sql)->toContain('created_at')
            ->and($sql)->toContain('updated_at')
            ->and($sql)->toContain('PRIMARY KEY')
            ->and($sql)->toContain('AUTO_INCREMENT')
            ->and($sql)->toContain('UNIQUE')
            ->and($sql)->toContain('FOREIGN KEY')
            ->and($sql)->toContain('REFERENCES categories');
    });

    it('creates tags table with correct columns', function (): void {
        $capturedSql = [];
        $connection = blogMigrationCreateMockConnection($capturedSql);
        $migration = blogMigrationLoadMigration('003_create_tags_table.php');

        $migration->up($connection);

        expect($capturedSql)->toHaveCount(1);

        $sql = $capturedSql[0];
        expect($sql)
            ->toContain('CREATE TABLE')
            ->and($sql)->toContain('tags')
            ->and($sql)->toContain('id')
            ->and($sql)->toContain('name')
            ->and($sql)->toContain('slug')
            ->and($sql)->toContain('created_at')
            ->and($sql)->toContain('updated_at')
            ->and($sql)->toContain('PRIMARY KEY')
            ->and($sql)->toContain('AUTO_INCREMENT')
            ->and($sql)->toContain('UNIQUE');
    });

    it('updates posts table with new columns', function (): void {
        $capturedSql = [];
        $connection = blogMigrationCreateMockConnection($capturedSql);
        $migration = blogMigrationLoadMigration('004_update_posts_table.php');

        $migration->up($connection);

        expect($capturedSql)->toHaveCount(1);

        $sql = $capturedSql[0];
        expect($sql)
            ->toContain('CREATE TABLE')
            ->and($sql)->toContain('posts')
            ->and($sql)->toContain('id')
            ->and($sql)->toContain('title')
            ->and($sql)->toContain('slug')
            ->and($sql)->toContain('content')
            ->and($sql)->toContain('summary')
            ->and($sql)->toContain('status')
            ->and($sql)->toContain('author_id')
            ->and($sql)->toContain('scheduled_at')
            ->and($sql)->toContain('published_at')
            ->and($sql)->toContain('created_at')
            ->and($sql)->toContain('updated_at')
            ->and($sql)->toContain('PRIMARY KEY')
            ->and($sql)->toContain('AUTO_INCREMENT')
            ->and($sql)->toContain('UNIQUE')
            ->and($sql)->toContain('FOREIGN KEY')
            ->and($sql)->toContain('REFERENCES authors');
    });

    it('creates comments table with self-referential parent_id', function (): void {
        $capturedSql = [];
        $connection = blogMigrationCreateMockConnection($capturedSql);
        $migration = blogMigrationLoadMigration('005_create_comments_table.php');

        $migration->up($connection);

        expect($capturedSql)->toHaveCount(1);

        $sql = $capturedSql[0];
        expect($sql)
            ->toContain('CREATE TABLE')
            ->and($sql)->toContain('comments')
            ->and($sql)->toContain('id')
            ->and($sql)->toContain('post_id')
            ->and($sql)->toContain('name')
            ->and($sql)->toContain('email')
            ->and($sql)->toContain('content')
            ->and($sql)->toContain('status')
            ->and($sql)->toContain('parent_id')
            ->and($sql)->toContain('verified_at')
            ->and($sql)->toContain('created_at')
            ->and($sql)->toContain('PRIMARY KEY')
            ->and($sql)->toContain('AUTO_INCREMENT')
            ->and($sql)->toContain('FOREIGN KEY')
            ->and($sql)->toContain('REFERENCES posts')
            ->and($sql)->toContain('REFERENCES comments');
    });

    it('creates verification_tokens table', function (): void {
        $capturedSql = [];
        $connection = blogMigrationCreateMockConnection($capturedSql);
        $migration = blogMigrationLoadMigration('006_create_verification_tokens_table.php');

        $migration->up($connection);

        expect($capturedSql)->toHaveCount(1);

        $sql = $capturedSql[0];
        expect($sql)
            ->toContain('CREATE TABLE')
            ->and($sql)->toContain('verification_tokens')
            ->and($sql)->toContain('id')
            ->and($sql)->toContain('token')
            ->and($sql)->toContain('email')
            ->and($sql)->toContain('type')
            ->and($sql)->toContain('comment_id')
            ->and($sql)->toContain('created_at')
            ->and($sql)->toContain('expires_at')
            ->and($sql)->toContain('PRIMARY KEY')
            ->and($sql)->toContain('AUTO_INCREMENT')
            ->and($sql)->toContain('UNIQUE')
            ->and($sql)->toContain('FOREIGN KEY')
            ->and($sql)->toContain('REFERENCES comments');
    });

    it('creates post_categories pivot table', function (): void {
        $capturedSql = [];
        $connection = blogMigrationCreateMockConnection($capturedSql);
        $migration = blogMigrationLoadMigration('007_create_post_categories_table.php');

        $migration->up($connection);

        expect($capturedSql)->toHaveCount(1);

        $sql = $capturedSql[0];
        expect($sql)
            ->toContain('CREATE TABLE')
            ->and($sql)->toContain('post_categories')
            ->and($sql)->toContain('post_id')
            ->and($sql)->toContain('category_id')
            ->and($sql)->toContain('UNIQUE')
            ->and($sql)->toContain('FOREIGN KEY')
            ->and($sql)->toContain('REFERENCES posts')
            ->and($sql)->toContain('REFERENCES categories')
            ->and($sql)->toContain('ON DELETE CASCADE');
    });

    it('creates post_tags pivot table', function (): void {
        $capturedSql = [];
        $connection = blogMigrationCreateMockConnection($capturedSql);
        $migration = blogMigrationLoadMigration('008_create_post_tags_table.php');

        $migration->up($connection);

        expect($capturedSql)->toHaveCount(1);

        $sql = $capturedSql[0];
        expect($sql)
            ->toContain('CREATE TABLE')
            ->and($sql)->toContain('post_tags')
            ->and($sql)->toContain('post_id')
            ->and($sql)->toContain('tag_id')
            ->and($sql)->toContain('UNIQUE')
            ->and($sql)->toContain('FOREIGN KEY')
            ->and($sql)->toContain('REFERENCES posts')
            ->and($sql)->toContain('REFERENCES tags')
            ->and($sql)->toContain('ON DELETE CASCADE');
    });

    it('adds foreign key constraints', function (): void {
        // This test verifies all foreign key relationships are properly defined
        $migrations = [
            '002_create_categories_table.php' => ['REFERENCES categories'],
            '004_update_posts_table.php' => ['REFERENCES authors'],
            '005_create_comments_table.php' => ['REFERENCES posts', 'REFERENCES comments'],
            '006_create_verification_tokens_table.php' => ['REFERENCES comments'],
            '007_create_post_categories_table.php' => ['REFERENCES posts', 'REFERENCES categories'],
            '008_create_post_tags_table.php' => ['REFERENCES posts', 'REFERENCES tags'],
        ];

        foreach ($migrations as $filename => $expectedReferences) {
            $capturedSql = [];
            $connection = blogMigrationCreateMockConnection($capturedSql);
            $migration = blogMigrationLoadMigration($filename);

            $migration->up($connection);

            $sql = $capturedSql[0];
            expect($sql)->toContain('FOREIGN KEY');

            foreach ($expectedReferences as $reference) {
                expect($sql)->toContain($reference);
            }
        }
    });

    it('adds indexes for frequently queried columns', function (): void {
        // Test that migrations include indexes for columns that are frequently queried
        $indexExpectations = [
            '001_create_authors_table.php' => ['idx_authors_slug', 'idx_authors_email'],
            '002_create_categories_table.php' => ['idx_categories_slug', 'idx_categories_parent_id'],
            '003_create_tags_table.php' => ['idx_tags_slug'],
            '004_update_posts_table.php' => ['idx_posts_slug', 'idx_posts_status', 'idx_posts_author_id', 'idx_posts_published_at'],
            '005_create_comments_table.php' => ['idx_comments_post_id', 'idx_comments_status', 'idx_comments_parent_id', 'idx_comments_email'],
            '006_create_verification_tokens_table.php' => ['idx_verification_tokens_token', 'idx_verification_tokens_email', 'idx_verification_tokens_type', 'idx_verification_tokens_expires_at'],
            '007_create_post_categories_table.php' => ['idx_post_categories_unique', 'idx_post_categories_category_id'],
            '008_create_post_tags_table.php' => ['idx_post_tags_unique', 'idx_post_tags_tag_id'],
        ];

        foreach ($indexExpectations as $filename => $expectedIndexes) {
            $capturedSql = [];
            $connection = blogMigrationCreateMockConnection($capturedSql);
            $migration = blogMigrationLoadMigration($filename);

            $migration->up($connection);

            $sql = $capturedSql[0];
            expect($sql)->toContain('INDEX');

            foreach ($expectedIndexes as $indexName) {
                expect($sql)->toContain($indexName);
            }
        }
    });

    it('can rollback all migrations cleanly', function (): void {
        // Test that all migrations have proper down() methods that can rollback
        $migrations = [
            '001_create_authors_table.php' => 'DROP TABLE authors',
            '002_create_categories_table.php' => 'DROP TABLE categories',
            '003_create_tags_table.php' => 'DROP TABLE tags',
            '004_update_posts_table.php' => 'DROP TABLE posts',
            '005_create_comments_table.php' => 'DROP TABLE comments',
            '006_create_verification_tokens_table.php' => 'DROP TABLE verification_tokens',
            '007_create_post_categories_table.php' => 'DROP TABLE post_categories',
            '008_create_post_tags_table.php' => 'DROP TABLE post_tags',
        ];

        foreach ($migrations as $filename => $expectedSql) {
            $capturedSql = [];
            $connection = blogMigrationCreateMockConnection($capturedSql);
            $migration = blogMigrationLoadMigration($filename);

            $migration->down($connection);

            expect($capturedSql)
                ->toHaveCount(1)
                ->and($capturedSql[0])->toBe($expectedSql);
        }
    });
});
