<?php

declare(strict_types=1);

namespace Marko\Auth\Guard;

use Marko\Auth\AuthenticatableInterface;
use Marko\Auth\Contracts\GuardInterface;
use Marko\Auth\Contracts\UserProviderInterface;

class TokenGuard implements GuardInterface
{
    /** @var array<string, string> */
    private array $headers = [];

    private ?AuthenticatableInterface $cachedUser = null;

    public function __construct(
        private readonly string $headerName = 'Authorization',
        private readonly string $prefix = 'Bearer ',
        private readonly string $name = 'token',
        public ?UserProviderInterface $provider = null {
            set {
                $this->provider = $value;
                $this->cachedUser = null;
            }
        },
    ) {}

    public function check(): bool
    {
        return $this->user() !== null;
    }

    public function guest(): bool
    {
        return !$this->check();
    }

    public function user(): ?AuthenticatableInterface
    {
        if ($this->cachedUser !== null) {
            return $this->cachedUser;
        }

        $token = $this->getTokenFromHeaders($this->headers);

        if ($token === null || $this->provider === null) {
            return null;
        }

        $this->cachedUser = $this->provider->retrieveByCredentials(['api_token' => $token]);

        return $this->cachedUser;
    }

    /**
     * Set the request headers for token extraction.
     *
     * @param array<string, string> $headers
     */
    public function setHeaders(
        array $headers,
    ): void {
        $this->headers = $headers;
        $this->cachedUser = null;
    }

    public function id(): int|string|null
    {
        return null;
    }

    public function attempt(
        array $credentials,
    ): bool {
        return false;
    }

    public function login(
        AuthenticatableInterface $user,
    ): void {}

    public function loginById(
        int|string $id,
    ): ?AuthenticatableInterface {
        return null;
    }

    public function logout(): void {}

    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Extract token from headers.
     *
     * @param array<string, string> $headers
     */
    public function getTokenFromHeaders(
        array $headers,
    ): ?string {
        if (!isset($headers[$this->headerName])) {
            return null;
        }

        $value = $headers[$this->headerName];

        if (!str_starts_with($value, $this->prefix)) {
            return null;
        }

        return substr($value, strlen($this->prefix));
    }
}
