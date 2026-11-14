<?php

declare(strict_types=1);

namespace Marko\AuthenticationToken\Service;

use DateInterval;
use DateTimeImmutable;
use DateTimeInterface;
use Marko\Authentication\AuthenticatableInterface;
use Marko\AuthenticationToken\Config\TokenConfig;
use Marko\AuthenticationToken\Contracts\NewAccessToken;
use Marko\AuthenticationToken\Contracts\TokenRepositoryInterface;
use Marko\AuthenticationToken\Entity\PersonalAccessToken;
use Marko\AuthenticationToken\Event\AllTokensRevokedEvent;
use Marko\AuthenticationToken\Event\TokenCreatedEvent;
use Marko\AuthenticationToken\Event\TokenRevokedEvent;
use Marko\Config\Exceptions\ConfigException;
use Marko\Config\Exceptions\ConfigNotFoundException;
use Marko\Core\Event\EventDispatcherInterface;
use Marko\Database\Config\DatabaseTimezoneConfig;
use Psr\Clock\ClockInterface;
use Random\RandomException;
use RuntimeException;

/**
 * Issues and revokes personal access tokens, dispatching TokenCreatedEvent,
 * TokenRevokedEvent and AllTokensRevokedEvent. Events never carry the token
 * value.
 *
 * expires_at and created_at are stored in the database timezone
 * (`database.timezone`, UTC by default), whatever timezone the caller's
 * expiresAt or the clock is in.
 */
readonly class TokenManager
{
    public function __construct(
        private TokenRepositoryInterface $repository,
        private TokenConfig $config,
        private ClockInterface $clock,
        private DatabaseTimezoneConfig $databaseTimezoneConfig,
        private ?EventDispatcherInterface $eventDispatcher = null,
    ) {}

    /**
     * Without an explicit $expiresAt, the token expires after
     * `authentication-token.token_expiration_days` days, or never when that
     * config is null. An explicit $expiresAt always wins.
     *
     * $abilities scopes the token: an empty list or one containing '*' grants
     * every ability of the user, any other list exactly the abilities named.
     * The Gate (and so #[Can]) enforces them; see TokenGuard::hasAbility().
     *
     * @param array<string> $abilities
     * @throws ConfigException|ConfigNotFoundException|RuntimeException
     */
    public function createToken(
        AuthenticatableInterface $user,
        string $name,
        array $abilities = [],
        ?DateTimeInterface $expiresAt = null,
    ): NewAccessToken {
        // Read the config even when $expiresAt is given, so a bad value fails on the first token.
        $expirationDays = $this->config->expirationDays();
        $now = $this->clock->now();

        if (
            $expiresAt === null
            && $expirationDays !== null
        ) {
            $expiresAt = $now->add(new DateInterval("P{$expirationDays}D"));
        }

        try {
            $rawToken = bin2hex(random_bytes(40));
        } catch (RandomException) {
            throw new RuntimeException('Failed to generate a secure token.');
        }

        $token = new PersonalAccessToken();
        $token->tokenableType = get_class($user);
        $token->tokenableId = $user->getAuthIdentifier();
        $token->name = $name;
        $token->tokenHash = hash('sha256', $rawToken);
        $token->abilities = json_encode($abilities);
        $token->expiresAt = $expiresAt !== null ? $this->databaseTimezoneConfig->format($expiresAt) : null;
        $token->createdAt = $this->databaseTimezoneConfig->format($now);

        $saved = $this->repository->create($token);

        $this->eventDispatcher?->dispatch(new TokenCreatedEvent(
            user: $user,
            tokenId: $saved->id,
            name: $name,
            abilities: array_values($abilities),
            expiresAt: $expiresAt !== null ? DateTimeImmutable::createFromInterface($expiresAt) : null,
        ));

        return new NewAccessToken($saved, $rawToken);
    }

    public function revokeToken(
        int $tokenId,
    ): void {
        $this->repository->revoke($tokenId);

        $this->eventDispatcher?->dispatch(new TokenRevokedEvent($tokenId));
    }

    public function revokeAllTokens(
        AuthenticatableInterface $user,
    ): void {
        $this->repository->revokeAllForUser(
            get_class($user),
            $user->getAuthIdentifier(),
        );

        $this->eventDispatcher?->dispatch(new AllTokensRevokedEvent($user));
    }
}
