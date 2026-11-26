<?php

declare(strict_types=1);

namespace Marko\Database\PgSql\Tests\Integration;

use Marko\Core\Path\ProjectPaths;
use Marko\Database\Exceptions\MigrationException;
use Marko\Database\Exceptions\QueryException;
use Marko\Database\Migration\MigrationRepository;
use Marko\Database\Migration\Migrator;
use Marko\Database\PgSql\Connection\PgSqlConnection;
use Marko\Database\PgSql\Tests\Fixtures\IntegrationDatabase;
use Throwable;

/*
 * Migrations run in a transaction against a real PostgreSQL server. Set
 * MARKO_TEST_PGSQL_HOST (and optionally MARKO_TEST_PGSQL_PORT, _DATABASE,
 * _USERNAME, _PASSWORD) to enable; the tests skip otherwise. The tests create
 * and drop the mtx_items table, the mtx_items_label_idx index and the
 * migrations table (its name is fixed, so the file drops it before and after
 * every case; the driver integration suites run serially).
 *
 * Settings come from tests/Fixtures/IntegrationDatabase. With
 * MARKO_INTEGRATION_REQUIRED set (CI), a missing host fails instead of
 * skipping. Part of the integration-services group.
 */

/**
 * The source of a migration file whose up() and down() run the given statements in order.
 *
 * @param array<string> $up
 * @param array<string> $down
 */
function mtxMigrationSource(
    array $up,
    array $down,
    bool $withoutTransaction = false,
): string {
    $attribute = $withoutTransaction ? '#[WithoutTransaction] ' : '';
    $upBody = mtxStatements($up);
    $downBody = mtxStatements($down);

    return <<<PHP
        <?php

        declare(strict_types=1);

        use Marko\\Database\\Attributes\\WithoutTransaction;
        use Marko\\Database\\Connection\\ConnectionInterface;
        use Marko\\Database\\Migration\\Migration;

        return new $attribute class () extends Migration
        {
            public function up(
                ConnectionInterface \$connection,
            ): void {
        $upBody
            }

            public function down(
                ConnectionInterface \$connection,
            ): void {
        $downBody
            }
        };
        PHP;
}

/**
 * @param array<string> $statements
 */
function mtxStatements(
    array $statements,
): string {
    return implode(
        "\n",
        array_map(
            fn (string $sql): string => '        $connection->execute(' . var_export($sql, true) . ');',
            $statements,
        ),
    );
}

function mtxWriteMigration(
    string $projectPath,
    string $name,
    string $source,
): void {
    file_put_contents("$projectPath/database/migrations/$name.php", $source);
}

function mtxTableExists(
    PgSqlConnection $connection,
): bool {
    return $connection->query("SELECT to_regclass('public.mtx_items') AS found")[0]['found'] !== null;
}

function mtxIndexExists(
    PgSqlConnection $connection,
): bool {
    return $connection->query("SELECT to_regclass('public.mtx_items_label_idx') AS found")[0]['found'] !== null;
}

/**
 * @return array<string>
 */
function mtxRecorded(
    PgSqlConnection $connection,
): array {
    return array_column($connection->query('SELECT name FROM migrations ORDER BY name'), 'name');
}

function mtxPreviousQueryException(
    Throwable $error,
): ?QueryException {
    for ($current = $error; $current !== null; $current = $current->getPrevious()) {
        if ($current instanceof QueryException) {
            return $current;
        }
    }

    return null;
}

pest()->group('integration-services');

beforeEach(function (): void {
    $config = IntegrationDatabase::config();

    if ($config === null) {
        $this->markTestSkipped(IntegrationDatabase::SKIP_REASON);
    }

    $this->connection = new PgSqlConnection($config);
    $this->cleanUp = function (): void {
        $this->connection->execute('DROP TABLE IF EXISTS mtx_items');
        $this->connection->execute('DROP TABLE IF EXISTS migrations');
    };
    ($this->cleanUp)();

    $this->projectPath = sys_get_temp_dir() . '/marko-migrations-' . bin2hex(random_bytes(8));
    mkdir("$this->projectPath/database/migrations", recursive: true);
    $this->migrator = new Migrator(
        $this->connection,
        new MigrationRepository(),
        new ProjectPaths($this->projectPath),
    );
});

