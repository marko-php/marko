<?php

declare(strict_types=1);

namespace Marko\Authentication\Database\Repository;

use DateMalformedStringException;
use DateTimeImmutable;
use Marko\Authentication\Config\AuthConfig;
use Marko\Authentication\Contracts\PasswordResetTokenRepositoryInterface;
use Marko\Authentication\PasswordReset\PasswordResetTokenRecord;
use Marko\Database\Config\DatabaseTimezoneConfig;
use Marko\Database\Connection\ConnectionInterface;
use Marko\Database\Query\QueryBuilderFactoryInterface;

/**
 * Keeps password reset token hashes in the password_reset_tokens table, one row per user of the reset provider.
 *
 * Rows are keyed by authentication.password_reset.provider and the user's auth identifier, so a token issued for
 * user 7 of one provider never matches user 7 of another. Unlike the cache default, rows outlive cache flushes,
 * and expired ones stay until deleteExpired() (run by `marko auth:clear-reset-tokens`) removes them.
 */
class DatabasePasswordResetTokenRepository implements PasswordResetTokenRepositoryInterface
{
    private const string TABLE = 'password_reset_tokens';

    public function __construct(
        private ConnectionInterface $connection,
        private QueryBuilderFactoryInterface $queryBuilderFactory,
        private AuthConfig $authConfig,
        private DatabaseTimezoneConfig $databaseTimezoneConfig,
    ) {}

    /**
     * An upsert on (provider, user_id), so two reset requests racing for one user leave a single row.
     */
    public function store(
        int|string $userId,
        string $tokenHash,
        DateTimeImmutable $createdAt,
    ): void {
        $this->queryBuilderFactory->create()
            ->table(self::TABLE)
            ->upsert(
                [[
                    'provider' => $this->authConfig->passwordResetProvider(),
                    'user_id' => (string) $userId,
                    'token_hash' => $tokenHash,
                    'created_at' => $this->databaseTimezoneConfig->format($createdAt),
                ]],
                ['provider', 'user_id'],
                ['token_hash', 'created_at'],
            );
    }

    /**
     * @throws DateMalformedStringException When the stored created_at is not a datetime
     */
    public function find(
        int|string $userId,
    ): ?PasswordResetTokenRecord {
        $rows = $this->connection->query(
            sprintf(
                'SELECT %s, %s FROM %s WHERE %s = ? AND %s = ?',
                $this->column('token_hash'),
                $this->column('created_at'),
                $this->table(),
                $this->column('provider'),
                $this->column('user_id'),
            ),
            [$this->authConfig->passwordResetProvider(), (string) $userId],
        );

        if ($rows === []) {
            return null;
        }

        return new PasswordResetTokenRecord(
            tokenHash: (string) $rows[0]['token_hash'],
            createdAt: $this->databaseTimezoneConfig->parse((string) $rows[0]['created_at']),
        );
    }

    public function delete(
        int|string $userId,
    ): void {
        $this->connection->execute(
            sprintf(
                'DELETE FROM %s WHERE %s = ? AND %s = ?',
                $this->table(),
                $this->column('provider'),
                $this->column('user_id'),
            ),
            [$this->authConfig->passwordResetProvider(), (string) $userId],
        );
    }

    /**
     * Deletes expired tokens of every provider, so tokens left behind by a provider the app no longer resets
     * passwords for are cleared too.
     */
    public function deleteExpired(
        DateTimeImmutable $before,
    ): int {
        return $this->connection->execute(
            sprintf('DELETE FROM %s WHERE %s < ?', $this->table(), $this->column('created_at')),
            [$this->databaseTimezoneConfig->format($before)],
        );
    }

    private function table(): string
    {
        return $this->connection->quoteIdentifier(self::TABLE);
    }

    private function column(
        string $name,
    ): string {
        return $this->connection->quoteIdentifier($name);
    }
}
