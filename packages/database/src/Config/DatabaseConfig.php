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

        $requiredKeys = ['driver', 'host', 'port', 'database', 'username', 'password'];

        foreach ($requiredKeys as $key) {
            if (!array_key_exists($key, $config)) {
                throw ConfigurationException::missingRequiredKey($key);
            }
        }

        $this->driver = $config['driver'];
        $this->host = $config['host'];
        $this->port = $config['port'];
        $this->database = $config['database'];
        $this->username = $config['username'];
        $this->password = $config['password'];
        $this->sslMode = $config['sslmode'] ?? null;
        $this->sslRootCert = $config['ssl_ca'] ?? null;
        $this->sslVerifyServerCert = $config['ssl_verify_server_cert'] ?? false;
        $this->sslCert = $config['ssl_cert'] ?? null;
        $this->sslKey = $config['ssl_key'] ?? null;
    }
}
