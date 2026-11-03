<?php

declare(strict_types=1);

namespace Marko\Database\Exceptions;

use Marko\Core\Exceptions\MarkoException;

/**
 * Exception thrown when database configuration is invalid.
 */
class ConfigurationException extends MarkoException
{
    public static function configFileNotFound(
        string $path,
    ): self {
        return new self(
            message: "Database configuration file not found at: $path",
            context: 'While loading database configuration',
            suggestion: 'Create a config/database.php file with your database settings',
        );
    }

    public static function missingRequiredKey(
        string $key,
    ): self {
        return new self(
            message: "Required database configuration key '$key' is missing",
            context: 'While validating database configuration',
            suggestion: "Add the '$key' key to your config/database.php file",
        );
    }

    public static function invalidIgnoreIndexes(): self
    {
        return new self(
            message: "Database configuration key 'migrations.ignore_indexes' must be a list of strings",
            context: 'While validating database migration configuration',
            suggestion: "Set 'migrations' => ['ignore_indexes' => ['index_name', 'prefix_*']] in config/database.php",
        );
    }

    public static function incompleteSslKeyPair(
        string $present,
        string $missing,
    ): self {
        return new self(
            message: "SSL configuration key '$present' is set but '$missing' is missing",
            context: 'While validating database SSL configuration',
            suggestion: "When using client certificate authentication, both 'ssl_cert' and 'ssl_key' must be provided together",
        );
    }

    public static function unverifiedSslClientCertificate(): self
    {
        return new self(
            message: "SSL client certificate 'ssl_cert' is set but 'ssl_ca' is missing, so the MySQL server certificate would not be verified",
            context: 'While validating database SSL configuration',
            suggestion: "Set 'ssl_ca' to the CA certificate that signed the server certificate. If you deliberately accept an unverified server, set 'ssl_verify_server_cert' => false",
        );
    }

    public static function invalidTimezone(
        string $timezone,
    ): self {
        return new self(
            message: "Invalid database timezone: $timezone",
            context: 'While reading the timezone key of config/database.php',
            suggestion: "Set 'timezone' to a valid PHP timezone identifier such as 'UTC' or 'America/New_York'",
        );
    }

    public static function invalidSqliteOption(
        string $key,
        string $expected,
    ): self {
        return new self(
            message: "Database configuration key '$key' must be $expected",
            context: 'While validating SQLite database configuration',
            suggestion: "Set 'sqlite' => ['pragmas' => ['busy_timeout' => 5000], 'create_if_missing' => true] in config/database.php",
        );
    }

    public static function relativeSqlitePathWithoutBasePath(
        string $path,
    ): self {
        return new self(
            message: "The relative SQLite database path '$path' cannot be resolved without a base path",
            context: 'While resolving the SQLite database path',
            suggestion: 'Pass a base path as the second argument of DatabaseConfig::fromArray(), or use an absolute path, :memory: or a file: URI',
        );
    }
}
