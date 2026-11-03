<?php

declare(strict_types=1);

use Marko\Core\Exceptions\UnsupportedFeatureException;
use Marko\Database\Exceptions\MigrationException;

describe('MigrationException', function (): void {
    it('builds an invalid migration name exception naming the rejected name', function (): void {
        $exception = MigrationException::invalidMigrationName('../../storage/uploads/shell');

        expect($exception->getMessage())
            ->toBe("Invalid migration name '../../storage/uploads/shell': names must not contain '/', '\\', '..' or "
                . 'NUL bytes')
            ->and($exception->getContext())->toContain('migrations table')
            ->and($exception->getSuggestion())->toContain('database/migrations/');
    });

    it('builds a missing previous column exception naming the table and column', function (): void {
        $exception = MigrationException::missingPreviousColumn('posts', 'status');

        expect($exception->getMessage())
            ->toBe("Column 'posts.status' is modified, but the diff holds no previous definition for it")
            ->and($exception->getContext())->toContain('posts.status')
            ->and($exception->getSuggestion())->toContain('columnsToModifyFrom');
    });

    it('builds a nothing to modify exception naming the table, column and driver', function (): void {
        $exception = MigrationException::nothingToModify('posts', 'email', 'PostgreSQL');

        expect($exception->getMessage())
            ->toBe("Column 'posts.email' has no type, nullability or default change for PostgreSQL to apply")
            ->and($exception->getSuggestion())->toContain('index diff');
    });

    it('builds a column change not supported exception naming the table, column and driver', function (): void {
        $exception = MigrationException::columnChangeNotSupported('posts', 'id', 'PostgreSQL', 'auto-increment');

        expect($exception->getMessage())
            ->toBe("Cannot change the auto-increment of column 'posts.id' in place on PostgreSQL")
            ->and($exception->getSuggestion())->toContain('by hand');
    });

    it(
        'names the table, the existing key columns and the new key columns when a primary key already exists',
        function (): void {
            $exception = MigrationException::primaryKeyAlreadyExists('post_tags', ['post_id', 'tag_id'], ['id']);

            expect($exception->getMessage())
                ->toBe("Cannot add primary key column 'id' to table 'post_tags', which already has a primary key on "
                    . "'post_id', 'tag_id'")
                ->and($exception->getSuggestion())->toContain('by hand');
        },
    );

    it('lists every new key column when several are added to a table that has a primary key', function (): void {
        $exception = MigrationException::primaryKeyAlreadyExists('post_tags', ['id'], ['post_id', 'tag_id']);

        expect($exception->getMessage())
            ->toBe("Cannot add primary key columns 'post_id', 'tag_id' to table 'post_tags', which already has a "
                . "primary key on 'id'");
    });
    it('keeps the original exception as previous when a migration fails', function (): void {
        $original = new RuntimeException('Database error');

        $exception = MigrationException::migrationFailed('2024_01_01_000000_test', 'Database error', $original);

        expect($exception->getPrevious())->toBe($original)
            ->and($exception->getMessage())->toBe("Migration '2024_01_01_000000_test' failed: Database error");
    });

    it('uses the original exception suggestion when a failed migration carries one', function (): void {
        $original = UnsupportedFeatureException::forDriver('partial indexes', 'MySQL', 'Drop the WHERE clause');

        $exception = MigrationException::migrationFailed('2024_01_01_000000_test', $original->getMessage(), $original);

        expect($exception->getSuggestion())->toBe('Drop the WHERE clause');
    });

    it('keeps the generic suggestion when the original exception has none', function (): void {
        $exception = MigrationException::migrationFailed(
            '2024_01_01_000000_test',
            'Database error',
            new RuntimeException('Database error'),
        );

        expect($exception->getSuggestion())->toContain('Fix the migration error');
    });
});
