<?php

declare(strict_types=1);

namespace Marko\Database\MySql\Tests\Introspection;

use Marko\Database\Connection\ConnectionInterface;
use Marko\Database\Connection\StatementInterface;
use Marko\Database\Introspection\IntrospectorInterface;
use Marko\Database\MySql\Introspection\MySqlIntrospector;
use Marko\Database\Schema\Column;
use Marko\Database\Schema\ForeignKey;
use Marko\Database\Schema\Index;
use Marko\Database\Schema\IndexType;
use Marko\Database\Schema\Table;
use RuntimeException;

/**
 * Creates a mock connection that returns predefined query results.
 *
 * @param array<string, array<int, array<string, mixed>>> $queryResults Map of SQL patterns to results
 */
function createMockConnection(
    array $queryResults = [],
): ConnectionInterface {
    return new class ($queryResults) implements ConnectionInterface
    {
        /**
         * @param array<string, array<int, array<string, mixed>>> $queryResults
         */
        public function __construct(
            private readonly array $queryResults,
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
            // Find matching query result by SQL pattern
            foreach ($this->queryResults as $pattern => $results) {
                if (str_contains($sql, $pattern)) {
                    return $results;
                }
            }

            return [];
        }

        public function execute(
            string $sql,
            array $bindings = [],
        ): int {
            return 0;
        }

        public function prepare(
            string $sql,
        ): StatementInterface {
            throw new RuntimeException('Not implemented');
        }

        public function lastInsertId(): int
        {
            return 0;
        }
    };
}

