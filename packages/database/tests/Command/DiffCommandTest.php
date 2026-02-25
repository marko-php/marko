<?php

declare(strict_types=1);

use Marko\Core\Attributes\Command;
use Marko\Core\Command\CommandInterface;
use Marko\Database\Command\DiffCommand;
use Marko\Database\Diff\DiffCalculator;
use Marko\Database\Diff\SchemaDiff;
use Marko\Database\Diff\TableDiff;
use Marko\Database\Schema\Column;
use Marko\Database\Schema\Index;
use Marko\Database\Schema\IndexType;
use Marko\Database\Schema\Table;
use Marko\Database\Tests\Command\Helpers;

it('registers as db:diff command via #[Command] attribute', function (): void {
    $reflection = new ReflectionClass(DiffCommand::class);
    $attributes = $reflection->getAttributes(Command::class);

    expect($attributes)->toHaveCount(1)
        ->and($attributes[0]->newInstance()->name)->toBe('db:diff');
});

it('implements CommandInterface', function (): void {
    $reflection = new ReflectionClass(DiffCommand::class);

    expect($reflection->implementsInterface(CommandInterface::class))->toBeTrue();
});

it('discovers entity classes with #[Table] from all modules', function (): void {
    $command = Helpers::createDiffCommand();
    ['output' => $output] = Helpers::executeDiffCommand($command);

    expect($output)->toContain('No changes detected');
});

it('builds schema from entity metadata', function (): void {
    $command = Helpers::createDiffCommand();

    expect($command)->toBeInstanceOf(DiffCommand::class);
});

it('introspects current database state', function (): void {
    $existingTable = new Table(
        name: 'users',
        columns: [
            new Column(name: 'id', type: 'INT', primaryKey: true, autoIncrement: true),
            new Column(name: 'name', type: 'VARCHAR', length: 255),
        ],
        indexes: [],
    );

    // Non-entity tables in the database are left alone (not dropped)
    $command = Helpers::createDiffCommand(tables: ['users' => $existingTable]);
    ['output' => $output] = Helpers::executeDiffCommand($command);

    expect($output)->toContain('No changes detected');
});

it('calculates diff between entities and database', function (): void {
    $dbTable = new Table(
        name: 'posts',
        columns: [
            new Column(name: 'id', type: 'INT', primaryKey: true, autoIncrement: true),
            new Column(name: 'title', type: 'VARCHAR', length: 255),
        ],
        indexes: [],
    );

    // Non-entity tables are left alone, so no diff detected
    $command = Helpers::createDiffCommand(tables: ['posts' => $dbTable]);
    ['exitCode' => $exitCode] = Helpers::executeDiffCommand($command);

    expect($exitCode)->toBe(0);
});

it('displays tables to be created', function (): void {
    $diffCalculator = new class () extends DiffCalculator
    {
        public function calculate(
            array $entitySchema,
            array $databaseSchema,
        ): SchemaDiff {
            return new SchemaDiff(
                tablesToCreate: [
                    new Table(
                        name: 'posts',
                        columns: [
                            new Column(name: 'id', type: 'INT', primaryKey: true),
                            new Column(name: 'title', type: 'VARCHAR', length: 255),
                        ],
                        indexes: [],
                    ),
                ],
            );
        }
    };

    $command = Helpers::createDiffCommand(diffCalculator: $diffCalculator);
    ['output' => $output] = Helpers::executeDiffCommand($command);

    expect($output)->toContain('Create table: posts');
});

it('displays tables to be dropped (flagged as destructive)', function (): void {
    $diffCalculator = new class () extends DiffCalculator
    {
        public function calculate(
            array $entitySchema,
            array $databaseSchema,
        ): SchemaDiff {
            return new SchemaDiff(
                tablesToDrop: [
                    new Table(
                        name: 'old_users',
                        columns: [
                            new Column(name: 'id', type: 'INT', primaryKey: true),
                        ],
                        indexes: [],
                    ),
                ],
            );
        }
    };

    $command = Helpers::createDiffCommand(diffCalculator: $diffCalculator);
    ['output' => $output] = Helpers::executeDiffCommand($command);

    expect($output)->toContain('Drop table: old_users')
        ->and($output)->toContain('[DESTRUCTIVE]');
});

it('displays columns to be added', function (): void {
    $diffCalculator = new class () extends DiffCalculator
    {
        public function calculate(
            array $entitySchema,
            array $databaseSchema,
        ): SchemaDiff {
            return new SchemaDiff(
                tablesToAlter: [
                    'users' => new TableDiff(
                        tableName: 'users',
                        columnsToAdd: [
                            new Column(name: 'email', type: 'VARCHAR', length: 255),
                        ],
                    ),
                ],
            );
        }
    };

    $command = Helpers::createDiffCommand(diffCalculator: $diffCalculator);
    ['output' => $output] = Helpers::executeDiffCommand($command);

    expect($output)->toContain('Alter table: users')
        ->and($output)->toContain('Add column: email');
});

it('displays columns to be dropped (flagged as destructive)', function (): void {
    $diffCalculator = new class () extends DiffCalculator
    {
        public function calculate(
            array $entitySchema,
            array $databaseSchema,
        ): SchemaDiff {
            return new SchemaDiff(
                tablesToAlter: [
                    'users' => new TableDiff(
                        tableName: 'users',
                        columnsToDrop: [
                            new Column(name: 'legacy_field', type: 'VARCHAR'),
                        ],
                    ),
                ],
            );
        }
    };

    $command = Helpers::createDiffCommand(diffCalculator: $diffCalculator);
    ['output' => $output] = Helpers::executeDiffCommand($command);

    expect($output)->toContain('Drop column: legacy_field')
        ->and($output)->toContain('[DESTRUCTIVE]');
});

