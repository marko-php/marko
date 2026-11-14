<?php

declare(strict_types=1);

namespace Marko\Broadcasting\Amphp\Auth;

/**
 * The verified contents of a subscriber token.
 */
readonly class AmphpTokenClaims
{
    /**
     * @param list<string> $channels Private channel names (with the private- prefix) the holder may stream
     */
    public function __construct(
        public string $userId,
        public array $channels,
        public int $expiresAt,
    ) {}

    public function allows(
        string $channel,
    ): bool {
        return in_array($channel, $this->channels, true);
    }
}