afterEach(function (): void {
    if (isset($this->cleanUp)) {
        ($this->cleanUp)();
    }

    if (isset($this->projectPath)) {
        array_map(unlink(...), glob("$this->projectPath/database/migrations/*.php") ?: []);
        rmdir("$this->projectPath/database/migrations");
        rmdir("$this->projectPath/database");
        rmdir($this->projectPath);
    }
});

describe('PostgreSQL migrations in a transaction', function (): void {
    it('leaves no table and no migration record when a migration fails halfway on PostgreSQL', function (): void {
        mtxWriteMigration($this->projectPath, '2026_01_01_000001_create_items', mtxMigrationSource(
            up: [
                'CREATE TABLE mtx_items (id INT PRIMARY KEY)',
                'THIS IS NOT VALID SQL',
            ],
            down: ['DROP TABLE IF EXISTS mtx_items'],
        ));

        $error = null;

        try {
            $this->migrator->migrate();
        } catch (MigrationException $e) {
            $error = $e;
        }

        expect($error)->toBeInstanceOf(MigrationException::class)
            ->and($error->getMessage())->toContain('rolled back')
            ->and(mtxTableExists($this->connection))->toBeFalse()
            ->and(mtxRecorded($this->connection))->toBeEmpty();
    });

    it('undoes a failed rollback and keeps the migration recorded on PostgreSQL', function (): void {
        mtxWriteMigration($this->projectPath, '2026_01_01_000001_create_items', mtxMigrationSource(
            up: ['CREATE TABLE mtx_items (id INT PRIMARY KEY)'],
            down: [
                'DROP TABLE mtx_items',
                'THIS IS NOT VALID SQL',
            ],
        ));
        $this->migrator->migrate();

        $error = null;

        try {
            $this->migrator->rollback();
        } catch (MigrationException $e) {
            $error = $e;
        }

        expect($error)->toBeInstanceOf(MigrationException::class)
            ->and($error->getMessage())->toContain('rolled back')
            ->and(mtxTableExists($this->connection))->toBeTrue()
            ->and(mtxRecorded($this->connection))->toBe(['2026_01_01_000001_create_items']);
    });

    it('runs CREATE INDEX CONCURRENTLY in a migration marked WithoutTransaction', function (): void {
        $this->connection->execute('CREATE TABLE mtx_items (id INT PRIMARY KEY, label VARCHAR(20))');
        mtxWriteMigration($this->projectPath, '2026_01_01_000001_index_items', mtxMigrationSource(
            up: ['CREATE INDEX CONCURRENTLY mtx_items_label_idx ON mtx_items (label)'],
            down: ['DROP INDEX IF EXISTS mtx_items_label_idx'],
            withoutTransaction: true,
        ));

        $applied = $this->migrator->migrate();

        expect($applied)->toBe(['2026_01_01_000001_index_items'])
            ->and(mtxIndexExists($this->connection))->toBeTrue()
            ->and(mtxRecorded($this->connection))->toBe(['2026_01_01_000001_index_items']);
    });

    it('suggests WithoutTransaction when CREATE INDEX CONCURRENTLY runs inside a transaction', function (): void {
        $this->connection->execute('CREATE TABLE mtx_items (id INT PRIMARY KEY, label VARCHAR(20))');
        mtxWriteMigration($this->projectPath, '2026_01_01_000001_index_items', mtxMigrationSource(
            up: ['CREATE INDEX CONCURRENTLY mtx_items_label_idx ON mtx_items (label)'],
            down: ['DROP INDEX IF EXISTS mtx_items_label_idx'],
        ));

        $error = null;

        try {
            $this->migrator->migrate();
        } catch (MigrationException $e) {
            $error = $e;
        }

        expect($error)->toBeInstanceOf(MigrationException::class)
            ->and($error->getMessage())->toContain('WithoutTransaction')
            ->and(mtxPreviousQueryException($error)?->sqlState())->toBe('25001')
            ->and(mtxIndexExists($this->connection))->toBeFalse()
            ->and(mtxRecorded($this->connection))->toBeEmpty();
    });
});
