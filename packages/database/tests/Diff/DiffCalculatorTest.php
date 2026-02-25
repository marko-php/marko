<?php

declare(strict_types=1);

namespace Marko\Database\Tests\Diff;

use Marko\Database\Diff\DiffCalculator;
use Marko\Database\Diff\SchemaDiff;
use Marko\Database\Diff\TableDiff;
use Marko\Database\Schema\Column;
use Marko\Database\Schema\ForeignKey;
use Marko\Database\Schema\Index;
use Marko\Database\Schema\IndexType;
use Marko\Database\Schema\Table;

beforeEach(function (): void {
    $this->calculator = new DiffCalculator();
});

describe('DiffCalculator', function (): void {
    it('detects new tables that need to be created', function (): void {
        $entitySchema = [
            'posts' => new Table(
                name: 'posts',
                columns: [
                    new Column(name: 'id', type: 'INT', primaryKey: true),
                    new Column(name: 'title', type: 'VARCHAR', length: 255),
                ],
            ),
            'comments' => new Table(
                name: 'comments',
                columns: [
                    new Column(name: 'id', type: 'INT', primaryKey: true),
                    new Column(name: 'body', type: 'TEXT'),
                ],
            ),
        ];

        $databaseSchema = [
            'posts' => new Table(
                name: 'posts',
                columns: [
                    new Column(name: 'id', type: 'INT', primaryKey: true),
                    new Column(name: 'title', type: 'VARCHAR', length: 255),
                ],
            ),
        ];

        $diff = $this->calculator->calculate($entitySchema, $databaseSchema);

        expect($diff)
            ->toBeInstanceOf(SchemaDiff::class)
            ->and($diff->tablesToCreate)->toHaveCount(1)
            ->and($diff->tablesToCreate[0]->name)->toBe('comments');
    });

    it('leaves non-entity tables alone (no drops for migration-only tables)', function (): void {
        $entitySchema = [
            'posts' => new Table(
                name: 'posts',
                columns: [
                    new Column(name: 'id', type: 'INT', primaryKey: true),
                ],
            ),
        ];

        $databaseSchema = [
            'posts' => new Table(
                name: 'posts',
                columns: [
                    new Column(name: 'id', type: 'INT', primaryKey: true),
                ],
            ),
            'legacy_table' => new Table(
                name: 'legacy_table',
                columns: [
                    new Column(name: 'id', type: 'INT', primaryKey: true),
                ],
            ),
        ];

        $diff = $this->calculator->calculate($entitySchema, $databaseSchema);

        // Tables in DB but not in entity schema are left alone
        expect($diff->tablesToDrop)->toBeEmpty();
    });

    it('detects new columns in existing tables', function (): void {
        $entitySchema = [
            'posts' => new Table(
                name: 'posts',
                columns: [
                    new Column(name: 'id', type: 'INT', primaryKey: true),
                    new Column(name: 'title', type: 'VARCHAR', length: 255),
                    new Column(name: 'slug', type: 'VARCHAR', length: 255),
                ],
            ),
        ];

        $databaseSchema = [
            'posts' => new Table(
                name: 'posts',
                columns: [
                    new Column(name: 'id', type: 'INT', primaryKey: true),
                    new Column(name: 'title', type: 'VARCHAR', length: 255),
                ],
            ),
        ];

        $diff = $this->calculator->calculate($entitySchema, $databaseSchema);

        expect($diff->tablesToAlter)->toHaveCount(1);

        $tableDiff = $diff->tablesToAlter['posts'];
        expect($tableDiff)
            ->toBeInstanceOf(TableDiff::class)
            ->and($tableDiff->columnsToAdd)->toHaveCount(1)
            ->and($tableDiff->columnsToAdd[0]->name)->toBe('slug');
    });

    it('detects columns that need to be dropped', function (): void {
        $entitySchema = [
            'posts' => new Table(
                name: 'posts',
                columns: [
                    new Column(name: 'id', type: 'INT', primaryKey: true),
                ],
            ),
        ];

        $databaseSchema = [
            'posts' => new Table(
                name: 'posts',
                columns: [
                    new Column(name: 'id', type: 'INT', primaryKey: true),
                    new Column(name: 'old_column', type: 'VARCHAR', length: 100),
                ],
            ),
        ];

        $diff = $this->calculator->calculate($entitySchema, $databaseSchema);

        expect($diff->tablesToAlter)->toHaveCount(1);

        $tableDiff = $diff->tablesToAlter['posts'];
        expect($tableDiff->columnsToDrop)
            ->toHaveCount(1)
            ->and($tableDiff->columnsToDrop[0]->name)->toBe('old_column');
    });

    it('detects column type changes', function (): void {
        $entitySchema = [
            'posts' => new Table(
                name: 'posts',
                columns: [
                    new Column(name: 'id', type: 'INT', primaryKey: true),
                    new Column(name: 'views', type: 'BIGINT'),
                ],
            ),
        ];

        $databaseSchema = [
            'posts' => new Table(
                name: 'posts',
                columns: [
                    new Column(name: 'id', type: 'INT', primaryKey: true),
                    new Column(name: 'views', type: 'INT'),
                ],
            ),
        ];

        $diff = $this->calculator->calculate($entitySchema, $databaseSchema);

        expect($diff->tablesToAlter)->toHaveCount(1);

        $tableDiff = $diff->tablesToAlter['posts'];
        expect($tableDiff->columnsToModify)
            ->toHaveCount(1)
            ->and($tableDiff->columnsToModify['views']->name)->toBe('views')
            ->and($tableDiff->columnsToModify['views']->type)->toBe('BIGINT');
    });

    it('detects column nullable changes', function (): void {
        $entitySchema = [
            'posts' => new Table(
                name: 'posts',
                columns: [
                    new Column(name: 'id', type: 'INT', primaryKey: true),
                    new Column(name: 'bio', type: 'TEXT', nullable: true),
                ],
            ),
        ];

        $databaseSchema = [
            'posts' => new Table(
                name: 'posts',
                columns: [
                    new Column(name: 'id', type: 'INT', primaryKey: true),
                    new Column(name: 'bio', type: 'TEXT', nullable: false),
                ],
            ),
        ];

        $diff = $this->calculator->calculate($entitySchema, $databaseSchema);

        expect($diff->tablesToAlter)->toHaveCount(1);

        $tableDiff = $diff->tablesToAlter['posts'];
        expect($tableDiff->columnsToModify)
            ->toHaveCount(1)
            ->and($tableDiff->columnsToModify['bio']->nullable)->toBeTrue();
    });

    it('detects column default value changes', function (): void {
        $entitySchema = [
            'posts' => new Table(
                name: 'posts',
                columns: [
                    new Column(name: 'id', type: 'INT', primaryKey: true),
                    new Column(name: 'status', type: 'VARCHAR', default: 'published'),
                ],
            ),
        ];

        $databaseSchema = [
            'posts' => new Table(
                name: 'posts',
                columns: [
                    new Column(name: 'id', type: 'INT', primaryKey: true),
                    new Column(name: 'status', type: 'VARCHAR', default: 'draft'),
                ],
            ),
        ];

        $diff = $this->calculator->calculate($entitySchema, $databaseSchema);

        expect($diff->tablesToAlter)->toHaveCount(1);

        $tableDiff = $diff->tablesToAlter['posts'];
        expect($tableDiff->columnsToModify)
            ->toHaveCount(1)
            ->and($tableDiff->columnsToModify['status']->default)->toBe('published');
    });

    it('detects new indexes', function (): void {
        $entitySchema = [
            'posts' => new Table(
                name: 'posts',
                columns: [
                    new Column(name: 'id', type: 'INT', primaryKey: true),
                    new Column(name: 'slug', type: 'VARCHAR', length: 255),
                ],
                indexes: [
                    new Index(name: 'idx_posts_slug', columns: ['slug'], type: IndexType::Unique),
                ],
            ),
        ];

        $databaseSchema = [
            'posts' => new Table(
                name: 'posts',
                columns: [
                    new Column(name: 'id', type: 'INT', primaryKey: true),
                    new Column(name: 'slug', type: 'VARCHAR', length: 255),
                ],
                indexes: [],
            ),
        ];

        $diff = $this->calculator->calculate($entitySchema, $databaseSchema);

        expect($diff->tablesToAlter)->toHaveCount(1);

        $tableDiff = $diff->tablesToAlter['posts'];
        expect($tableDiff->indexesToAdd)
            ->toHaveCount(1)
            ->and($tableDiff->indexesToAdd[0]->name)->toBe('idx_posts_slug');
    });

    it('detects indexes that need to be dropped', function (): void {
        $entitySchema = [
            'posts' => new Table(
                name: 'posts',
                columns: [
                    new Column(name: 'id', type: 'INT', primaryKey: true),
                ],
                indexes: [],
            ),
        ];

        $databaseSchema = [
            'posts' => new Table(
                name: 'posts',
                columns: [
                    new Column(name: 'id', type: 'INT', primaryKey: true),
                ],
                indexes: [
                    new Index(name: 'idx_old', columns: ['id']),
                ],
            ),
        ];

        $diff = $this->calculator->calculate($entitySchema, $databaseSchema);

        expect($diff->tablesToAlter)->toHaveCount(1);

        $tableDiff = $diff->tablesToAlter['posts'];
        expect($tableDiff->indexesToDrop)
            ->toHaveCount(1)
            ->and($tableDiff->indexesToDrop[0]->name)->toBe('idx_old');
    });

    it('detects new foreign keys', function (): void {
        $entitySchema = [
            'posts' => new Table(
                name: 'posts',
                columns: [
                    new Column(name: 'id', type: 'INT', primaryKey: true),
                    new Column(name: 'user_id', type: 'INT'),
                ],
                indexes: [],
                foreignKeys: [
                    new ForeignKey(
                        name: 'fk_posts_user',
                        columns: ['user_id'],
                        referencedTable: 'users',
                        referencedColumns: ['id'],
                        onDelete: 'CASCADE',
                    ),
                ],
            ),
        ];

        $databaseSchema = [
            'posts' => new Table(
                name: 'posts',
                columns: [
                    new Column(name: 'id', type: 'INT', primaryKey: true),
                    new Column(name: 'user_id', type: 'INT'),
                ],
                indexes: [],
                foreignKeys: [],
            ),
        ];

        $diff = $this->calculator->calculate($entitySchema, $databaseSchema);

        expect($diff->tablesToAlter)->toHaveCount(1);

        $tableDiff = $diff->tablesToAlter['posts'];
        expect($tableDiff->foreignKeysToAdd)
            ->toHaveCount(1)
            ->and($tableDiff->foreignKeysToAdd[0]->name)->toBe('fk_posts_user');
    });

    it('detects foreign keys that need to be dropped', function (): void {
        $entitySchema = [
            'posts' => new Table(
                name: 'posts',
                columns: [
                    new Column(name: 'id', type: 'INT', primaryKey: true),
                    new Column(name: 'user_id', type: 'INT'),
                ],
                indexes: [],
                foreignKeys: [],
            ),
        ];

        $databaseSchema = [
            'posts' => new Table(
                name: 'posts',
                columns: [
                    new Column(name: 'id', type: 'INT', primaryKey: true),
                    new Column(name: 'user_id', type: 'INT'),
                ],
                indexes: [],
                foreignKeys: [
                    new ForeignKey(
                        name: 'fk_legacy',
                        columns: ['user_id'],
                        referencedTable: 'users',
                        referencedColumns: ['id'],
                    ),
                ],
            ),
        ];

        $diff = $this->calculator->calculate($entitySchema, $databaseSchema);

        expect($diff->tablesToAlter)->toHaveCount(1);

        $tableDiff = $diff->tablesToAlter['posts'];
        expect($tableDiff->foreignKeysToDrop)
            ->toHaveCount(1)
            ->and($tableDiff->foreignKeysToDrop[0]->name)->toBe('fk_legacy');
    });

    it('flags destructive operations (DROP) separately', function (): void {
        $entitySchema = [
            'posts' => new Table(
                name: 'posts',
                columns: [
                    new Column(name: 'id', type: 'INT', primaryKey: true),
                ],
            ),
        ];

        $databaseSchema = [
            'posts' => new Table(
                name: 'posts',
                columns: [
                    new Column(name: 'id', type: 'INT', primaryKey: true),
                    new Column(name: 'old_column', type: 'VARCHAR'),
                ],
                indexes: [
                    new Index(name: 'idx_old', columns: ['old_column']),
                ],
                foreignKeys: [
                    new ForeignKey(
                        name: 'fk_old',
                        columns: ['id'],
                        referencedTable: 'other',
                        referencedColumns: ['id'],
                    ),
                ],
            ),
            'legacy_table' => new Table(
                name: 'legacy_table',
                columns: [
                    new Column(name: 'id', type: 'INT', primaryKey: true),
                ],
            ),
        ];

        $diff = $this->calculator->calculate($entitySchema, $databaseSchema);

        // Non-entity tables (legacy_table) are not dropped
        expect($diff->hasDestructiveChanges())
            ->toBeTrue()
            ->and($diff->getDestructiveChanges())->not->toContain('DROP TABLE legacy_table')
            ->and($diff->getDestructiveChanges())->toContain('DROP COLUMN posts.old_column')
            ->and($diff->getDestructiveChanges())->toContain('DROP INDEX posts.idx_old')
            ->and($diff->getDestructiveChanges())->toContain('DROP FOREIGN KEY posts.fk_old');
    });

    it('treats entity unique=true and db unique=false as equivalent when unique index exists', function (): void {
        // PostgreSQL unique indexes don't set column constraint flag,
        // so entity.unique=true and db.unique=false should be equivalent
        $entitySchema = [
            'posts' => new Table(
                name: 'posts',
                columns: [
                    new Column(name: 'id', type: 'INT', primaryKey: true),
                    new Column(name: 'slug', type: 'VARCHAR', length: 255, unique: true),
                ],
                indexes: [
                    new Index(name: 'idx_posts_slug', columns: ['slug'], type: IndexType::Unique),
                ],
            ),
        ];

        $databaseSchema = [
            'posts' => new Table(
                name: 'posts',
                columns: [
                    new Column(name: 'id', type: 'INT', primaryKey: true),
                    new Column(name: 'slug', type: 'VARCHAR', length: 255, unique: false),
                ],
                indexes: [
                    new Index(name: 'idx_posts_slug', columns: ['slug'], type: IndexType::Unique),
                ],
            ),
        ];

        $diff = $this->calculator->calculate($entitySchema, $databaseSchema);

        expect($diff->isEmpty())->toBeTrue();
    });

    it('treats entity unique=false with unique index and db unique=true as equivalent', function (): void {
        // Entity has unique=false on column but a separate unique Index object
        // DB reports unique=true as a column constraint
        $entitySchema = [
            'posts' => new Table(
                name: 'posts',
                columns: [
                    new Column(name: 'id', type: 'INT', primaryKey: true),
                    new Column(name: 'slug', type: 'VARCHAR', length: 255, unique: false),
                ],
                indexes: [
                    new Index(name: 'idx_posts_slug', columns: ['slug'], type: IndexType::Unique),
                ],
            ),
        ];

        $databaseSchema = [
            'posts' => new Table(
                name: 'posts',
                columns: [
                    new Column(name: 'id', type: 'INT', primaryKey: true),
                    new Column(name: 'slug', type: 'VARCHAR', length: 255, unique: true),
                ],
                indexes: [
                    new Index(name: 'idx_posts_slug', columns: ['slug'], type: IndexType::Unique),
                ],
            ),
        ];

        $diff = $this->calculator->calculate($entitySchema, $databaseSchema);

        expect($diff->isEmpty())->toBeTrue();
    });

    it('returns empty diff when schema matches database', function (): void {
        $schema = [
            'posts' => new Table(
                name: 'posts',
                columns: [
                    new Column(name: 'id', type: 'INT', primaryKey: true),
                    new Column(name: 'title', type: 'VARCHAR', length: 255),
                ],
                indexes: [
                    new Index(name: 'idx_title', columns: ['title']),
                ],
                foreignKeys: [],
            ),
        ];

        $diff = $this->calculator->calculate($schema, $schema);

        expect($diff->isEmpty())
            ->toBeTrue()
            ->and($diff->tablesToCreate)->toBeEmpty()
            ->and($diff->tablesToDrop)->toBeEmpty()
            ->and($diff->tablesToAlter)->toBeEmpty();
    });

    it('provides human-readable diff summary', function (): void {
        $entitySchema = [
            'posts' => new Table(
                name: 'posts',
                columns: [
                    new Column(name: 'id', type: 'INT', primaryKey: true),
                    new Column(name: 'title', type: 'VARCHAR', length: 255),
                    new Column(name: 'slug', type: 'VARCHAR', length: 255),
                ],
                indexes: [
                    new Index(name: 'idx_slug', columns: ['slug'], type: IndexType::Unique),
                ],
            ),
            'comments' => new Table(
                name: 'comments',
                columns: [
                    new Column(name: 'id', type: 'INT', primaryKey: true),
                ],
            ),
        ];

        $databaseSchema = [
            'posts' => new Table(
                name: 'posts',
                columns: [
                    new Column(name: 'id', type: 'INT', primaryKey: true),
                    new Column(name: 'title', type: 'VARCHAR', length: 100),
                ],
            ),
            'legacy' => new Table(
                name: 'legacy',
                columns: [
                    new Column(name: 'id', type: 'INT', primaryKey: true),
                ],
            ),
        ];

        $diff = $this->calculator->calculate($entitySchema, $databaseSchema);
        $summary = $diff->getSummary();

        // Non-entity tables (legacy) are not dropped
        expect($summary)
            ->toContain('Create table: comments')
            ->not->toContain('Drop table: legacy')
            ->and($summary)->toContain('Alter table: posts')
            ->toContain('Add column: slug')
            ->toContain('Modify column: title')
            ->toContain('Add index: idx_slug');
    });
});
