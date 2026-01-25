<?php

declare(strict_types=1);

namespace Marko\Auth\Token;

use DateMalformedStringException;
use DateTimeImmutable;
use Random\RandomException;

class RememberTokenManager
{
    private int $lifetimeMinutes;

    public function __construct(
        ?int $lifetimeMinutes = null,
    ) {
        $this->lifetimeMinutes = $lifetimeMinutes ?? 43200; // 30 days default
    }

    /**
     * @throws RandomException
     */
    public function generate(): string
    {
        return bin2hex(random_bytes(32));
    }

    public function hash(
        string $token,
    ): string {
        return hash('sha256', $token);
    }

    public function validate(
        string $token,
        string $storedHash,
    ): bool {
        return hash_equals($storedHash, $this->hash($token));
    }

    /**
     * @throws DateMalformedStringException
     */
    public function isExpired(
        DateTimeImmutable $createdAt,
    ): bool {
        $expiresAt = $createdAt->modify("+$this->lifetimeMinutes minutes");

        return $expiresAt < new DateTimeImmutable();
    }

    /**
     * @param array<int, array{hash: string, created_at: DateTimeImmutable}> $tokens
     * @return array<int, array{hash: string, created_at: DateTimeImmutable}>
     * @throws DateMalformedStringException
     */
    public function filterExpired(
        array $tokens,
    ): array {
        return array_values(array_filter(
            $tokens,
            fn (array $token): bool => !$this->isExpired($token['created_at']),
        ));
    }
}
