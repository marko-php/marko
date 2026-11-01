<?php

declare(strict_types=1);

namespace Marko\Authentication\Tests\Fixtures;

use Marko\Authentication\Contracts\RememberTokenStorageInterface;
use Marko\Authentication\Token\RememberTokenRecord;
use Psr\Clock\ClockInterface;

/**
 * Per-device remember tokens held in an array, keyed by "guard:selector".
 */
class InMemoryRememberTokenStorage implements RememberTokenStorageInterface
{
    /** @var array<string, RememberTokenRecord> */
    public array $tokens = [];

    public function __construct(
        private readonly ClockInterface $clock,
    ) {}

    public function store(
        RememberTokenRecord $token,
    ): void {
        $this->tokens[$token->guard . ':' . $token->selector] = $token;
    }

    public function findBySelector(
        string $guard,
        string $selector,
    ): ?RememberTokenRecord {
        return $this->tokens["$guard:$selector"] ?? null;
    }

    public function rotateValidator(
        string $guard,
        string $selector,
        string $currentValidatorHash,
        string $newValidatorHash,
    ): bool {
        $token = $this->tokens["$guard:$selector"] ?? null;

        if ($token === null || $token->validatorHash !== $currentValidatorHash) {
            return false;
        }

        $this->tokens["$guard:$selector"] = new RememberTokenRecord(
            guard: $token->guard,
            userId: $token->userId,
            selector: $token->selector,
            validatorHash: $newValidatorHash,
            expiresAt: $token->expiresAt,
            userAgent: $token->userAgent,
        );

        return true;
    }

    public function deleteBySelector(
        string $guard,
        string $selector,
    ): void {
        unset($this->tokens["$guard:$selector"]);
    }

    public function deleteAllForUser(
        string $guard,
        int|string $userId,
        ?string $exceptSelector = null,
    ): int {
        $deleted = 0;

        foreach ($this->tokensFor($guard, $userId) as $token) {
            if ($token->selector !== $exceptSelector) {
                unset($this->tokens["$guard:$token->selector"]);
                $deleted++;
            }
        }

        return $deleted;
    }

    public function clearExpiredTokens(): int
    {
        $now = $this->clock->now();
        $live = array_filter($this->tokens, fn (RememberTokenRecord $token): bool => $token->expiresAt > $now);
        $cleared = count($this->tokens) - count($live);
        $this->tokens = $live;

        return $cleared;
    }

    public function clearAllTokens(): int
    {
        $cleared = count($this->tokens);
        $this->tokens = [];

        return $cleared;
    }

    /**
     * @return list<RememberTokenRecord> The tokens stored for one user on a guard
     */
    public function tokensFor(
        string $guard,
        int|string $userId,
    ): array {
        return array_values(array_filter(
            $this->tokens,
            fn (RememberTokenRecord $token): bool => $token->guard === $guard
                && (string) $token->userId === (string) $userId,
        ));
    }
}
