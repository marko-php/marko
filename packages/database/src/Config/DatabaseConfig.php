<?php

declare(strict_types=1);

namespace Marko\Database\Config;

use Marko\Core\Path\ProjectPaths;
use Marko\Database\Exceptions\ConfigurationException;
use ReflectionClass;

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

        self::validateConfigArray($config);

        $this->driver = $config['driver'];
        $this->host = $config['host'];
        $this->port = $config['port'];
        $this->database = $config['database'];
        $this->username = $config['username'];
        $this->password = $config['password'];
        $this->sslMode = $config['sslmode'] ?? null;
        $this->sslRootCert = $config['ssl_ca'] ?? null;
        $this->sslVerifyServerCert = $config['ssl_verify_server_cert'] ?? ($config['ssl_ca'] ?? null) !== null;
        $this->sslCert = $config['ssl_cert'] ?? null;
        $this->sslKey = $config['ssl_key'] ?? null;
    }

    /**
     * Create a DatabaseConfig from a raw configuration array.
     *
     * @param array<string, mixed> $config
     *
     * @throws ConfigurationException
     */
    public static function fromArray(array $config): self
    {
        self::validateConfigArray($config);

        $instance = (new ReflectionClass(self::class))->newInstanceWithoutConstructor();

        $props = [
            'driver' => $config['driver'],
            'host' => $config['host'],
            'port' => $config['port'],
            'database' => $config['database'],
            'username' => $config['username'],
            'password' => $config['password'],
            'sslMode' => $config['sslmode'] ?? null,
            'sslRootCert' => $config['ssl_ca'] ?? null,
            'sslVerifyServerCert' => $config['ssl_verify_server_cert'] ?? ($config['ssl_ca'] ?? null) !== null,
            'sslCert' => $config['ssl_cert'] ?? null,
            'sslKey' => $config['ssl_key'] ?? null,
        ];

        $reflection = new ReflectionClass($instance);

        foreach ($props as $name => $value) {
            $prop = $reflection->getProperty($name);
            $prop->setValue($instance, $value);
        }

        return $instance;
    }

    /**
     * Validate a database configuration array, throwing ConfigurationException on failure.
     *
     * @param array<string, mixed> $config
     *
     * @throws ConfigurationException
     */
    private static function validateConfigArray(array $config): void
    {
        $requiredKeys = ['driver', 'host', 'port', 'database', 'username', 'password'];

        foreach ($requiredKeys as $key) {
            if (!array_key_exists($key, $config)) {
                throw ConfigurationException::missingRequiredKey($key);
            }
        }

        $sslCert = $config['ssl_cert'] ?? null;
        $sslKey = $config['ssl_key'] ?? null;

        if ($sslCert !== null && $sslKey === null) {
            throw ConfigurationException::incompleteSslKeyPair('ssl_cert', 'ssl_key');
        }

        if ($sslKey !== null && $sslCert === null) {
            throw ConfigurationException::incompleteSslKeyPair('ssl_key', 'ssl_cert');
        }
    }
}
