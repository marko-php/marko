<?php

declare(strict_types=1);

namespace Marko\AdminAuth\Repository;

use Marko\AdminAuth\Entity\RememberToken;
use Marko\Authentication\Contracts\RememberTokenStorageInterface;
use Marko\Authentication\Token\RememberTokenRecord;
use Marko\Database\Exceptions\EntityException;
use Marko\Database\Exceptions\RepositoryException;
use Marko\Database\Repository\Repository;

/**
 * Per-device remember-me tokens in the remember_tokens table.
 *
 * Bound to RememberTokenStorageInterface, so every session guard (the admin guard and the app's own) issues one
 * token per device: a remember-me login on one device no longer rotates another device's token, and logging out
 * deletes only that device's row. `marko auth:clear-tokens` purges expired rows through clearExpiredTokens().
 *
 * @extends Repository<RememberToken>
 */
class RememberTokenRepository extends Repository implements RememberTokenStorageInterface
{
    protected const string ENTITY_CLASS = RememberToken::class;

    /**
     * @throws RepositoryException
     */
    public function store(
        RememberTokenRecord $token,
    ): void {
        $entity = new RememberToken();
        $entity->guard = $token->guard;
        $entity->userId = (string) $token->userId;
        $entity->selector = $token->selector;
        $entity->validatorHash = $token->validatorHash;
        $entity->expiresAt = $token->expiresAt;
        $entity->userAgent = $token->userAgent;
        $entity->createdAt = $this->now();

        $this->save($entity);
    }

    /**
     * @throws EntityException
     */
    public function findBySelector(
        string $guard,
        string $selector,
    ): ?RememberTokenRecord {
        return $this->findOneBy(['guard' => $guard, 'selector' => $selector])?->toRecord();
    }

    /**
     * A compare-and-swap on the validator hash: of two requests rotating the same token at once, the second
     * matches no row and gets false.
     */
    public function rotateValidator(
        string $guard,
        string $selector,
        string $currentValidatorHash,
        string $newValidatorHash,
    ): bool {
        $sql = sprintf(
            'UPDATE %s SET %s = ? WHERE %s = ? AND %s = ? AND %s = ?',
            $this->table(),
            $this->column('validator_hash'),
            $this->column('guard'),
            $this->column('selector'),
            $this->column('validator_hash'),
        );

        return $this->connection->execute($sql, [$newValidatorHash, $guard, $selector, $currentValidatorHash]) === 1;
    }

    public function deleteBySelector(
        string $guard,
        string $selector,
    ): void {
        $this->connection->execute(
            sprintf(
                'DELETE FROM %s WHERE %s = ? AND %s = ?',
                $this->table(),
                $this->column('guard'),
                $this->column('selector'),
            ),
            [$guard, $selector],
        );
    }

    public function deleteAllForUser(
        string $guard,
        int|string $userId,
        ?string $exceptSelector = null,
    ): int {
        $sql = sprintf(
            'DELETE FROM %s WHERE %s = ? AND %s = ?',
            $this->table(),
            $this->column('guard'),
            $this->column('user_id'),
        );
        $bindings = [$guard, (string) $userId];

        if ($exceptSelector !== null) {
            $sql .= sprintf(' AND %s <> ?', $this->column('selector'));
            $bindings[] = $exceptSelector;
        }

        return $this->connection->execute($sql, $bindings);
    }

    public function clearExpiredTokens(): int
    {
        $now = $this->hydrator->toDatabaseValue($this->now(), $this->metadata->properties['expiresAt']);

        return $this->connection->execute(
            sprintf('DELETE FROM %s WHERE %s <= ?', $this->table(), $this->column('expires_at')),
            [$now],
        );
    }

    public function clearAllTokens(): int
    {
        return $this->connection->execute(sprintf('DELETE FROM %s', $this->table()));
    }

    private function table(): string
    {
        return $this->connection->quoteIdentifier('remember_tokens');
    }

    private function column(
        string $name,
    ): string {
        return $this->connection->quoteIdentifier($name);
    }
}
