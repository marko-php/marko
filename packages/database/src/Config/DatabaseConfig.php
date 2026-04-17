<?php

declare(strict_types=1);

namespace Marko\Database\Config;

use Marko\Core\Path\ProjectPaths;
use Marko\Database\Exceptions\ConfigurationException;

/**
 * Database configuration loaded from config/database.php.
 */
readonly class DatabaseConfig
{
    public string $driver;

    public string $host;

    public int $port;

    public string $database;

    public string $username;

    public string $password;

    public ?string $sslMode;

    public ?string $sslRootCert;

    public bool $sslVerifyServerCert;

    public ?string $sslCert;

    public ?string $sslKey;

    /**
     * @throws ConfigurationException
     */
    public function __construct(
        ProjectPaths $paths,
    ) {
        $configPath = $paths->config . '/database.php';

        if (!file_exists($configPath)) {
            throw ConfigurationException::configFileNotFound($configPath);
        }

        $config = require $configPath;

        $defaultConnection = $config['default'] ?? null;
        if ($defaultConnection === null || !isset($config['connections'][$defaultConnection])) {
            throw ConfigurationException::missingRequiredKey('default');
        }

        $connectionConfig = $config['connections'][$defaultConnection];

        $requiredKeys = ['driver', 'host', 'port', 'database', 'username', 'password'];

        foreach ($requiredKeys as $key) {
            if (!array_key_exists($key, $connectionConfig)) {
                throw ConfigurationException::missingRequiredKey($key);
            }
        }

        $this->driver = $connectionConfig['driver'];
        $this->host = $connectionConfig['host'];
        $this->port = $connectionConfig['port'];
        $this->database = $connectionConfig['database'];
        $this->username = $connectionConfig['username'];
        $this->password = $connectionConfig['password'];
        $this->sslMode = $connectionConfig['sslmode'] ?? null;
        $this->sslRootCert = $connectionConfig['ssl_ca'] ?? null;
        $this->sslVerifyServerCert = $connectionConfig['ssl_verify_server_cert'] ?? ($this->sslRootCert !== null);
        $this->sslCert = $connectionConfig['ssl_cert'] ?? null;
        $this->sslKey = $connectionConfig['ssl_key'] ?? null;

        if ($this->sslCert !== null && $this->sslKey === null) {
            throw ConfigurationException::incompleteSslKeyPair('ssl_cert', 'ssl_key');
        }

        if ($this->sslKey !== null && $this->sslCert === null) {
            throw ConfigurationException::incompleteSslKeyPair('ssl_key', 'ssl_cert');
        }
    }
}
