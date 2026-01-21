<?php

declare(strict_types=1);

use Marko\Database\Connection\ConnectionInterface;
use Marko\Database\Connection\StatementInterface;
use Marko\Database\Introspection\IntrospectorInterface;
use Marko\Database\PgSql\Introspection\PgSqlIntrospector;
use Marko\Database\Schema\Column;
use Marko\Database\Schema\ForeignKey;
use Marko\Database\Schema\Index;
use Marko\Database\Schema\IndexType;
use Marko\Database\Schema\Table;

describe('PgSqlIntrospector', function (): void {
    it('implements IntrospectorInterface', function (): void {
        $reflection = new ReflectionClass(PgSqlIntrospector::class);

        expect($reflection->implementsInterface(IntrospectorInterface::class))->toBeTrue();
    });

    it('reads table list from information_schema.tables', function (): void {
        $queriedSql = null;
        $queriedBindings = null;

        $connection = createTestConnection(
            function (string $sql, array $bindings) use (&$queriedSql, &$queriedBindings): array {
                $queriedSql = $sql;
                $queriedBindings = $bindings;

                return [
                    ['table_name' => 'users'],
                    ['table_name' => 'posts'],
                    ['table_name' => 'comments'],
                ];
            },
        );

        $introspector = new PgSqlIntrospector($connection);
        $tables = $introspector->getTables();

        expect($tables)->toBe(['users', 'posts', 'comments'])
            ->and($queriedSql)->toContain('information_schema.tables')
            ->and($queriedSql)->toContain('table_schema')
            ->and($queriedSql)->toContain("table_type = 'BASE TABLE'")
            ->and($queriedBindings)->toBe(['public']);
    });

    it('reads column definitions from information_schema.columns', function (): void {
        $queries = [];

        $connection = createTestConnection(function (string $sql, array $bindings) use (&$queries): array {
            $queries[] = ['sql' => $sql, 'bindings' => $bindings];

            // Return appropriate data based on query
            if (str_contains($sql, 'information_schema.columns')) {
                return [
                    [
                        'column_name' => 'id',
                        'data_type' => 'integer',
                        'character_maximum_length' => null,
                        'is_nullable' => 'NO',
                        'column_default' => "nextval('users_id_seq'::regclass)",
                        'is_identity' => 'YES',
                        'identity_generation' => 'BY DEFAULT',
                    ],
                    [
                        'column_name' => 'name',
                        'data_type' => 'character varying',
                        'character_maximum_length' => 255,
                        'is_nullable' => 'NO',
                        'column_default' => null,
                        'is_identity' => 'NO',
                        'identity_generation' => null,
                    ],
                ];
            }

            // Primary key query
            if (str_contains($sql, 'pg_constraint') && str_contains($sql, "'p'")) {
                return [['column_name' => 'id']];
            }

            // Unique constraint query
            return [];
        });

        $introspector = new PgSqlIntrospector($connection);
        $columns = $introspector->getColumns('users');

        // Verify the columns query was made
        $columnQuery = array_filter($queries, fn ($q) => str_contains($q['sql'], 'information_schema.columns'));
        expect($columnQuery)->not->toBeEmpty();

        $firstColumnQuery = array_values($columnQuery)[0];
        expect($firstColumnQuery['bindings'])->toContain('users')
            ->and($firstColumnQuery['bindings'])->toContain('public')
            // Verify results
            ->and($columns)->toHaveCount(2)
            ->and($columns[0])->toBeInstanceOf(Column::class)
            ->and($columns[0]->name)->toBe('id')
            ->and($columns[1]->name)->toBe('name');
    });

    it('maps PostgreSQL data types to Column value objects', function (): void {
        $connection = createTestConnection(function (string $sql): array {
            if (str_contains($sql, 'information_schema.columns')) {
                return [
                    ['column_name' => 'int_col', 'data_type' => 'integer', 'character_maximum_length' => null, 'is_nullable' => 'NO', 'column_default' => null, 'is_identity' => 'NO', 'identity_generation' => null],
                    ['column_name' => 'bigint_col', 'data_type' => 'bigint', 'character_maximum_length' => null, 'is_nullable' => 'NO', 'column_default' => null, 'is_identity' => 'NO', 'identity_generation' => null],
                    ['column_name' => 'varchar_col', 'data_type' => 'character varying', 'character_maximum_length' => 100, 'is_nullable' => 'NO', 'column_default' => null, 'is_identity' => 'NO', 'identity_generation' => null],
                    ['column_name' => 'text_col', 'data_type' => 'text', 'character_maximum_length' => null, 'is_nullable' => 'NO', 'column_default' => null, 'is_identity' => 'NO', 'identity_generation' => null],
                    ['column_name' => 'bool_col', 'data_type' => 'boolean', 'character_maximum_length' => null, 'is_nullable' => 'NO', 'column_default' => null, 'is_identity' => 'NO', 'identity_generation' => null],
                    ['column_name' => 'timestamp_col', 'data_type' => 'timestamp without time zone', 'character_maximum_length' => null, 'is_nullable' => 'NO', 'column_default' => null, 'is_identity' => 'NO', 'identity_generation' => null],
                    ['column_name' => 'timestamptz_col', 'data_type' => 'timestamp with time zone', 'character_maximum_length' => null, 'is_nullable' => 'NO', 'column_default' => null, 'is_identity' => 'NO', 'identity_generation' => null],
                    ['column_name' => 'decimal_col', 'data_type' => 'numeric', 'character_maximum_length' => null, 'is_nullable' => 'NO', 'column_default' => null, 'is_identity' => 'NO', 'identity_generation' => null],
                    ['column_name' => 'smallint_col', 'data_type' => 'smallint', 'character_maximum_length' => null, 'is_nullable' => 'NO', 'column_default' => null, 'is_identity' => 'NO', 'identity_generation' => null],
                    ['column_name' => 'real_col', 'data_type' => 'real', 'character_maximum_length' => null, 'is_nullable' => 'NO', 'column_default' => null, 'is_identity' => 'NO', 'identity_generation' => null],
                    ['column_name' => 'double_col', 'data_type' => 'double precision', 'character_maximum_length' => null, 'is_nullable' => 'NO', 'column_default' => null, 'is_identity' => 'NO', 'identity_generation' => null],
                    ['column_name' => 'date_col', 'data_type' => 'date', 'character_maximum_length' => null, 'is_nullable' => 'NO', 'column_default' => null, 'is_identity' => 'NO', 'identity_generation' => null],
                    ['column_name' => 'time_col', 'data_type' => 'time without time zone', 'character_maximum_length' => null, 'is_nullable' => 'NO', 'column_default' => null, 'is_identity' => 'NO', 'identity_generation' => null],
                    ['column_name' => 'json_col', 'data_type' => 'json', 'character_maximum_length' => null, 'is_nullable' => 'NO', 'column_default' => null, 'is_identity' => 'NO', 'identity_generation' => null],
                    ['column_name' => 'jsonb_col', 'data_type' => 'jsonb', 'character_maximum_length' => null, 'is_nullable' => 'NO', 'column_default' => null, 'is_identity' => 'NO', 'identity_generation' => null],
                    ['column_name' => 'uuid_col', 'data_type' => 'uuid', 'character_maximum_length' => null, 'is_nullable' => 'NO', 'column_default' => null, 'is_identity' => 'NO', 'identity_generation' => null],
                    ['column_name' => 'char_col', 'data_type' => 'character', 'character_maximum_length' => 10, 'is_nullable' => 'NO', 'column_default' => null, 'is_identity' => 'NO', 'identity_generation' => null],
                    ['column_name' => 'bytea_col', 'data_type' => 'bytea', 'character_maximum_length' => null, 'is_nullable' => 'NO', 'column_default' => null, 'is_identity' => 'NO', 'identity_generation' => null],
                ];
            }

            return [];
        });

        $introspector = new PgSqlIntrospector($connection);
        $columns = $introspector->getColumns('test_types');

        expect($columns[0]->type)->toBe('integer')
            ->and($columns[1]->type)->toBe('bigint')
            ->and($columns[2]->type)->toBe('varchar')
            ->and($columns[2]->length)->toBe(100)
            ->and($columns[3]->type)->toBe('text')
            ->and($columns[4]->type)->toBe('boolean')
            ->and($columns[5]->type)->toBe('timestamp')
            ->and($columns[6]->type)->toBe('timestamptz')
            ->and($columns[7]->type)->toBe('decimal')
            ->and($columns[8]->type)->toBe('smallint')
            ->and($columns[9]->type)->toBe('float')
            ->and($columns[10]->type)->toBe('double')
            ->and($columns[11]->type)->toBe('date')
            ->and($columns[12]->type)->toBe('time')
            ->and($columns[13]->type)->toBe('json')
            ->and($columns[14]->type)->toBe('jsonb')
            ->and($columns[15]->type)->toBe('uuid')
            ->and($columns[16]->type)->toBe('char')
            ->and($columns[16]->length)->toBe(10)
            ->and($columns[17]->type)->toBe('blob');
    });

    it('detects nullable columns', function (): void {
        $connection = createTestConnection(function (string $sql): array {
            if (str_contains($sql, 'information_schema.columns')) {
                return [
                    ['column_name' => 'required_col', 'data_type' => 'character varying', 'character_maximum_length' => 255, 'is_nullable' => 'NO', 'column_default' => null, 'is_identity' => 'NO', 'identity_generation' => null],
                    ['column_name' => 'optional_col', 'data_type' => 'character varying', 'character_maximum_length' => 255, 'is_nullable' => 'YES', 'column_default' => null, 'is_identity' => 'NO', 'identity_generation' => null],
                ];
            }

            return [];
        });

        $introspector = new PgSqlIntrospector($connection);
        $columns = $introspector->getColumns('users');

        expect($columns[0]->nullable)->toBeFalse()
            ->and($columns[1]->nullable)->toBeTrue();
    });

    it('detects default values including sequences', function (): void {
        $connection = createTestConnection(function (string $sql): array {
            if (str_contains($sql, 'information_schema.columns')) {
                return [
                    ['column_name' => 'status', 'data_type' => 'character varying', 'character_maximum_length' => 50, 'is_nullable' => 'NO', 'column_default' => "'active'::character varying", 'is_identity' => 'NO', 'identity_generation' => null],
                    ['column_name' => 'count', 'data_type' => 'integer', 'character_maximum_length' => null, 'is_nullable' => 'NO', 'column_default' => '0', 'is_identity' => 'NO', 'identity_generation' => null],
                    ['column_name' => 'created_at', 'data_type' => 'timestamp without time zone', 'character_maximum_length' => null, 'is_nullable' => 'NO', 'column_default' => 'CURRENT_TIMESTAMP', 'is_identity' => 'NO', 'identity_generation' => null],
                    ['column_name' => 'id', 'data_type' => 'integer', 'character_maximum_length' => null, 'is_nullable' => 'NO', 'column_default' => "nextval('users_id_seq'::regclass)", 'is_identity' => 'NO', 'identity_generation' => null],
                    ['column_name' => 'flag', 'data_type' => 'boolean', 'character_maximum_length' => null, 'is_nullable' => 'NO', 'column_default' => 'true', 'is_identity' => 'NO', 'identity_generation' => null],
                    ['column_name' => 'flag2', 'data_type' => 'boolean', 'character_maximum_length' => null, 'is_nullable' => 'NO', 'column_default' => 'false', 'is_identity' => 'NO', 'identity_generation' => null],
                    ['column_name' => 'price', 'data_type' => 'numeric', 'character_maximum_length' => null, 'is_nullable' => 'NO', 'column_default' => '10.50', 'is_identity' => 'NO', 'identity_generation' => null],
                ];
            }

            return [];
        });

        $introspector = new PgSqlIntrospector($connection);
        $columns = $introspector->getColumns('users');

        expect($columns[0]->default)->toBe('active')
            ->and($columns[1]->default)->toBe(0)
            ->and($columns[2]->default)->toBe('CURRENT_TIMESTAMP')
            // Sequence defaults should be treated as auto_increment, not as regular defaults
            ->and($columns[3]->autoIncrement)->toBeTrue()
            ->and($columns[3]->default)->toBeNull()
            ->and($columns[4]->default)->toBeTrue()
            ->and($columns[5]->default)->toBeFalse()
            ->and($columns[6]->default)->toBe(10.50);
    });

    it('detects serial/identity columns', function (): void {
        $connection = createTestConnection(function (string $sql): array {
            if (str_contains($sql, 'information_schema.columns')) {
                return [
                    ['column_name' => 'id', 'data_type' => 'integer', 'character_maximum_length' => null, 'is_nullable' => 'NO', 'column_default' => "nextval('users_id_seq'::regclass)", 'is_identity' => 'NO', 'identity_generation' => null],
                    ['column_name' => 'identity_id', 'data_type' => 'integer', 'character_maximum_length' => null, 'is_nullable' => 'NO', 'column_default' => null, 'is_identity' => 'YES', 'identity_generation' => 'BY DEFAULT'],
                    ['column_name' => 'always_identity_id', 'data_type' => 'bigint', 'character_maximum_length' => null, 'is_nullable' => 'NO', 'column_default' => null, 'is_identity' => 'YES', 'identity_generation' => 'ALWAYS'],
                    ['column_name' => 'name', 'data_type' => 'character varying', 'character_maximum_length' => 255, 'is_nullable' => 'NO', 'column_default' => null, 'is_identity' => 'NO', 'identity_generation' => null],
                ];
            }

            if (str_contains($sql, 'pg_constraint') && str_contains($sql, "'p'")) {
                return [['column_name' => 'id']];
            }

            return [];
        });

        $introspector = new PgSqlIntrospector($connection);
        $columns = $introspector->getColumns('users');

        expect($columns[0]->autoIncrement)->toBeTrue()
            ->and($columns[1]->autoIncrement)->toBeTrue()
            ->and($columns[2]->autoIncrement)->toBeTrue()
            ->and($columns[3]->autoIncrement)->toBeFalse();
    });

    it('reads indexes from pg_indexes', function (): void {
        $queriedSql = null;
        $queriedBindings = null;

        $connection = createTestConnection(
            function (string $sql, array $bindings) use (&$queriedSql, &$queriedBindings): array {
                if (str_contains($sql, 'pg_indexes')) {
                    $queriedSql = $sql;
                    $queriedBindings = $bindings;

                    return [
                        ['indexname' => 'users_email_idx', 'indexdef' => 'CREATE INDEX users_email_idx ON public.users USING btree (email)'],
                        ['indexname' => 'users_name_status_idx', 'indexdef' => 'CREATE INDEX users_name_status_idx ON public.users USING btree (name, status)'],
                    ];
                }

                return [];
            },
        );

        $introspector = new PgSqlIntrospector($connection);
        $indexes = $introspector->getIndexes('users');

        expect($queriedSql)->toContain('pg_indexes')
            ->and($queriedSql)->toContain('tablename')
            ->and($queriedBindings)->toBe(['users', 'public'])
            ->and($indexes)->toHaveCount(2)
            ->and($indexes[0])->toBeInstanceOf(Index::class)
            ->and($indexes[0]->name)->toBe('users_email_idx')
            ->and($indexes[0]->columns)->toBe(['email'])
            ->and($indexes[0]->type)->toBe(IndexType::Btree)
            ->and($indexes[1]->name)->toBe('users_name_status_idx')
            ->and($indexes[1]->columns)->toBe(['name', 'status']);
    });

    it('detects unique indexes', function (): void {
        $connection = createTestConnection(function (string $sql): array {
            if (str_contains($sql, 'pg_indexes')) {
                return [
                    ['indexname' => 'users_email_unique', 'indexdef' => 'CREATE UNIQUE INDEX users_email_unique ON public.users USING btree (email)'],
                    ['indexname' => 'users_name_idx', 'indexdef' => 'CREATE INDEX users_name_idx ON public.users USING btree (name)'],
                ];
            }

            return [];
        });

        $introspector = new PgSqlIntrospector($connection);
        $indexes = $introspector->getIndexes('users');

        expect($indexes[0]->type)->toBe(IndexType::Unique)
            ->and($indexes[1]->type)->toBe(IndexType::Btree);
    });

    it('reads foreign keys from information_schema.table_constraints', function (): void {
        $queriedSql = null;
        $queriedBindings = null;

        $connection = createTestConnection(
            function (string $sql, array $bindings) use (&$queriedSql, &$queriedBindings): array {
                if (str_contains($sql, 'information_schema.table_constraints')) {
                    $queriedSql = $sql;
                    $queriedBindings = $bindings;

                    return [
                        [
                            'constraint_name' => 'posts_user_id_fkey',
                            'column_name' => 'user_id',
                            'referenced_table' => 'users',
                            'referenced_column' => 'id',
                            'delete_rule' => 'CASCADE',
                            'update_rule' => 'NO ACTION',
                        ],
                    ];
                }

                return [];
            },
        );

        $introspector = new PgSqlIntrospector($connection);
        $foreignKeys = $introspector->getForeignKeys('posts');

        expect($queriedSql)->toContain('information_schema.table_constraints')
            ->and($queriedSql)->toContain('key_column_usage')
            ->and($queriedSql)->toContain('referential_constraints')
            ->and($queriedBindings)->toBe(['posts', 'public'])
            ->and($foreignKeys)->toHaveCount(1)
            ->and($foreignKeys[0])->toBeInstanceOf(ForeignKey::class)
            ->and($foreignKeys[0]->name)->toBe('posts_user_id_fkey')
            ->and($foreignKeys[0]->columns)->toBe(['user_id'])
            ->and($foreignKeys[0]->referencedTable)->toBe('users')
            ->and($foreignKeys[0]->referencedColumns)->toBe(['id']);
    });

    it('detects ON DELETE and ON UPDATE actions', function (): void {
        $connection = createTestConnection(function (string $sql): array {
            if (str_contains($sql, 'information_schema.table_constraints')) {
                return [
                    [
                        'constraint_name' => 'posts_user_id_fkey',
                        'column_name' => 'user_id',
                        'referenced_table' => 'users',
                        'referenced_column' => 'id',
                        'delete_rule' => 'CASCADE',
                        'update_rule' => 'SET NULL',
                    ],
                    [
                        'constraint_name' => 'posts_category_id_fkey',
                        'column_name' => 'category_id',
                        'referenced_table' => 'categories',
                        'referenced_column' => 'id',
                        'delete_rule' => 'SET NULL',
                        'update_rule' => 'CASCADE',
                    ],
                ];
            }

            return [];
        });

        $introspector = new PgSqlIntrospector($connection);
        $foreignKeys = $introspector->getForeignKeys('posts');

        expect($foreignKeys[0]->onDelete)->toBe('CASCADE')
            ->and($foreignKeys[0]->onUpdate)->toBe('SET NULL')
            ->and($foreignKeys[1]->onDelete)->toBe('SET NULL')
            ->and($foreignKeys[1]->onUpdate)->toBe('CASCADE');
    });

    it('filters to public schema by default', function (): void {
        $queriedBindings = null;

        $connection = createTestConnection(function (string $sql, array $bindings) use (&$queriedBindings): array {
            if (str_contains($sql, 'information_schema.tables')) {
                $queriedBindings = $bindings;

                return [['table_name' => 'users']];
            }

            return [];
        });

        $introspector = new PgSqlIntrospector($connection);
        $tables = $introspector->getTables();

        expect($queriedBindings)->toBe(['public'])
            ->and($tables)->toBe(['users']);
    });

    it('checks table existence correctly', function (): void {
        $connection = createTestConnection(function (string $sql, array $bindings): array {
            if (str_contains($sql, 'information_schema.tables') && in_array('users', $bindings, true)) {
                return [['table_name' => 'users']];
            }

            return [];
        });

        $introspector = new PgSqlIntrospector($connection);

        expect($introspector->tableExists('users'))->toBeTrue()
            ->and($introspector->tableExists('nonexistent'))->toBeFalse();
    });

    it('gets primary key columns', function (): void {
        $queriedSql = null;
        $queriedBindings = null;

        $connection = createTestConnection(
            function (string $sql, array $bindings) use (&$queriedSql, &$queriedBindings): array {
                if (str_contains($sql, 'pg_constraint')) {
                    $queriedSql = $sql;
                    $queriedBindings = $bindings;

                    return [['column_name' => 'id']];
                }

                return [];
            },
        );

        $introspector = new PgSqlIntrospector($connection);
        $primaryKey = $introspector->getPrimaryKey('users');

        expect($queriedSql)->toContain('pg_constraint')
            ->and($queriedSql)->toContain("contype = 'p'")
            ->and($queriedBindings)->toBe(['users', 'public'])
            ->and($primaryKey)->toBe(['id']);
    });

    it('gets composite primary key columns', function (): void {
        $connection = createTestConnection(function (string $sql): array {
            if (str_contains($sql, 'pg_constraint')) {
                return [
                    ['column_name' => 'post_id'],
                    ['column_name' => 'tag_id'],
                ];
            }

            return [];
        });

        $introspector = new PgSqlIntrospector($connection);
        $primaryKey = $introspector->getPrimaryKey('post_tags');

        expect($primaryKey)->toBe(['post_id', 'tag_id']);
    });

    it('gets full table schema', function (): void {
        $connection = createTestConnection(function (string $sql): array {
            if (str_contains($sql, 'information_schema.columns')) {
                return [
                    ['column_name' => 'id', 'data_type' => 'integer', 'character_maximum_length' => null, 'is_nullable' => 'NO', 'column_default' => "nextval('users_id_seq'::regclass)", 'is_identity' => 'NO', 'identity_generation' => null],
                    ['column_name' => 'name', 'data_type' => 'character varying', 'character_maximum_length' => 255, 'is_nullable' => 'NO', 'column_default' => null, 'is_identity' => 'NO', 'identity_generation' => null],
                ];
            }

            if (str_contains($sql, 'pg_constraint') && str_contains($sql, "'p'")) {
                return [['column_name' => 'id']];
            }

            if (str_contains($sql, 'pg_constraint') && str_contains($sql, "'u'")) {
                return [];
            }

            if (str_contains($sql, 'pg_indexes')) {
                return [
                    ['indexname' => 'users_name_idx', 'indexdef' => 'CREATE INDEX users_name_idx ON public.users USING btree (name)'],
                ];
            }

            return [];
        });

        $introspector = new PgSqlIntrospector($connection);
        $table = $introspector->getTable('users');

        expect($table)->toBeInstanceOf(Table::class)
            ->and($table->name)->toBe('users')
            ->and($table->columns)->toHaveCount(2)
            ->and($table->indexes)->toHaveCount(1);
    });

    it('returns null for nonexistent table', function (): void {
        $connection = createTestConnection(function (): array {
            return [];
        });

        $introspector = new PgSqlIntrospector($connection);
        $table = $introspector->getTable('nonexistent');

        expect($table)->toBeNull();
    });

    it('handles multi-column foreign keys', function (): void {
        $connection = createTestConnection(function (string $sql): array {
            if (str_contains($sql, 'information_schema.table_constraints')) {
                return [
                    ['constraint_name' => 'order_items_fkey', 'column_name' => 'order_id', 'referenced_table' => 'orders', 'referenced_column' => 'id', 'delete_rule' => 'CASCADE', 'update_rule' => 'NO ACTION'],
                    ['constraint_name' => 'order_items_fkey', 'column_name' => 'item_id', 'referenced_table' => 'orders', 'referenced_column' => 'item_num', 'delete_rule' => 'CASCADE', 'update_rule' => 'NO ACTION'],
                ];
            }

            return [];
        });

        $introspector = new PgSqlIntrospector($connection);
        $foreignKeys = $introspector->getForeignKeys('order_items');

        expect($foreignKeys)->toHaveCount(1)
            ->and($foreignKeys[0]->columns)->toBe(['order_id', 'item_id'])
            ->and($foreignKeys[0]->referencedColumns)->toBe(['id', 'item_num']);
    });

    it('detects unique constraint columns', function (): void {
        $connection = createTestConnection(function (string $sql): array {
            if (str_contains($sql, 'information_schema.columns')) {
                return [
                    ['column_name' => 'id', 'data_type' => 'integer', 'character_maximum_length' => null, 'is_nullable' => 'NO', 'column_default' => null, 'is_identity' => 'NO', 'identity_generation' => null],
                    ['column_name' => 'email', 'data_type' => 'character varying', 'character_maximum_length' => 255, 'is_nullable' => 'NO', 'column_default' => null, 'is_identity' => 'NO', 'identity_generation' => null],
                ];
            }

            if (str_contains($sql, 'pg_constraint') && str_contains($sql, "'p'")) {
                return [['column_name' => 'id']];
            }

            if (str_contains($sql, 'pg_constraint') && str_contains($sql, "'u'")) {
                return [['column_name' => 'email']];
            }

            return [];
        });

        $introspector = new PgSqlIntrospector($connection);
        $columns = $introspector->getColumns('users');

        expect($columns[0]->unique)->toBeFalse() // Primary key column is not marked unique separately
            ->and($columns[1]->unique)->toBeTrue(); // email has unique constraint
    });

    it('marks primary key columns correctly', function (): void {
        $connection = createTestConnection(function (string $sql): array {
            if (str_contains($sql, 'information_schema.columns')) {
                return [
                    ['column_name' => 'id', 'data_type' => 'integer', 'character_maximum_length' => null, 'is_nullable' => 'NO', 'column_default' => null, 'is_identity' => 'NO', 'identity_generation' => null],
                    ['column_name' => 'name', 'data_type' => 'character varying', 'character_maximum_length' => 255, 'is_nullable' => 'NO', 'column_default' => null, 'is_identity' => 'NO', 'identity_generation' => null],
                ];
            }

            if (str_contains($sql, 'pg_constraint') && str_contains($sql, "'p'")) {
                return [['column_name' => 'id']];
            }

            return [];
        });

        $introspector = new PgSqlIntrospector($connection);
        $columns = $introspector->getColumns('users');

        expect($columns[0]->primaryKey)->toBeTrue()
            ->and($columns[1]->primaryKey)->toBeFalse();
    });
});

/**
 * Helper function to create a test connection with a custom query callback.
 *
 * @param callable(string, array): array<array<string, mixed>> $queryCallback
 */
function createTestConnection(
    callable $queryCallback,
): ConnectionInterface {
    return new class ($queryCallback) implements ConnectionInterface
    {
        /**
         * @param callable(string, array): array<array<string, mixed>> $queryCallback
         */
        public function __construct(
            private readonly mixed $queryCallback,
        ) {}

        public function connect(): void
        {
            // No-op for test
        }

        public function disconnect(): void
        {
            // No-op for test
        }

        public function isConnected(): bool
        {
            return true;
        }

        /**
         * @param string $sql
         * @param array $bindings
         * @return array<array<string, mixed>>
         */
        public function query(
            string $sql,
            array $bindings = [],
        ): array {
            return ($this->queryCallback)($sql, $bindings);
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
            throw new RuntimeException('Not implemented in test');
        }

        public function lastInsertId(): int
        {
            return 0;
        }
    };
}
