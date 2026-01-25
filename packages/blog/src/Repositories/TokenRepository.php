<?php

declare(strict_types=1);

namespace Marko\Blog\Repositories;

use Closure;
use DateTimeImmutable;
use Marko\Blog\Entity\VerificationToken;
use Marko\Blog\Services\TokenRepositoryInterface;
use Marko\Database\Connection\ConnectionInterface;
use Marko\Database\Entity\Entity;
use Marko\Database\Entity\EntityHydrator;
use Marko\Database\Entity\EntityMetadataFactory;
use Marko\Database\Repository\Repository;

class TokenRepository extends Repository implements TokenRepositoryInterface
{
    protected const string ENTITY_CLASS = VerificationToken::class;

    public function __construct(
        ConnectionInterface $connection,
        EntityMetadataFactory $metadataFactory,
        EntityHydrator $hydrator,
        ?Closure $queryBuilderFactory = null,
    ) {
        parent::__construct($connection, $metadataFactory, $hydrator, $queryBuilderFactory);
    }

    public function save(
        VerificationToken|Entity $token,
    ): void {
        parent::save($token);
    }

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

    public function delete(
        VerificationToken|Entity $token,
    ): void {
        parent::delete($token);
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
