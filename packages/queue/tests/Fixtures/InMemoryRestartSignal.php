<?php

declare(strict_types=1);

namespace Marko\Queue\Tests\Fixtures;

use Marko\Queue\RestartSignalInterface;

/**
 * Restart signal held in memory, with sequential tokens, and a count of requests.
 */
class InMemoryRestartSignal implements RestartSignalInterface
{
    public int $requests = 0;

    public function __construct(
        private ?string $token = null,
    ) {}

    public function request(): void
    {
        $this->requests++;
        $this->token = 'token-' . $this->requests;
    }

    public function current(): ?string
    {
        return $this->token;
    }
}
