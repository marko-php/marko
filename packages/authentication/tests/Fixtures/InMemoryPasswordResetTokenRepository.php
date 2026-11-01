<?php

declare(strict_types=1);

namespace Marko\Authentication\Tests\Fixtures;

use DateTimeImmutable;
use Marko\Authentication\Contracts\PasswordResetTokenRepositoryInterface;
use Marko\Authentication\PasswordReset\PasswordResetTokenRecord;

/**
 * Password reset tokens held in an array, keyed by user identifier. Nothing expires on its own.
 */
class InMemoryPasswordResetTokenRepository implements PasswordResetTokenRepositoryInterface
{
    /** @var array<int|string, PasswordResetTokenRecord> */
    public array $records = [];

    public function store(
        int|string $userId,
        string $tokenHash,
        DateTimeImmutable $createdAt,
    ): void {
        $this->records[$userId] = new PasswordResetTokenRecord($tokenHash, $createdAt);
    }

    public function find(
        int|string $userId,
    ): ?PasswordResetTokenRecord {
        return $this->records[$userId] ?? null;
    }

    public function delete(
        int|string $userId,
    ): void {
        unset($this->records[$userId]);
    }

    public function deleteExpired(
        DateTimeImmutable $before,
    ): int {
        $expired = array_filter(
            $this->records,
            fn (PasswordResetTokenRecord $record): bool => $record->createdAt < $before,
        );
        $this->records = array_diff_key($this->records, $expired);

        return count($expired);
    }
}
