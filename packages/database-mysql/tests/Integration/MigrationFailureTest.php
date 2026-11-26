<?php

declare(strict_types=1);

namespace Marko\Database\MySql\Tests\Integration;

use Marko\Core\Path\ProjectPaths;
use Marko\Database\Exceptions\MigrationException;
use Marko\Database\Migration\MigrationRepository;
use Marko\Database\Migration\Migrator;
use Marko\Database\MySql\Connection\MySqlConnection;
use Marko\Database\MySql\Tests\Fixtures\IntegrationDatabase;

/*
 * A failed migration on a real MySQL or MariaDB server. Set
 * MARKO_TEST_MYSQL_HOST (and optionally MARKO_TEST_MYSQL_PORT, _DATABASE,
 * _USERNAME, _PASSWORD) to enable; the tests skip otherwise. The tests create
 * and drop the mfail_items table and the migrations table (its name is fixed,
 * so the file drops it before and after every case; the driver integration
 * suites run serially).
 *
 * Settings come from tests/Fixtures/IntegrationDatabase. With
 * MARKO_INTEGRATION_REQUIRED set (CI), a missing host fails instead of
 * skipping. Part of the integration-services group.
 */

pest()->group('integration-services');

beforeEach(function (): void {
    $config = IntegrationDatabase::config();

    if ($config === null) {
        $this->markTestSkipped(IntegrationDatabase::SKIP_REASON);
    }

    $this->connection = new MySqlConnection($config);
    $this->cleanUp = function (): void {
        $this->connection->execute('DROP TABLE IF EXISTS mfail_items');
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

describe('MySQL migration failures', function (): void {
    it('reports that a failed migration may be partly applied on MySQL', function (): void {
        file_put_contents(
            "$this->projectPath/database/migrations/2026_01_01_000001_create_items.php",
            <<<'PHP'
                <?php

                declare(strict_types=1);

                use Marko\Database\Connection\ConnectionInterface;
                use Marko\Database\Migration\Migration;

                return new class () extends Migration
                {
                    public function up(
                        ConnectionInterface $connection,
                    ): void {
                        $connection->execute('CREATE TABLE mfail_items (id INT PRIMARY KEY)');
                        $connection->execute('THIS IS NOT VALID SQL');
                    }

                    public function down(
                        ConnectionInterface $connection,
                    ): void {
                        $connection->execute('DROP TABLE IF EXISTS mfail_items');
                    }
                };
                PHP,
        );

        $error = null;

        try {
            $this->migrator->migrate();
        } catch (MigrationException $e) {
            $error = $e;
        }

        $tableExists = $this->connection->query(
            'SELECT COUNT(*) AS found FROM information_schema.tables '
            . "WHERE table_schema = DATABASE() AND table_name = 'mfail_items'",
        )[0]['found'];

        expect($error)->toBeInstanceOf(MigrationException::class)
            ->and($error->getMessage())->toContain('without a transaction')->toContain('stay applied')
            ->and((int) $tableExists)->toBe(1)
            ->and($this->connection->query('SELECT name FROM migrations'))->toBeEmpty();
    });
});
