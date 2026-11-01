<?php

declare(strict_types=1);

namespace Marko\Authentication\Contracts;

use DateTimeImmutable;
use Marko\Authentication\PasswordReset\PasswordResetTokenRecord;

/**
 * Stores password reset token hashes, one per user, keyed by auth identifier.
 *
 * Implementations only store data. Expiry, hash comparison and single use are
 * checked by PasswordResetBroker, so every storage behaves the same.
 */
interface PasswordResetTokenRepositoryInterface
{
    /**
     * Store a token hash for the user, replacing any token the user already has.
     */
    public function store(
        int|string $userId,
        string $tokenHash,
        DateTimeImmutable $createdAt,
    ): void;

    public function find(
        int|string $userId,
    ): ?PasswordResetTokenRecord;

    public function delete(
        int|string $userId,
    ): void;

    /**
     * Delete every token created before $before, returning how many were deleted.
     */
    public function deleteExpired(
        DateTimeImmutable $before,
    ): int;
}
