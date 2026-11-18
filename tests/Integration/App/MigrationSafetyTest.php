<?php

declare(strict_types=1);

use Marko\Database\Connection\ConnectionInterface;

/*
 * db:migrate, db:rebuild and db:seed against real Postgres in each
 * environment (#170). The fixture's Book entity declares a partial index no
 * committed migration creates, and a committed migration creates a partial
 * index on authors that config/database.php lists under
 * migrations.ignore_indexes.
 */

pest()->group('integration-services');

beforeEach(fn () => setUpIntegrationTest($this, migrate: false));

afterEach(fn () => tearDownIntegrationTest($this));

/**
 * @return list<string>
 */
function integrationMigrationFiles(
    string $project,
): array {
    return array_map('basename', glob("$project/database/migrations/*.php") ?: []);
}

/**
 * @return array<string, string> index name => definition
 */
function integrationIndexDefinitions(
    ConnectionInterface $connection,
    string $table,
): array {
    $rows = $connection->query(
        "SELECT indexname, indexdef FROM pg_indexes WHERE schemaname = 'public' AND tablename = ?",
        [$table],
    );

    return array_column($rows, 'indexdef', 'indexname');
}

it('never generates migrations when db:migrate runs in production', function (): void {
    $before = integrationMigrationFiles($this->project);

    $result = withIntegrationAppEnv('production', fn (): array => runIntegrationCommand($this->app, 'db:migrate'));

    expect($result['exitCode'])->toBe(0, $result['output'])
        ->and($result['output'])
        ->toContain('Applied 6 schema migration(s).')
        ->toContain('Warning: Entity schema differs from database.')
        ->toContain('CREATE INDEX "books_title_index" ON "books" ("title") WHERE title <> \'\'')
        ->not->toContain('Generated:')
        ->and(integrationMigrationFiles($this->project))->toBe($before);
})->issue(170);

it('reports no drift for jobs, failed_jobs and sessions created from the documented DDL', function (): void {
    $result = withIntegrationAppEnv('production', fn (): array => runIntegrationCommand($this->app, 'db:migrate'));

    expect($result['exitCode'])->toBe(0, $result['output'])
        ->and($result['output'])
        ->toContain('Warning: Entity schema differs from database.')
        ->not->toContain('"jobs"')
        ->not->toContain('"failed_jobs"')
        ->not->toContain('"sessions"');
})->issue(337);

it('keeps a hand-made partial index when db:migrate runs', function (): void {
    $result = withIntegrationAppEnv('local', fn (): array => runIntegrationCommand($this->app, 'db:migrate'));
    $connection = $this->app->container->get(ConnectionInterface::class);
    $generated = array_diff(integrationMigrationFiles($this->project), [
        '2026_01_01_000001_create_authors_table.php',
        '2026_01_01_000002_create_books_table.php',
        '2026_01_01_000003_create_jobs_table.php',
        '2026_01_01_000004_create_failed_jobs_table.php',
        '2026_01_01_000005_create_sessions_table.php',
        '2026_01_01_000006_create_authors_named_partial_index.php',
    ]);
    $generatedSource = implode("\n", array_map(
        fn (string $file): string => (string) file_get_contents("$this->project/database/migrations/$file"),
        $generated,
    ));

    expect($result['exitCode'])->toBe(0, $result['output'])
        ->and($result['output'])->not->toContain('remove existing database objects')
        ->and($generatedSource)->not->toContain('authors_named_idx')
        ->and(integrationIndexDefinitions($connection, 'authors'))->toHaveKey('authors_named_idx');
})->issue(170);

it('round-trips an entity partial index through db:migrate on postgres', function (): void {
    $first = withIntegrationAppEnv('local', fn (): array => runIntegrationCommand($this->app, 'db:migrate'));
    $filesAfterFirst = integrationMigrationFiles($this->project);
    $second = withIntegrationAppEnv('local', fn (): array => runIntegrationCommand($this->app, 'db:migrate'));
    $indexes = integrationIndexDefinitions($this->app->container->get(ConnectionInterface::class), 'books');

    expect($first['exitCode'])->toBe(0, $first['output'])
        ->and($first['output'])->toContain('Generated:')
        ->and($indexes)->toHaveKey('books_title_index')
        ->and($indexes['books_title_index'])->toContain('WHERE')
        ->and($second['exitCode'])->toBe(0, $second['output'])
        ->and($second['output'])
        ->not->toContain('Generated:')
        ->toContain('Nothing to migrate.')
        ->and(integrationMigrationFiles($this->project))->toBe($filesAfterFirst);
})->issue(170);

it('refuses db:rebuild and db:seed in production and when APP_ENV is unset', function (string $environment): void {
    $migrate = runIntegrationCommand($this->app, 'db:migrate', ['--no-generate']);

    [$rebuild, $seed] = withIntegrationAppEnv($environment, fn (): array => [
        runIntegrationCommand($this->app, 'db:rebuild'),
        runIntegrationCommand($this->app, 'db:seed'),
    ]);
    $tables = $this->app->container->get(ConnectionInterface::class)->query(
        "SELECT count(*) AS total FROM information_schema.tables WHERE table_schema = 'public'",
    );

    expect($migrate['exitCode'])->toBe(0, $migrate['output'])
        ->and($rebuild['exitCode'])->toBe(1)
        ->and($rebuild['output'])->toContain("db:rebuild cannot be run in the 'production' environment")
        ->and($seed['exitCode'])->toBe(1)
        ->and($seed['output'])->toContain("db:seed cannot be run in the 'production' environment")
        ->and((int) $tables[0]['total'])->toBe(6);
})->with(['production', ''])->issue(170);

it('refuses db:rebuild and db:seed in staging without --force', function (): void {
    $migrate = runIntegrationCommand($this->app, 'db:migrate', ['--no-generate']);

    [$rebuild, $seed] = withIntegrationAppEnv('staging', fn (): array => [
        runIntegrationCommand($this->app, 'db:rebuild'),
        runIntegrationCommand($this->app, 'db:seed'),
    ]);
    $tables = $this->app->container->get(ConnectionInterface::class)->query(
        "SELECT count(*) AS total FROM information_schema.tables WHERE table_schema = 'public'",
    );

    expect($migrate['exitCode'])->toBe(0, $migrate['output'])
        ->and($rebuild['exitCode'])->toBe(1)
        ->and($rebuild['output'])->toContain("db:rebuild is refused in the 'staging' environment without --force")
        ->and($seed['exitCode'])->toBe(1)
        ->and($seed['output'])->toContain("db:seed is refused in the 'staging' environment without --force")
        ->and((int) $tables[0]['total'])->toBe(6);
})->issue(235);
