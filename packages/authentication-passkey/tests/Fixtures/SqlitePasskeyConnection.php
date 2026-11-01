<?php

declare(strict_types=1);

namespace Marko\AuthenticationPasskey\Tests\Fixtures;

use Marko\Database\Connection\ConnectionInterface;
use Marko\Database\Connection\StatementInterface;
use PDO;
use RuntimeException;

/**
 * An in-memory SQLite connection with the passkey_credentials table, so the
 * repository runs its real SQL. The credential id is unique, as on MySQL and
 * PostgreSQL.
 */
class SqlitePasskeyConnection implements ConnectionInterface
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = new PDO('sqlite::memory:', options: [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $this->pdo->exec(
            'CREATE TABLE passkey_credentials (id INTEGER PRIMARY KEY AUTOINCREMENT, '
            . 'credential_id TEXT NOT NULL UNIQUE, provider TEXT NOT NULL, user_id TEXT NOT NULL, '
            . 'name TEXT NOT NULL, public_key TEXT NOT NULL, sign_count INTEGER NOT NULL, '
            . 'transports TEXT NOT NULL, created_at TEXT NOT NULL, last_used_at TEXT NULL)',
        );
    }

    /**
     * @return list<array<string, mixed>> Every stored row, ordered by id
     */
    public function rows(): array
    {
        return $this->pdo->query('SELECT * FROM passkey_credentials ORDER BY id')->fetchAll();
    }

    public function connect(): void {}

    public function disconnect(): void {}

    public function isConnected(): bool
    {
        return true;
    }

    public function query(
        string $sql,
        array $bindings = [],
    ): array {
        $statement = $this->pdo->prepare($sql);
        $statement->execute($bindings);

        return $statement->fetchAll();
    }

    public function execute(
        string $sql,
        array $bindings = [],
    ): int {
        $statement = $this->pdo->prepare($sql);
        $statement->execute($bindings);

        return $statement->rowCount();
    }

    public function prepare(
        string $sql,
    ): StatementInterface {
        throw new RuntimeException('Not implemented');
    }

    public function lastInsertId(): int
    {
        return (int) $this->pdo->lastInsertId();
    }

    public function driverName(): string
    {
        return 'sqlite';
    }

    public function supportsReturning(): bool
    {
        return false;
    }

    public function quoteIdentifier(
        string $identifier,
    ): string {
        return '"' . str_replace('"', '""', $identifier) . '"';
    }
}
