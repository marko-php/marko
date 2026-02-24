<?php

declare(strict_types=1);

namespace Marko\AuthenticationToken\Guard;

use Marko\Authentication\AuthenticatableInterface;
use Marko\Authentication\Contracts\GuardInterface;
use Marko\Authentication\Contracts\UserProviderInterface;
use Marko\AuthenticationToken\Contracts\TokenRepositoryInterface;
use Marko\AuthenticationToken\Entity\PersonalAccessToken;
use Marko\Routing\Http\Request;

class TokenGuard implements GuardInterface
{
    private bool $tokenResolved = false;

    private ?PersonalAccessToken $resolvedToken = null;

    public UserProviderInterface $provider {
        set {
            $this->provider = $value;
        }
    }

    public function __construct(
        private readonly TokenRepositoryInterface $repository,
        private readonly Request $request,
    ) {}

    public function check(): bool
    {
        return $this->user() !== null;
    }

    public function guest(): bool
    {
        return !$this->check();
    }

    public function extractToken(): ?string
    {
        $header = $this->request->header('Authorization');

        if ($header === null || !str_starts_with($header, 'Bearer ')) {
            return null;
        }

        return substr($header, 7);
    }

    private function resolveTokenEntity(): ?PersonalAccessToken
    {
        if ($this->tokenResolved) {
            return $this->resolvedToken;
        }

        $this->tokenResolved = true;
        $rawToken = $this->extractToken();

        if ($rawToken === null) {
            return null;
        }

        $tokenHash = hash('sha256', $rawToken);
        $this->resolvedToken = $this->repository->findByToken($tokenHash);

        return $this->resolvedToken;
    }

    public function user(): ?AuthenticatableInterface
    {
        $tokenEntity = $this->resolveTokenEntity();

        if ($tokenEntity === null) {
            return null;
        }

        return $this->provider->retrieveById($tokenEntity->tokenableId);
    }

    public function id(): int|string|null
    {
        return $this->user()?->getAuthIdentifier();
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
        return 'token';
    }

    public function hasAbility(
        string $ability,
    ): bool {
        $tokenEntity = $this->resolveTokenEntity();

        if ($tokenEntity === null || $tokenEntity->abilities === null) {
            return false;
        }

        $abilities = json_decode($tokenEntity->abilities, true);

        if (!is_array($abilities)) {
            return false;
        }

        return in_array($ability, $abilities, true);
    }
}
