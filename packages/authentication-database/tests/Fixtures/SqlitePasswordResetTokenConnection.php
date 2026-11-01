<?php

declare(strict_types=1);

namespace Marko\Authentication\Database\Tests\Fixtures;

use Marko\Database\Connection\ConnectionInterface;
use Marko\Database\Connection\StatementInterface;
use PDO;
use RuntimeException;

/**
 * An in-memory SQLite connection with the password_reset_tokens table, so the repository tests run their real
 * SQL against real rows. The table mirrors the PasswordResetToken entity, including the unique index the upsert
 * conflicts on.
 */
class SqlitePasswordResetTokenConnection implements ConnectionInterface
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = new PDO('sqlite::memory:', options: [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $this->pdo->exec(
            'CREATE TABLE password_reset_tokens (id INTEGER PRIMARY KEY AUTOINCREMENT, provider TEXT NOT NULL, '
            . 'user_id TEXT NOT NULL, token_hash TEXT NOT NULL, created_at TEXT NOT NULL)',
        );
        $this->pdo->exec(
            'CREATE UNIQUE INDEX idx_password_reset_tokens_provider_user_id '
            . 'ON password_reset_tokens (provider, user_id)',
        );
    }

    /**
     * @return list<array<string, mixed>> Every row, ordered by provider and user id
     */
    public function rows(): array
    {
        return $this->pdo->query(
            'SELECT provider, user_id, token_hash, created_at FROM password_reset_tokens ORDER BY provider, user_id',
        )->fetchAll();
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
