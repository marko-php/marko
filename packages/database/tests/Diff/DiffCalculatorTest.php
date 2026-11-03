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

/**
 * A users table with an auto-increment id, the given column (if any) and indexes.
 *
 * @param list<Index> $indexes
 */
function uniqueDiffUsers(
    ?Column $column = null,
    array $indexes = [],
): Table {
    return new Table(
        name: 'users',
        columns: [
            new Column(name: 'id', type: 'integer', primaryKey: true, autoIncrement: true),
            ...($column !== null ? [$column] : []),
        ],
        indexes: $indexes,
    );
}

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

    it('puts the database column into columnsToModifyFrom for a nullability-only change', function (): void {
        $databaseColumn = new Column(name: 'bio', type: 'TEXT', nullable: false);

        $diff = $this->calculator->calculate(
            ['posts' => new Table(name: 'posts', columns: [new Column(name: 'bio', type: 'TEXT', nullable: true)])],
            ['posts' => new Table(name: 'posts', columns: [$databaseColumn])],
        );

        expect($diff->tablesToAlter['posts']->columnsToModifyFrom)->toBe(['bio' => $databaseColumn]);
    });

    it('puts the database column into columnsToModifyFrom for a default-only change', function (): void {
        $databaseColumn = new Column(name: 'status', type: 'VARCHAR', default: 'draft');

        $diff = $this->calculator->calculate(
            ['posts' => new Table(
                name: 'posts',
                columns: [new Column(name: 'status', type: 'VARCHAR', default: 'live')],
            )],
            ['posts' => new Table(name: 'posts', columns: [$databaseColumn])],
        );

        expect($diff->tablesToAlter['posts']->columnsToModifyFrom)->toBe(['status' => $databaseColumn]);
    });

    it('keys columnsToModifyFrom by the same column names as columnsToModify', function (): void {
        $diff = $this->calculator->calculate(
            ['posts' => new Table(name: 'posts', columns: [
                new Column(name: 'id', type: 'INT', primaryKey: true),
                new Column(name: 'title', type: 'VARCHAR', nullable: true),
                new Column(name: 'views', type: 'BIGINT'),
                new Column(name: 'status', type: 'VARCHAR', default: 'live'),
            ])],
            ['posts' => new Table(name: 'posts', columns: [
                new Column(name: 'id', type: 'INT', primaryKey: true),
                new Column(name: 'title', type: 'VARCHAR'),
                new Column(name: 'views', type: 'INT'),
                new Column(name: 'status', type: 'VARCHAR', default: 'live'),
            ])],
        );

        $tableDiff = $diff->tablesToAlter['posts'];

        expect(array_keys($tableDiff->columnsToModifyFrom))->toBe(['title', 'views'])
            ->and(array_keys($tableDiff->columnsToModify))->toBe(['title', 'views'])
            ->and($tableDiff->columnsToModifyFrom['views']->type)->toBe('INT');
    });

    it('fills the current primary key from the database table key columns', function (): void {
        $diff = $this->calculator->calculate(
            ['post_tags' => new Table(name: 'post_tags', columns: [
                new Column(name: 'post_id', type: 'INT', primaryKey: true),
                new Column(name: 'tag_id', type: 'INT', primaryKey: true),
                new Column(name: 'weight', type: 'INT'),
            ])],
            ['post_tags' => new Table(name: 'post_tags', columns: [
                new Column(name: 'post_id', type: 'INT', primaryKey: true),
                new Column(name: 'tag_id', type: 'INT', primaryKey: true),
            ])],
        );

        expect($diff->tablesToAlter['post_tags']->currentPrimaryKey)->toBe(['post_id', 'tag_id']);
    });

    it('carries the database\'s current table on every table diff the calculator builds', function (): void {
        $databaseTable = new Table(
            name: 'posts',
            columns: [new Column(name: 'id', type: 'INT', primaryKey: true)],
            indexes: [new Index(name: 'idx_manual', columns: ['id'])],
        );

        $diff = $this->calculator->calculate(
            ['posts' => new Table(name: 'posts', columns: [new Column(name: 'id', type: 'INT', primaryKey: true)])],
            ['posts' => $databaseTable],
        );

        expect($diff->tablesToAlter['posts']->currentTable)->toBe($databaseTable);
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

    it('does not drop an index listed in the table unmanagedIndexes', function (): void {
        $entitySchema = [
            'shows' => new Table(
                name: 'shows',
                columns: [new Column(name: 'id', type: 'INT', primaryKey: true)],
                unmanagedIndexes: ['shows_live_partial_idx'],
            ),
        ];
        $databaseSchema = [
            'shows' => new Table(
                name: 'shows',
                columns: [new Column(name: 'id', type: 'INT', primaryKey: true)],
                indexes: [new Index(name: 'shows_live_partial_idx', columns: ['id'], where: 'id > 0')],
            ),
        ];

        $diff = $this->calculator->calculate($entitySchema, $databaseSchema);

        expect($diff->isEmpty())->toBeTrue();
    });

    it('does not drop an index matching a configured ignore pattern', function (): void {
        $calculator = new DiffCalculator(ignoredIndexes: ['shows_manual_idx', '*_gin_idx']);
        $entitySchema = [
            'shows' => new Table(
                name: 'shows',
                columns: [new Column(name: 'id', type: 'INT', primaryKey: true)],
            ),
        ];
        $databaseSchema = [
            'shows' => new Table(
                name: 'shows',
                columns: [new Column(name: 'id', type: 'INT', primaryKey: true)],
                indexes: [
                    new Index(name: 'shows_manual_idx', columns: ['id']),
                    new Index(name: 'shows_tags_gin_idx', columns: ['id']),
                ],
            ),
        ];

        $diff = $calculator->calculate($entitySchema, $databaseSchema);

        expect($diff->isEmpty())->toBeTrue();
    });

    it('still drops undeclared indexes that are not ignored', function (): void {
        $calculator = new DiffCalculator(ignoredIndexes: ['*_gin_idx']);
        $entitySchema = [
            'shows' => new Table(
                name: 'shows',
                columns: [new Column(name: 'id', type: 'INT', primaryKey: true)],
                unmanagedIndexes: ['shows_live_partial_idx'],
            ),
        ];
        $databaseSchema = [
            'shows' => new Table(
                name: 'shows',
                columns: [new Column(name: 'id', type: 'INT', primaryKey: true)],
                indexes: [
                    new Index(name: 'shows_live_partial_idx', columns: ['id']),
                    new Index(name: 'shows_stale_idx', columns: ['id']),
                ],
            ),
        ];

        $diff = $calculator->calculate($entitySchema, $databaseSchema);

        expect($diff->tablesToAlter['shows']->indexesToDrop)->toHaveCount(1)
            ->and($diff->tablesToAlter['shows']->indexesToDrop[0]->name)->toBe('shows_stale_idx');
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

    it('adds a unique index when an existing column becomes unique', function (): void {
        $diff = $this->calculator->calculate(
            ['users' => uniqueDiffUsers(new Column(name: 'email', type: 'varchar', unique: true))],
            ['users' => uniqueDiffUsers(new Column(name: 'email', type: 'varchar', length: 255))],
        );

        expect($diff->tablesToAlter['users']->indexesToAdd)->toEqual([
            new Index(name: 'users_email_unique', columns: ['email'], type: IndexType::Unique),
        ])
            ->and($diff->tablesToAlter['users']->indexesToDrop)->toBeEmpty();
    });

    it('drops the unique index when an existing column stops being unique', function (): void {
        $databaseIndex = new Index(name: 'email', columns: ['email'], type: IndexType::Unique);

        $diff = $this->calculator->calculate(
            ['users' => uniqueDiffUsers(new Column(name: 'email', type: 'varchar'))],
            ['users' => uniqueDiffUsers(
                new Column(name: 'email', type: 'varchar', length: 255, unique: true),
                indexes: [$databaseIndex],
            )],
        );

        expect($diff->tablesToAlter['users']->indexesToDrop)->toBe([$databaseIndex])
            ->and($diff->tablesToAlter['users']->indexesToAdd)->toBeEmpty();
    });

    it('keeps an existing single-column unique index of a unique column whatever its name', function (
        string $indexName,
    ): void {
        $diff = $this->calculator->calculate(
            ['users' => uniqueDiffUsers(new Column(name: 'email', type: 'varchar', unique: true))],
            ['users' => uniqueDiffUsers(
                new Column(name: 'email', type: 'varchar', length: 255, unique: true),
                indexes: [new Index(name: $indexName, columns: ['email'], type: IndexType::Unique)],
            )],
        );

        expect($diff->isEmpty())->toBeTrue();
    })->with(['mysql inline' => 'email', 'pgsql inline' => 'users_email_key', 'derived' => 'users_email_unique']);

    it('does not report a column as modified when only its unique flag differs', function (): void {
        $diff = $this->calculator->calculate(
            ['users' => uniqueDiffUsers(new Column(name: 'email', type: 'varchar', unique: true))],
            ['users' => uniqueDiffUsers(new Column(name: 'email', type: 'varchar', length: 255))],
        );

        expect($diff->tablesToAlter['users']->columnsToModify)->toBeEmpty();
    });

    it('does not derive a unique index for a column that is being added', function (): void {
        $diff = $this->calculator->calculate(
            ['users' => uniqueDiffUsers(new Column(name: 'email', type: 'varchar', unique: true))],
            ['users' => uniqueDiffUsers()],
        );

        expect($diff->tablesToAlter['users']->columnsToAdd)->toHaveCount(1)
            ->and($diff->tablesToAlter['users']->indexesToAdd)->toBeEmpty();
    });

    it('does not derive a unique index when the entity declares one on the column', function (): void {
        $declared = new Index(name: 'idx_users_email', columns: ['email'], type: IndexType::Unique);

        $diff = $this->calculator->calculate(
            ['users' => uniqueDiffUsers(
                new Column(name: 'email', type: 'varchar', unique: true),
                indexes: [$declared],
            )],
            ['users' => uniqueDiffUsers(new Column(name: 'email', type: 'varchar', length: 255))],
        );

        expect($diff->tablesToAlter['users']->indexesToAdd)->toBe([$declared]);
    });

    it("does not treat a partial unique index as the column's uniqueness", function (): void {
        $partial = new Index(
            name: 'users_email_live',
            columns: ['email'],
            type: IndexType::Unique,
            where: 'deleted_at IS NULL',
        );

        $diff = $this->calculator->calculate(
            ['users' => uniqueDiffUsers(new Column(name: 'email', type: 'varchar', unique: true))],
            ['users' => uniqueDiffUsers(new Column(name: 'email', type: 'varchar', length: 255), indexes: [$partial])],
        );

        expect($diff->tablesToAlter['users']->indexesToAdd)->toEqual([
            new Index(name: 'users_email_unique', columns: ['email'], type: IndexType::Unique),
        ])
            ->and($diff->tablesToAlter['users']->indexesToDrop)->toBe([$partial]);
    });

    it('drops a unique index on a foreign key column that is no longer unique', function (): void {
        $unique = new Index(name: 'team_id', columns: ['team_id'], type: IndexType::Unique);

        $diff = $this->calculator->calculate(
            ['users' => uniqueDiffUsers(new Column(name: 'team_id', type: 'integer', references: 'teams.id'))],
            ['users' => uniqueDiffUsers(
                new Column(name: 'team_id', type: 'integer', unique: true),
                indexes: [$unique],
            )],
        );

        expect($diff->tablesToAlter['users']->indexesToDrop)->toBe([$unique]);
    });

    it('adds a plain replacement index when it drops the only index of a foreign key column', function (): void {
        $diff = $this->calculator->calculate(
            ['users' => uniqueDiffUsers(new Column(name: 'team_id', type: 'integer', references: 'teams.id'))],
            ['users' => uniqueDiffUsers(
                new Column(name: 'team_id', type: 'integer', unique: true),
                indexes: [new Index(name: 'team_id', columns: ['team_id'], type: IndexType::Unique)],
            )],
        );

        expect($diff->tablesToAlter['users']->indexesToAdd)->toEqual([
            new Index(name: 'users_team_id_index', columns: ['team_id']),
        ]);
    });

    it(
        'adds no replacement index when another index on the database table already leads with the foreign key column',
        function (): void {
            $diff = $this->calculator->calculate(
                ['users' => uniqueDiffUsers(
                    new Column(name: 'team_id', type: 'integer', references: 'teams.id'),
                    indexes: [new Index(name: 'idx_team_role', columns: ['team_id', 'id'])],
                )],
                ['users' => uniqueDiffUsers(
                    new Column(name: 'team_id', type: 'integer', unique: true),
                    indexes: [
                        new Index(name: 'team_id', columns: ['team_id'], type: IndexType::Unique),
                        new Index(name: 'idx_team_role', columns: ['team_id', 'id']),
                    ],
                )],
            );

            expect($diff->tablesToAlter['users']->indexesToAdd)->toBeEmpty()
                ->and($diff->tablesToAlter['users']->indexesToDrop)->toHaveCount(1);
        },
    );

    it('keeps the derived unique index name users_email_unique unchanged', function (): void {
        $diff = $this->calculator->calculate(
            ['users' => uniqueDiffUsers(new Column(name: 'email', type: 'varchar', unique: true))],
            ['users' => uniqueDiffUsers(new Column(name: 'email', type: 'varchar', length: 255))],
        );

        expect($diff->tablesToAlter['users']->indexesToAdd[0]->name)->toBe('users_email_unique');
    });

    it('shortens a derived unique index name over 63 bytes', function (): void {
        $table = fn (bool $unique): Table => new Table(
            name: 'customer_subscription_events',
            columns: [
                new Column(name: 'id', type: 'integer', primaryKey: true, autoIncrement: true),
                new Column(name: 'external_billing_reference_id', type: 'varchar', length: 191, unique: $unique),
            ],
        );

        $diff = $this->calculator->calculate(
            ['customer_subscription_events' => $table(true)],
            ['customer_subscription_events' => $table(false)],
        );
        $full = 'customer_subscription_events_external_billing_reference_id_unique';

        expect($diff->tablesToAlter['customer_subscription_events']->indexesToAdd)->toEqual([
            new Index(
                name: 'customer_subscription_events_external_billing_r_' . hash('crc32b', $full) . '_unique',
                columns: ['external_billing_reference_id'],
                type: IndexType::Unique,
            ),
        ]);
    });

    it('shortens a derived foreign key replacement index name over 63 bytes', function (): void {
        $table = fn (bool $unique): Table => new Table(
            name: 'customer_subscription_events',
            columns: [
                new Column(name: 'id', type: 'integer', primaryKey: true, autoIncrement: true),
                new Column(
                    name: 'billing_account_reference_number',
                    type: 'integer',
                    unique: $unique,
                    references: $unique ? null : 'billing_accounts.id',
                ),
            ],
            indexes: $unique ? [new Index(
                name: 'billing_account_reference_number',
                columns: ['billing_account_reference_number'],
                type: IndexType::Unique,
            )] : [],
        );

        $diff = $this->calculator->calculate(
            ['customer_subscription_events' => $table(false)],
            ['customer_subscription_events' => $table(true)],
        );
        $full = 'customer_subscription_events_billing_account_reference_number_index';
        $name = $diff->tablesToAlter['customer_subscription_events']->indexesToAdd[0]->name;

        expect($name)->toBe('customer_subscription_events_billing_account_ref_' . hash('crc32b', $full) . '_index')
            ->and(strlen($name))->toBeLessThanOrEqual(63);
    });

    it('gives two long unique columns sharing a prefix different derived names', function (): void {
        $table = fn (bool $unique): Table => new Table(
            name: 'customer_subscription_events',
            columns: [
                new Column(name: 'id', type: 'integer', primaryKey: true, autoIncrement: true),
                new Column(name: 'external_billing_reference_primary', type: 'varchar', unique: $unique),
                new Column(name: 'external_billing_reference_secondary', type: 'varchar', unique: $unique),
            ],
        );

        $diff = $this->calculator->calculate(
            ['customer_subscription_events' => $table(true)],
            ['customer_subscription_events' => $table(false)],
        );
        [$first, $second] = $diff->tablesToAlter['customer_subscription_events']->indexesToAdd;

        expect($first->name)->not->toBe($second->name)
            ->and(strlen($first->name))->toBeLessThanOrEqual(63)
            ->and(strlen($second->name))->toBeLessThanOrEqual(63);
    });

    it('keeps a non-unique index on a foreign key column', function (): void {
        $diff = $this->calculator->calculate(
            ['users' => uniqueDiffUsers(new Column(name: 'team_id', type: 'integer', references: 'teams.id'))],
            ['users' => uniqueDiffUsers(
                new Column(name: 'team_id', type: 'integer'),
                indexes: [new Index(name: 'team_id', columns: ['team_id'])],
            )],
        );

        expect($diff->isEmpty())->toBeTrue();
    });
});
