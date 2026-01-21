<?php

declare(strict_types=1);

namespace Marko\Database\Config;

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

    /**
     * @throws ConfigurationException
     */
    public function __construct(
        string $basePath,
    ) {
        $configPath = $basePath . '/config/database.php';

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
    }
}
