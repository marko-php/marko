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

        expect($diff)->toBeInstanceOf(SchemaDiff::class);
        expect($diff->tablesToCreate)->toHaveCount(1);
        expect($diff->tablesToCreate[0]->name)->toBe('comments');
    });

    it('detects tables that need to be dropped', function (): void {
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

        expect($diff->tablesToDrop)->toHaveCount(1);
        expect($diff->tablesToDrop[0]->name)->toBe('legacy_table');
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
        expect($tableDiff)->toBeInstanceOf(TableDiff::class);
        expect($tableDiff->columnsToAdd)->toHaveCount(1);
        expect($tableDiff->columnsToAdd[0]->name)->toBe('slug');
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
        expect($tableDiff->columnsToDrop)->toHaveCount(1);
        expect($tableDiff->columnsToDrop[0]->name)->toBe('old_column');
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
        expect($tableDiff->columnsToModify)->toHaveCount(1);
        expect($tableDiff->columnsToModify['views']->name)->toBe('views');
        expect($tableDiff->columnsToModify['views']->type)->toBe('BIGINT');
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
        expect($tableDiff->columnsToModify)->toHaveCount(1);
        expect($tableDiff->columnsToModify['bio']->nullable)->toBeTrue();
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
        expect($tableDiff->columnsToModify)->toHaveCount(1);
        expect($tableDiff->columnsToModify['status']->default)->toBe('published');
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
        expect($tableDiff->indexesToAdd)->toHaveCount(1);
        expect($tableDiff->indexesToAdd[0]->name)->toBe('idx_posts_slug');
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
        expect($tableDiff->indexesToDrop)->toHaveCount(1);
        expect($tableDiff->indexesToDrop[0]->name)->toBe('idx_old');
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
        expect($tableDiff->foreignKeysToAdd)->toHaveCount(1);
        expect($tableDiff->foreignKeysToAdd[0]->name)->toBe('fk_posts_user');
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
        expect($tableDiff->foreignKeysToDrop)->toHaveCount(1);
        expect($tableDiff->foreignKeysToDrop[0]->name)->toBe('fk_legacy');
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

        expect($diff->hasDestructiveChanges())->toBeTrue();
        expect($diff->getDestructiveChanges())->toContain('DROP TABLE legacy_table');
        expect($diff->getDestructiveChanges())->toContain('DROP COLUMN posts.old_column');
        expect($diff->getDestructiveChanges())->toContain('DROP INDEX posts.idx_old');
        expect($diff->getDestructiveChanges())->toContain('DROP FOREIGN KEY posts.fk_old');
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

        expect($diff->isEmpty())->toBeTrue();
        expect($diff->tablesToCreate)->toBeEmpty();
        expect($diff->tablesToDrop)->toBeEmpty();
        expect($diff->tablesToAlter)->toBeEmpty();
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

        expect($summary)->toContain('Create table: comments');
        expect($summary)->toContain('Drop table: legacy');
        expect($summary)->toContain('Alter table: posts');
        expect($summary)->toContain('Add column: slug');
        expect($summary)->toContain('Modify column: title');
        expect($summary)->toContain('Add index: idx_slug');
    });
});