describe('MySqlIntrospector', function (): void {
    it('implements IntrospectorInterface', function (): void {
        $connection = createMockConnection();
        $introspector = new MySqlIntrospector($connection, 'testdb');

        expect($introspector)->toBeInstanceOf(IntrospectorInterface::class);
    });

    it('reads table list from information_schema.tables', function (): void {
        $connection = createMockConnection([
            'information_schema.tables' => [
                ['TABLE_NAME' => 'users'],
                ['TABLE_NAME' => 'posts'],
                ['TABLE_NAME' => 'comments'],
            ],
        ]);

        $introspector = new MySqlIntrospector($connection, 'testdb');
        $tables = $introspector->getTables();

        expect($tables)->toBe(['users', 'posts', 'comments']);
    });

    it('reads column definitions from information_schema.columns', function (): void {
        $connection = createMockConnection([
            'information_schema.columns' => [
                [
                    'COLUMN_NAME' => 'id',
                    'DATA_TYPE' => 'int',
                    'CHARACTER_MAXIMUM_LENGTH' => null,
                    'IS_NULLABLE' => 'NO',
                    'COLUMN_DEFAULT' => null,
                    'EXTRA' => 'auto_increment',
                ],
                [
                    'COLUMN_NAME' => 'name',
                    'DATA_TYPE' => 'varchar',
                    'CHARACTER_MAXIMUM_LENGTH' => '255',
                    'IS_NULLABLE' => 'NO',
                    'COLUMN_DEFAULT' => null,
                    'EXTRA' => '',
                ],
            ],
        ]);

        $introspector = new MySqlIntrospector($connection, 'testdb');
        $columns = $introspector->getColumns('users');

        expect($columns)->toHaveCount(2);
        expect($columns[0])->toBeInstanceOf(Column::class);
        expect($columns[0]->name)->toBe('id');
        expect($columns[1]->name)->toBe('name');
    });

    it('maps MySQL data types to Column value objects', function (): void {
        $connection = createMockConnection([
            'information_schema.columns' => [
                [
                    'COLUMN_NAME' => 'id',
                    'DATA_TYPE' => 'bigint',
                    'CHARACTER_MAXIMUM_LENGTH' => null,
                    'IS_NULLABLE' => 'NO',
                    'COLUMN_DEFAULT' => null,
                    'EXTRA' => '',
                ],
                [
                    'COLUMN_NAME' => 'title',
                    'DATA_TYPE' => 'varchar',
                    'CHARACTER_MAXIMUM_LENGTH' => '100',
                    'IS_NULLABLE' => 'NO',
                    'COLUMN_DEFAULT' => null,
                    'EXTRA' => '',
                ],
                [
                    'COLUMN_NAME' => 'content',
                    'DATA_TYPE' => 'text',
                    'CHARACTER_MAXIMUM_LENGTH' => null,
                    'IS_NULLABLE' => 'YES',
                    'COLUMN_DEFAULT' => null,
                    'EXTRA' => '',
                ],
            ],
        ]);

        $introspector = new MySqlIntrospector($connection, 'testdb');
        $columns = $introspector->getColumns('posts');

        expect($columns[0]->type)->toBe('bigint');
        expect($columns[1]->type)->toBe('varchar');
        expect($columns[1]->length)->toBe(100);
        expect($columns[2]->type)->toBe('text');
    });

    it('detects nullable columns', function (): void {
        $connection = createMockConnection([
            'information_schema.columns' => [
                [
                    'COLUMN_NAME' => 'id',
                    'DATA_TYPE' => 'int',
                    'CHARACTER_MAXIMUM_LENGTH' => null,
                    'IS_NULLABLE' => 'NO',
                    'COLUMN_DEFAULT' => null,
                    'EXTRA' => '',
                ],
                [
                    'COLUMN_NAME' => 'bio',
                    'DATA_TYPE' => 'text',
                    'CHARACTER_MAXIMUM_LENGTH' => null,
                    'IS_NULLABLE' => 'YES',
                    'COLUMN_DEFAULT' => null,
                    'EXTRA' => '',
                ],
            ],
        ]);

        $introspector = new MySqlIntrospector($connection, 'testdb');
        $columns = $introspector->getColumns('users');

        expect($columns[0]->nullable)->toBeFalse();
        expect($columns[1]->nullable)->toBeTrue();
    });

    it('detects default values', function (): void {
        $connection = createMockConnection([
            'information_schema.columns' => [
                [
                    'COLUMN_NAME' => 'status',
                    'DATA_TYPE' => 'varchar',
                    'CHARACTER_MAXIMUM_LENGTH' => '20',
                    'IS_NULLABLE' => 'NO',
                    'COLUMN_DEFAULT' => 'active',
                    'EXTRA' => '',
                ],
                [
                    'COLUMN_NAME' => 'priority',
                    'DATA_TYPE' => 'int',
                    'CHARACTER_MAXIMUM_LENGTH' => null,
                    'IS_NULLABLE' => 'NO',
                    'COLUMN_DEFAULT' => '0',
                    'EXTRA' => '',
                ],
                [
                    'COLUMN_NAME' => 'created_at',
                    'DATA_TYPE' => 'timestamp',
                    'CHARACTER_MAXIMUM_LENGTH' => null,
                    'IS_NULLABLE' => 'NO',
                    'COLUMN_DEFAULT' => 'CURRENT_TIMESTAMP',
                    'EXTRA' => 'DEFAULT_GENERATED',
                ],
            ],
        ]);

        $introspector = new MySqlIntrospector($connection, 'testdb');
        $columns = $introspector->getColumns('tasks');

        expect($columns[0]->default)->toBe('active');
        expect($columns[1]->default)->toBe('0');
        expect($columns[2]->default)->toBe('CURRENT_TIMESTAMP');
    });

    it('detects auto_increment columns', function (): void {
        $connection = createMockConnection([
            'information_schema.columns' => [
                [
                    'COLUMN_NAME' => 'id',
                    'DATA_TYPE' => 'int',
                    'CHARACTER_MAXIMUM_LENGTH' => null,
                    'IS_NULLABLE' => 'NO',
                    'COLUMN_DEFAULT' => null,
                    'EXTRA' => 'auto_increment',
                ],
                [
                    'COLUMN_NAME' => 'name',
                    'DATA_TYPE' => 'varchar',
                    'CHARACTER_MAXIMUM_LENGTH' => '255',
                    'IS_NULLABLE' => 'NO',
                    'COLUMN_DEFAULT' => null,
                    'EXTRA' => '',
                ],
            ],
        ]);

        $introspector = new MySqlIntrospector($connection, 'testdb');
        $columns = $introspector->getColumns('users');

        expect($columns[0]->autoIncrement)->toBeTrue();
        expect($columns[1]->autoIncrement)->toBeFalse();
    });

    it('reads indexes from information_schema.statistics', function (): void {
        $connection = createMockConnection([
            'information_schema.statistics' => [
                [
                    'INDEX_NAME' => 'idx_email',
                    'COLUMN_NAME' => 'email',
                    'NON_UNIQUE' => '1',
                    'INDEX_TYPE' => 'BTREE',
                    'SEQ_IN_INDEX' => '1',
                ],
                [
                    'INDEX_NAME' => 'idx_name_created',
                    'COLUMN_NAME' => 'name',
                    'NON_UNIQUE' => '1',
                    'INDEX_TYPE' => 'BTREE',
                    'SEQ_IN_INDEX' => '1',
                ],
                [
                    'INDEX_NAME' => 'idx_name_created',
                    'COLUMN_NAME' => 'created_at',
                    'NON_UNIQUE' => '1',
                    'INDEX_TYPE' => 'BTREE',
                    'SEQ_IN_INDEX' => '2',
                ],
            ],
        ]);

        $introspector = new MySqlIntrospector($connection, 'testdb');
        $indexes = $introspector->getIndexes('users');

        expect($indexes)->toHaveCount(2);
        expect($indexes[0])->toBeInstanceOf(Index::class);
        expect($indexes[0]->name)->toBe('idx_email');
        expect($indexes[0]->columns)->toBe(['email']);
        expect($indexes[1]->name)->toBe('idx_name_created');
        expect($indexes[1]->columns)->toBe(['name', 'created_at']);
    });

    it('detects unique indexes', function (): void {
        $connection = createMockConnection([
            'information_schema.statistics' => [
                [
                    'INDEX_NAME' => 'idx_email',
                    'COLUMN_NAME' => 'email',
                    'NON_UNIQUE' => '0',
                    'INDEX_TYPE' => 'BTREE',
                    'SEQ_IN_INDEX' => '1',
                ],
                [
                    'INDEX_NAME' => 'idx_name',
                    'COLUMN_NAME' => 'name',
                    'NON_UNIQUE' => '1',
                    'INDEX_TYPE' => 'BTREE',
                    'SEQ_IN_INDEX' => '1',
                ],
            ],
        ]);

        $introspector = new MySqlIntrospector($connection, 'testdb');
        $indexes = $introspector->getIndexes('users');

        expect($indexes[0]->type)->toBe(IndexType::Unique);
        expect($indexes[1]->type)->toBe(IndexType::Btree);
    });

    it('reads foreign keys from information_schema.key_column_usage', function (): void {
        $connection = createMockConnection([
            'key_column_usage' => [
                [
                    'CONSTRAINT_NAME' => 'fk_posts_user',
                    'COLUMN_NAME' => 'user_id',
                    'REFERENCED_TABLE_NAME' => 'users',
                    'REFERENCED_COLUMN_NAME' => 'id',
                    'ORDINAL_POSITION' => '1',
                ],
            ],
            'referential_constraints' => [
                [
                    'CONSTRAINT_NAME' => 'fk_posts_user',
                    'DELETE_RULE' => 'CASCADE',
                    'UPDATE_RULE' => 'NO ACTION',
                ],
            ],
        ]);

        $introspector = new MySqlIntrospector($connection, 'testdb');
        $foreignKeys = $introspector->getForeignKeys('posts');

        expect($foreignKeys)->toHaveCount(1);
        expect($foreignKeys[0])->toBeInstanceOf(ForeignKey::class);
        expect($foreignKeys[0]->name)->toBe('fk_posts_user');
        expect($foreignKeys[0]->columns)->toBe(['user_id']);
        expect($foreignKeys[0]->referencedTable)->toBe('users');
        expect($foreignKeys[0]->referencedColumns)->toBe(['id']);
    });

    it('detects ON DELETE and ON UPDATE actions', function (): void {
        $connection = createMockConnection([
            'key_column_usage' => [
                [
                    'CONSTRAINT_NAME' => 'fk_orders_user',
                    'COLUMN_NAME' => 'user_id',
                    'REFERENCED_TABLE_NAME' => 'users',
                    'REFERENCED_COLUMN_NAME' => 'id',
                    'ORDINAL_POSITION' => '1',
                ],
            ],
            'referential_constraints' => [
                [
                    'CONSTRAINT_NAME' => 'fk_orders_user',
                    'DELETE_RULE' => 'SET NULL',
                    'UPDATE_RULE' => 'CASCADE',
                ],
            ],
        ]);

        $introspector = new MySqlIntrospector($connection, 'testdb');
        $foreignKeys = $introspector->getForeignKeys('orders');

        expect($foreignKeys[0]->onDelete)->toBe('SET NULL');
        expect($foreignKeys[0]->onUpdate)->toBe('CASCADE');
    });

    it('filters to current database only', function (): void {
        $capturedQueries = [];

        $connection = new class ($capturedQueries) implements ConnectionInterface
        {
            public function __construct(
                private array &$capturedQueries,
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
                $this->capturedQueries[] = ['sql' => $sql, 'bindings' => $bindings];

                return [];
            }

            public function execute(
                string $sql,
                array $bindings = [],
            ): int {
                return 0;
            }

            public function prepare(
                string $sql,
            ): StatementInterface {
                throw new RuntimeException('Not implemented');
            }

            public function lastInsertId(): int
            {
                return 0;
            }
        };

        $introspector = new MySqlIntrospector($connection, 'my_app_db');
        $introspector->getTables();

        // Verify the query includes database filter
        expect($capturedQueries)->toHaveCount(1);
        expect($capturedQueries[0]['bindings'])->toContain('my_app_db');
    });

    it('checks if table exists', function (): void {
        $connection = createMockConnection([
            'information_schema.tables' => [
                ['TABLE_NAME' => 'users'],
                ['TABLE_NAME' => 'posts'],
            ],
        ]);

        $introspector = new MySqlIntrospector($connection, 'testdb');

        expect($introspector->tableExists('users'))->toBeTrue();
        expect($introspector->tableExists('nonexistent'))->toBeFalse();
    });

    it('gets table schema with columns and indexes', function (): void {
        $queryResults = [];
        $callOrder = [];

        $connection = new class ($queryResults, $callOrder) implements ConnectionInterface
        {
            public function __construct(
                private array &$queryResults,
                private array &$callOrder,
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
                if (str_contains($sql, 'information_schema.tables')) {
                    $this->callOrder[] = 'tables';

                    return [['TABLE_NAME' => 'users']];
                }

                if (str_contains($sql, 'information_schema.columns')) {
                    $this->callOrder[] = 'columns';

                    return [
                        [
                            'COLUMN_NAME' => 'id',
                            'DATA_TYPE' => 'int',
                            'CHARACTER_MAXIMUM_LENGTH' => null,
                            'IS_NULLABLE' => 'NO',
                            'COLUMN_DEFAULT' => null,
                            'EXTRA' => 'auto_increment',
                        ],
                    ];
                }

                if (str_contains($sql, 'information_schema.statistics')) {
                    $this->callOrder[] = 'indexes';

                    return [
                        [
                            'INDEX_NAME' => 'idx_id',
                            'COLUMN_NAME' => 'id',
                            'NON_UNIQUE' => '0',
                            'INDEX_TYPE' => 'BTREE',
                            'SEQ_IN_INDEX' => '1',
                        ],
                    ];
                }

                return [];
            }

            public function execute(
                string $sql,
                array $bindings = [],
            ): int {
                return 0;
            }

            public function prepare(
                string $sql,
            ): StatementInterface {
                throw new RuntimeException('Not implemented');
            }

            public function lastInsertId(): int
            {
                return 0;
            }
        };

        $introspector = new MySqlIntrospector($connection, 'testdb');
        $table = $introspector->getTable('users');

        expect($table)->toBeInstanceOf(Table::class);
        expect($table->name)->toBe('users');
        expect($table->columns)->toHaveCount(1);
        expect($table->indexes)->toHaveCount(1);
    });

    it('returns null for non-existent table', function (): void {
        $connection = createMockConnection([
            'information_schema.tables' => [],
        ]);

        $introspector = new MySqlIntrospector($connection, 'testdb');
        $table = $introspector->getTable('nonexistent');

        expect($table)->toBeNull();
    });

    it('gets primary key columns', function (): void {
        $connection = createMockConnection([
            "INDEX_NAME = 'PRIMARY'" => [
                [
                    'COLUMN_NAME' => 'id',
                ],
            ],
        ]);

        $introspector = new MySqlIntrospector($connection, 'testdb');
        $primaryKey = $introspector->getPrimaryKey('users');

        expect($primaryKey)->toBe(['id']);
    });

    it('gets composite primary key columns', function (): void {
        $connection = createMockConnection([
            "INDEX_NAME = 'PRIMARY'" => [
                [
                    'COLUMN_NAME' => 'post_id',
                ],
                [
                    'COLUMN_NAME' => 'tag_id',
                ],
            ],
        ]);

        $introspector = new MySqlIntrospector($connection, 'testdb');
        $primaryKey = $introspector->getPrimaryKey('post_tags');

        expect($primaryKey)->toBe(['post_id', 'tag_id']);
    });

    it('detects fulltext indexes', function (): void {
        $connection = createMockConnection([
            'information_schema.statistics' => [
                [
                    'INDEX_NAME' => 'ft_content',
                    'COLUMN_NAME' => 'content',
                    'NON_UNIQUE' => '1',
                    'INDEX_TYPE' => 'FULLTEXT',
                    'SEQ_IN_INDEX' => '1',
                ],
            ],
        ]);

        $introspector = new MySqlIntrospector($connection, 'testdb');
        $indexes = $introspector->getIndexes('posts');

        expect($indexes[0]->type)->toBe(IndexType::Fulltext);
    });

    it('handles composite foreign keys', function (): void {
        $connection = createMockConnection([
            'key_column_usage' => [
                [
                    'CONSTRAINT_NAME' => 'fk_composite',
                    'COLUMN_NAME' => 'tenant_id',
                    'REFERENCED_TABLE_NAME' => 'tenants',
                    'REFERENCED_COLUMN_NAME' => 'id',
                    'ORDINAL_POSITION' => '1',
                ],
                [
                    'CONSTRAINT_NAME' => 'fk_composite',
                    'COLUMN_NAME' => 'user_id',
                    'REFERENCED_TABLE_NAME' => 'tenants',
                    'REFERENCED_COLUMN_NAME' => 'user_id',
                    'ORDINAL_POSITION' => '2',
                ],
            ],
            'referential_constraints' => [
                [
                    'CONSTRAINT_NAME' => 'fk_composite',
                    'DELETE_RULE' => 'CASCADE',
                    'UPDATE_RULE' => 'CASCADE',
                ],
            ],
        ]);

        $introspector = new MySqlIntrospector($connection, 'testdb');
        $foreignKeys = $introspector->getForeignKeys('orders');

        expect($foreignKeys)->toHaveCount(1);
        expect($foreignKeys[0]->columns)->toBe(['tenant_id', 'user_id']);
        expect($foreignKeys[0]->referencedColumns)->toBe(['id', 'user_id']);
    });

    it('handles empty table list', function (): void {
        $connection = createMockConnection([
            'information_schema.tables' => [],
        ]);

        $introspector = new MySqlIntrospector($connection, 'testdb');
        $tables = $introspector->getTables();

        expect($tables)->toBe([]);
    });

    it('handles table with no indexes', function (): void {
        $connection = createMockConnection([
            'information_schema.statistics' => [],
        ]);

        $introspector = new MySqlIntrospector($connection, 'testdb');
        $indexes = $introspector->getIndexes('simple_table');

        expect($indexes)->toBe([]);
    });

    it('handles table with no foreign keys', function (): void {
        $connection = createMockConnection([
            'key_column_usage' => [],
        ]);

        $introspector = new MySqlIntrospector($connection, 'testdb');
        $foreignKeys = $introspector->getForeignKeys('standalone_table');

        expect($foreignKeys)->toBe([]);
    });
});
