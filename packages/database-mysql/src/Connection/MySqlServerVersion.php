<?php

declare(strict_types=1);

namespace Marko\Database\MySql\Connection;

use Marko\Database\MySql\Exceptions\ServerVersionException;

/**
 * The server a MySQL connection talks to, parsed from the string the server
 * reports (SELECT VERSION() or PDO::ATTR_SERVER_VERSION): MySQL or MariaDB,
 * and its version number.
 */
readonly class MySqlServerVersion
{
    /**
     * MariaDB before 11.0 prefixes its version with "5.5.5-" in the protocol
     * handshake, so old MySQL clients do not mistake it for MySQL 10.
     */
    private const string MARIADB_HANDSHAKE_PREFIX = '5.5.5-';

    /**
     * @param string $reported The version string as the server reported it
     * @param string $version The version number alone, such as 8.4.3 or 10.11.8
     * @param bool $isMariaDb Whether the server is MariaDB rather than MySQL
     */
    public function __construct(
        public string $reported,
        public string $version,
        public bool $isMariaDb,
    ) {}

    /**
     * @throws ServerVersionException When $reported holds no version number
     */
    public static function fromString(
        string $reported,
    ): self {
        $isMariaDb = str_contains(strtolower($reported), 'mariadb');
        $unprefixed = ($isMariaDb && str_starts_with($reported, self::MARIADB_HANDSHAKE_PREFIX))
            ? substr($reported, strlen(self::MARIADB_HANDSHAKE_PREFIX))
            : $reported;

        if (preg_match('/^\d+(?:\.\d+)*/', $unprefixed, $matches) !== 1) {
            throw ServerVersionException::unreadable($reported);
        }

        return new self(
            reported: $reported,
            version: $matches[0],
            isMariaDb: $isMariaDb,
        );
    }

    /**
     * Whether the version number is $version or later, such as isAtLeast('10.5').
     */
    public function isAtLeast(
        string $version,
    ): bool {
        return version_compare($this->version, $version, '>=');
    }
}
