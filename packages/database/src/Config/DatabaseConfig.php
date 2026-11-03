<?php

declare(strict_types=1);

namespace Marko\Database\Config;

use DateTimeImmutable;
use DateTimeZone;
use Marko\Core\Path\ProjectPaths;
use Marko\Database\Exceptions\ConfigurationException;
use ReflectionClass;
use ReflectionException;

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
     * The zone stored datetimes are written in (the `timezone` key, UTC when absent). The connections pin the
     * database session to it on connect. The same key DatabaseTimezoneConfig reads.
     */
    public DateTimeZone $timezone;

    /**
     * Index names or fnmatch patterns that db:migrate never drops (migrations.ignore_indexes).
     *
     * @var list<string>
     */
    public array $ignoreIndexes;

    /**
     * The raw `sqlite.pragmas` overrides (empty when absent and for other drivers). The sqlite driver
     * validates the pragma names and values.
     *
     * @var array<string, int|string>
     */
    public array $sqlitePragmas;

    /**
     * The `sqlite.create_if_missing` option: whether a missing database file is created (default true).
     */
    public bool $sqliteCreateIfMissing;

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

        $props = self::properties($config, $paths->base);

        $this->driver = $props['driver'];
        $this->host = $props['host'];
        $this->port = $props['port'];
        $this->database = $props['database'];
        $this->username = $props['username'];
        $this->password = $props['password'];
        $this->sslMode = $props['sslMode'];
        $this->sslRootCert = $props['sslRootCert'];
        $this->sslVerifyServerCert = $props['sslVerifyServerCert'];
        $this->sslCert = $props['sslCert'];
        $this->sslKey = $props['sslKey'];
        $this->timezone = $props['timezone'];
        $this->ignoreIndexes = $props['ignoreIndexes'];
        $this->sqlitePragmas = $props['sqlitePragmas'];
        $this->sqliteCreateIfMissing = $props['sqliteCreateIfMissing'];
    }

    /**
     * Create a DatabaseConfig from a raw configuration array.
     *
     * @param array<string, mixed> $config
     * @param string|null $basePath Resolves a relative sqlite database path
     *
     * @throws ConfigurationException|ReflectionException
     */
    public static function fromArray(
        array $config,
        ?string $basePath = null,
    ): self {
        $props = self::properties($config, $basePath);

        $instance = (new ReflectionClass(self::class))->newInstanceWithoutConstructor();

        $reflection = new ReflectionClass($instance);

        foreach ($props as $name => $value) {
            $prop = $reflection->getProperty($name);
            $prop->setValue($instance, $value);
        }

        return $instance;
    }

    /**
     * The timezone as a fixed UTC offset such as `+00:00` or `+05:30`, or null for a region zone such as
     * `America/New_York`, whose offset changes with daylight saving time and which the database has to
     * resolve by name. UTC, numeric offsets and abbreviations (`CEST`) have a fixed offset: PHP formats
     * them with that one offset, so the database session is given the same offset.
     */
    public function fixedTimezoneOffset(): ?string
    {
        if ($this->timezone->getName() !== 'UTC' && $this->timezone->getLocation() !== false) {
            return null;
        }

        return new DateTimeImmutable('now', $this->timezone)->format('P');
    }

    /**
     * Validate the raw config and turn it into the property values.
     *
     * @param array<string, mixed> $config
     * @return array<string, mixed>
     *
     * @throws ConfigurationException
     */
    private static function properties(
        array $config,
        ?string $basePath,
    ): array {
        self::validateConfigArray($config);

        $isSqlite = $config['driver'] === 'sqlite';
        $sqlite = $config['sqlite'] ?? [];

        return [
            'driver' => $config['driver'],
            'host' => $isSqlite ? '' : $config['host'],
            'port' => $isSqlite ? 0 : $config['port'],
            'database' => $isSqlite ? self::resolveSqlitePath($config['database'], $basePath) : $config['database'],
            'username' => $isSqlite ? '' : $config['username'],
            'password' => $isSqlite ? '' : $config['password'],
            'sslMode' => $config['sslmode'] ?? null,
            'sslRootCert' => $config['ssl_ca'] ?? null,
            'sslVerifyServerCert' => $config['ssl_verify_server_cert'] ?? ($config['ssl_ca'] ?? null) !== null,
            'sslCert' => $config['ssl_cert'] ?? null,
            'sslKey' => $config['ssl_key'] ?? null,
            'timezone' => DatabaseTimezoneConfig::resolveTimezone(
                $config['timezone'] ?? DatabaseTimezoneConfig::DEFAULT_TIMEZONE,
            ),
            'ignoreIndexes' => array_values($config['migrations']['ignore_indexes'] ?? []),
            'sqlitePragmas' => $isSqlite ? $sqlite['pragmas'] ?? [] : [],
            'sqliteCreateIfMissing' => $isSqlite ? $sqlite['create_if_missing'] ?? true : true,
        ];
    }

    /**
     * @throws ConfigurationException
     */
    private static function resolveSqlitePath(
        string $database,
        ?string $basePath,
    ): string {
        if ($database === ':memory:' || str_starts_with($database, 'file:') || str_starts_with($database, '/')) {
            return $database;
        }

        if ($basePath === null) {
            throw ConfigurationException::relativeSqlitePathWithoutBasePath($database);
        }

        return $basePath . '/' . $database;
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
        if (!array_key_exists('driver', $config)) {
            throw ConfigurationException::missingRequiredKey('driver');
        }

        $isSqlite = $config['driver'] === 'sqlite';
        $requiredKeys = $isSqlite
            ? ['database']
            : ['host', 'port', 'database', 'username', 'password'];

        foreach ($requiredKeys as $key) {
            if (!array_key_exists($key, $config)) {
                throw ConfigurationException::missingRequiredKey($key);
            }
        }

        if ($isSqlite) {
            self::validateSqliteOptions($config['sqlite'] ?? []);
        }

        $sslCert = $config['ssl_cert'] ?? null;
        $sslKey = $config['ssl_key'] ?? null;

        if ($sslCert !== null && $sslKey === null) {
            throw ConfigurationException::incompleteSslKeyPair('ssl_cert', 'ssl_key');
        }

        if ($sslKey !== null && $sslCert === null) {
            throw ConfigurationException::incompleteSslKeyPair('ssl_key', 'ssl_cert');
        }

        // MySQL verifies the server certificate only against ssl_ca, so a client certificate without a CA
        // would encrypt the connection without proving who is on the other end. PostgreSQL leaves this to
        // sslmode (verify-full reads ~/.postgresql/root.crt when no ssl_ca is given).
        $isMysql = $config['driver'] === 'mysql';
        $verifyExplicitlyDisabled = ($config['ssl_verify_server_cert'] ?? null) === false;

        if ($isMysql && $sslCert !== null && ($config['ssl_ca'] ?? null) === null && !$verifyExplicitlyDisabled) {
            throw ConfigurationException::unverifiedSslClientCertificate();
        }

        $ignoreIndexes = $config['migrations']['ignore_indexes'] ?? [];

        $isListOfStrings = is_array($ignoreIndexes)
            && array_all($ignoreIndexes, static fn (mixed $name): bool => is_string($name));

        if (!$isListOfStrings) {
            throw ConfigurationException::invalidIgnoreIndexes();
        }
    }

    /**
     * @throws ConfigurationException
     */
    private static function validateSqliteOptions(
        mixed $sqlite,
    ): void {
        if (!is_array($sqlite)) {
            throw ConfigurationException::invalidSqliteOption('sqlite', 'an array');
        }

        if (!is_array($sqlite['pragmas'] ?? [])) {
            throw ConfigurationException::invalidSqliteOption('sqlite.pragmas', 'an array');
        }

        if (!is_bool($sqlite['create_if_missing'] ?? true)) {
            throw ConfigurationException::invalidSqliteOption('sqlite.create_if_missing', 'a boolean');
        }
    }
}
