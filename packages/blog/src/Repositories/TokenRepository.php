<?php

declare(strict_types=1);

namespace Marko\Blog\Repositories;

use DateTimeImmutable;
use Marko\Blog\Entity\VerificationToken;
use Marko\Blog\Services\TokenRepositoryInterface;
use Marko\Database\Repository\Repository;

/**
 * @extends Repository<VerificationToken>
 */
class TokenRepository extends Repository implements TokenRepositoryInterface
{
    protected const string ENTITY_CLASS = VerificationToken::class;

    public function findByToken(
        string $token,
    ): ?VerificationToken {
        return $this->findOneBy(['token' => $token]);
    }

    public function findByCommentId(
        int $commentId,
    ): ?VerificationToken {
        return $this->findOneBy(['comment_id' => $commentId]);
    }

    public function findBrowserTokenForEmail(
        string $email,
    ): ?VerificationToken {
        return $this->findOneBy([
            'email' => $email,
            'type' => 'browser',
        ]);
    }

    public function deleteExpiredEmailTokens(
        int $expiryDays,
    ): int {
        $expiryDate = (new DateTimeImmutable())->modify("-$expiryDays days");

        $sql = sprintf(
            'DELETE FROM %s WHERE type = ? AND expires_at < ?',
            $this->metadata->tableName,
        );

        return $this->connection->execute($sql, [
            'email',
            $expiryDate->format('Y-m-d H:i:s'),
        ]);
    }

    public function deleteExpiredBrowserTokens(
        int $cookieDays,
    ): int {
        $expiryDate = (new DateTimeImmutable())->modify("-$cookieDays days");

        $sql = sprintf(
            'DELETE FROM %s WHERE type = ? AND created_at < ?',
            $this->metadata->tableName,
        );

        return $this->connection->execute($sql, [
            'browser',
            $expiryDate->format('Y-m-d H:i:s'),
        ]);
    }
}
