<?php

declare(strict_types=1);

namespace Marko\Authentication\Event;

use Marko\Core\Event\Event;

class FailedLoginEvent extends Event
{
    /**
     * @var array<string, mixed>
     */
    public readonly array $credentials;

    /**
     * @param array<string, mixed> $credentials
     */
    public function __construct(
        array $credentials,
        public readonly string $guard,
    ) {
        // Remove password from credentials for security
        unset($credentials['password']);
        $this->credentials = $credentials;
    }

    /**
     * @return array<string, mixed>
     */
    public function getCredentials(): array
    {
        return $this->credentials;
    }

    public function getGuard(): string
    {
        return $this->guard;
    }
}