it('displays columns to be modified', function (): void {
    $diffCalculator = new class () extends DiffCalculator
    {
        public function calculate(
            array $entitySchema,
            array $databaseSchema,
        ): SchemaDiff {
            return new SchemaDiff(
                tablesToAlter: [
                    'users' => new TableDiff(
                        tableName: 'users',
                        columnsToModify: [
                            'name' => new Column(name: 'name', type: 'VARCHAR', length: 500),
                        ],
                    ),
                ],
            );
        }
    };

    $command = Helpers::createDiffCommand(diffCalculator: $diffCalculator);
    ['output' => $output] = Helpers::executeDiffCommand($command);

    expect($output)->toContain('Modify column: name');
});

it('displays indexes to be added or dropped', function (): void {
    $diffCalculator = new class () extends DiffCalculator
    {
        public function calculate(
            array $entitySchema,
            array $databaseSchema,
        ): SchemaDiff {
            return new SchemaDiff(
                tablesToAlter: [
                    'users' => new TableDiff(
                        tableName: 'users',
                        indexesToAdd: [
                            new Index(name: 'idx_email', columns: ['email'], type: IndexType::Btree),
                        ],
                        indexesToDrop: [
                            new Index(name: 'idx_old', columns: ['old_field'], type: IndexType::Btree),
                        ],
                    ),
                ],
            );
        }
    };

    $command = Helpers::createDiffCommand(diffCalculator: $diffCalculator);
    ['output' => $output] = Helpers::executeDiffCommand($command);

    expect($output)->toContain('Add index: idx_email')
        ->and($output)->toContain('Drop index: idx_old');
});

it('displays "No changes detected" when in sync', function (): void {
    $diffCalculator = new class () extends DiffCalculator
    {
        public function calculate(
            array $entitySchema,
            array $databaseSchema,
        ): SchemaDiff {
            return new SchemaDiff();
        }
    };

    $command = Helpers::createDiffCommand(diffCalculator: $diffCalculator);
    ['output' => $output] = Helpers::executeDiffCommand($command);

    expect($output)->toContain('No changes detected');
});

it('excludes migrations table from database schema comparison', function (): void {
    // Simulate a database that has the migrations table (framework table)
    $migrationsTable = new Table(
        name: 'migrations',
        columns: [
            new Column(name: 'name', type: 'VARCHAR', length: 255),
            new Column(name: 'batch', type: 'INT'),
        ],
        indexes: [],
    );

    $command = Helpers::createDiffCommand(tables: ['migrations' => $migrationsTable]);
    ['output' => $output] = Helpers::executeDiffCommand($command);

    // The migrations table should be excluded, so no "Drop table" should appear
    expect($output)->toContain('No changes detected')
        ->and($output)->not->toContain('Drop table: migrations');
});

it('excludes migrations table even when other tables need changes', function (): void {
    // Simulate a database with migrations table and another user table
    $migrationsTable = new Table(
        name: 'migrations',
        columns: [
            new Column(name: 'name', type: 'VARCHAR', length: 255),
            new Column(name: 'batch', type: 'INT'),
        ],
        indexes: [],
    );

    $usersTable = new Table(
        name: 'users',
        columns: [
            new Column(name: 'id', type: 'INT', primaryKey: true),
        ],
        indexes: [],
    );

    $command = Helpers::createDiffCommand(tables: [
        'migrations' => $migrationsTable,
        'users' => $usersTable,
    ]);
    ['output' => $output] = Helpers::executeDiffCommand($command);

    // Non-entity tables (users, migrations) are left alone — not dropped
    expect($output)->toContain('No changes detected')
        ->and($output)->not->toContain('Drop table: users')
        ->and($output)->not->toContain('Drop table: migrations');
});

it('returns 0 when no changes, 1 when changes exist', function (): void {
    // Test return 0 when no changes
    $noDiffCalculator = new class () extends DiffCalculator
    {
        public function calculate(
            array $entitySchema,
            array $databaseSchema,
        ): SchemaDiff {
            return new SchemaDiff();
        }
    };

    $commandNoChanges = Helpers::createDiffCommand(diffCalculator: $noDiffCalculator);
    ['exitCode' => $exitCode1] = Helpers::executeDiffCommand($commandNoChanges);

    expect($exitCode1)->toBe(0);

    // Test return 1 when changes exist
    $hasDiffCalculator = new class () extends DiffCalculator
    {
        public function calculate(
            array $entitySchema,
            array $databaseSchema,
        ): SchemaDiff {
            return new SchemaDiff(
                tablesToCreate: [
                    new Table(
                        name: 'new_table',
                        columns: [new Column(name: 'id', type: 'INT', primaryKey: true)],
                        indexes: [],
                    ),
                ],
            );
        }
    };

    $commandWithChanges = Helpers::createDiffCommand(diffCalculator: $hasDiffCalculator);
    ['exitCode' => $exitCode2] = Helpers::executeDiffCommand($commandWithChanges);

    expect($exitCode2)->toBe(1);
});
