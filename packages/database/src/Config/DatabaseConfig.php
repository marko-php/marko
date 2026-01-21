<?php

declare(strict_types=1);

namespace Marko\Database\Config;

use Marko\Database\Exceptions\ConfigurationException;

/**
 * Database configuration loaded from config/database.php.
 */
class DatabaseConfig
{
    public readonly string $driver;

    public readonly string $host;

    public readonly int $port;

    public readonly string $database;

    public readonly string $username;

    public readonly string $password;

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
