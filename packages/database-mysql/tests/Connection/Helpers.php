<?php

declare(strict_types=1);

namespace Marko\Database\MySql\Tests\Connection;

use Marko\Core\Path\ProjectPaths;
use Marko\Database\Config\DatabaseConfig;
use Marko\Database\MySql\Connection\MySqlConnection;
use PDO;

function createTestDatabaseConfig(
    string $host = 'localhost',
    int $port = 3306,
    string $database = 'test',
    string $username = 'root',
    string $password = '',
    ?string $sslCa = null,
    bool $sslVerifyServerCert = false,
    ?string $sslCert = null,
    ?string $sslKey = null,
    ?string $timezone = null,
): DatabaseConfig {
    $tempDir = sys_get_temp_dir() . '/marko_mysql_test_' . bin2hex(random_bytes(8));
    mkdir($tempDir . '/config', recursive: true);

    $configArray = [
        'driver' => 'mysql',
        'host' => $host,
        'port' => $port,
        'database' => $database,
        'username' => $username,
        'password' => $password,
    ];

    if ($sslCa !== null) {
        $configArray['ssl_ca'] = $sslCa;
    }

    if ($sslVerifyServerCert) {
        $configArray['ssl_verify_server_cert'] = true;
    }

    if ($sslCert !== null) {
        $configArray['ssl_cert'] = $sslCert;
    }

    if ($sslKey !== null) {
        $configArray['ssl_key'] = $sslKey;
    }

    if ($timezone !== null) {
        $configArray['timezone'] = $timezone;
    }

    file_put_contents(
        $tempDir . '/config/database.php',
        '<?php return ' . var_export($configArray, true) . ';',
    );

    $paths = new ProjectPaths($tempDir);
    $config = new DatabaseConfig($paths);

    unlink($tempDir . '/config/database.php');
    rmdir($tempDir . '/config');
    rmdir($tempDir);

    return $config;
}

/**
 * Connect a MySqlConnection and return the PDO options it passed.
 *
 * @return array<int, mixed>
 */
function connectAndCapturePdoOptions(
    DatabaseConfig $config,
): array {
    $capturedOptions = [];

    $connection = new class ($config, $capturedOptions) extends MySqlConnection
    {
        public function __construct(
            DatabaseConfig $config,
            private array &$capturedOptions,
        ) {
            parent::__construct($config);
        }

        protected function createPdo(
            string $dsn,
            string $username,
            string $password,
            array $options,
        ): PDO {
            $this->capturedOptions = $options;

            return new PDO('sqlite::memory:');
        }
    };

    $connection->connect();

    return $capturedOptions;
}

/**
 * A MySqlConnection whose server answers SELECT VERSION() with $serverVersion, through an in-memory SQLite
 * handle. $versionQueries counts the VERSION() calls.
 */
function connectionReportingVersion(
    string $serverVersion,
    int &$versionQueries = 0,
): MySqlConnection {
    return new class (createTestDatabaseConfig(), $serverVersion, $versionQueries) extends MySqlConnection
    {
        public function __construct(
            DatabaseConfig $config,
            private readonly string $serverVersion,
            /** @noinspection PhpPropertyOnlyWrittenInspection - Reference property modifies external variable */
            private int &$versionQueries,
        ) {
            parent::__construct($config);
        }

        protected function createPdo(
            string $dsn,
            string $username,
            string $password,
            array $options,
        ): PDO {
            $pdo = new PDO\Sqlite('sqlite::memory:');
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo->createFunction('VERSION', function (): string {
                $this->versionQueries++;

                return $this->serverVersion;
            }, 0);

            return $pdo;
        }
    };
}
