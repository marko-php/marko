<?php

declare(strict_types=1);

namespace Marko\AuthenticationToken\Guard;

use Marko\Authentication\AuthenticatableInterface;
use Marko\Authentication\Contracts\AbilityScopedGuardInterface;
use Marko\Authentication\Contracts\StatelessGuardInterface;
use Marko\Authentication\Contracts\UserProviderInterface;
use Marko\AuthenticationToken\Contracts\TokenRepositoryInterface;
use Marko\AuthenticationToken\Entity\PersonalAccessToken;
use Marko\AuthenticationToken\Event\TokenAuthenticationFailedEvent;
use Marko\AuthenticationToken\Event\TokenFailureReason;
use Marko\AuthenticationToken\Exceptions\StatelessGuardException;
use Marko\AuthenticationToken\Http\CurrentRequest;
use Marko\Core\Contracts\ResettableInterface;
use Marko\Core\Event\EventDispatcherInterface;
use Marko\Database\Config\DatabaseTimezoneConfig;
use Marko\Routing\Http\Request;
use Override;
use Psr\Clock\ClockInterface;

/**
 * Authenticates each request from its `Authorization: Bearer <token>` header
 * against the personal_access_tokens table: the token is hashed with SHA-256,
 * looked up, and rejected once its expiry has passed. The stored expires_at is
 * read in the database timezone (`database.timezone`, UTC by default).
 *
 * Stateless: attempt(), login(), loginById() and logout() throw a
 * StatelessGuardException. Issue and revoke tokens with TokenManager instead.
 *
 * An unknown, revoked or expired token dispatches one
 * TokenAuthenticationFailedEvent per request. A missing token and a
 * successful authentication dispatch nothing.
 *
 * The user is loaded through the provider by the token's tokenable_id, and
 * accepted only when it is an instance of the token's tokenable_type (the
 * class it was issued for, or a subclass), so a token issued for one model
 * never authenticates a different model that shares its id.
 *
 * Abilities: a token issued with no abilities (`[]`, or a NULL column) or
 * with the `*` wildcard has the full authority of its user; otherwise it
 * grants exactly the abilities listed. The Gate, and so #[Can], denies any
 * ability the token does not grant (see AbilityScopedGuardInterface).
 *
 * Resettable: AuthManager caches this guard for the life of a worker, and its
 * reset() (run between requests) forgets the request and token resolved last.
 */
class TokenGuard implements StatelessGuardInterface, AbilityScopedGuardInterface, ResettableInterface
{
    private const string BEARER_PREFIX = 'Bearer ';

    private const string WILDCARD_ABILITY = '*';

    /** The request the cached token was resolved for; a new request re-resolves. */
    private ?Request $resolvedFor = null;

    private bool $tokenResolved = false;

    private ?PersonalAccessToken $resolvedToken = null;

    public function __construct(
        private readonly TokenRepositoryInterface $repository,
        private readonly CurrentRequest $currentRequest,
        private readonly ClockInterface $clock,
        private readonly DatabaseTimezoneConfig $databaseTimezoneConfig,
        public UserProviderInterface $provider {
            set {
                $this->provider = $value;
            }
        },
        private readonly string $name = 'token',
        private readonly ?EventDispatcherInterface $eventDispatcher = null,
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
        $header = $this->currentRequest->get()?->header('Authorization');

        if ($header === null || !str_starts_with($header, self::BEARER_PREFIX)) {
            return null;
        }

        return substr($header, strlen(self::BEARER_PREFIX));
    }

    public function user(): ?AuthenticatableInterface
    {
        $tokenEntity = $this->resolveTokenEntity();

        if ($tokenEntity === null) {
            return null;
        }

        $user = $this->provider->retrieveById($tokenEntity->tokenableId);

        if ($user === null || !is_a($user, $tokenEntity->tokenableType)) {
            return null;
        }

        return $user;
    }

    public function id(): int|string|null
    {
        return $this->user()?->getAuthIdentifier();
    }

    /**
     * @throws StatelessGuardException
     */
    public function attempt(
        array $credentials,
        bool $remember = false,
    ): bool {
        throw StatelessGuardException::forMethod($this->name, 'attempt');
    }

    /**
     * @throws StatelessGuardException
     */
    public function login(
        AuthenticatableInterface $user,
    ): void {
        throw StatelessGuardException::forMethod($this->name, 'login');
    }

    /**
     * @throws StatelessGuardException
     */
    public function loginById(
        int|string $id,
    ): ?AuthenticatableInterface {
        throw StatelessGuardException::forMethod($this->name, 'loginById');
    }

    /**
     * @throws StatelessGuardException
     */
    public function logout(): void
    {
        throw StatelessGuardException::forMethod($this->name, 'logout');
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getChallenge(): string
    {
        return 'Bearer';
    }

    /**
     * Forget the request and token resolved last, so a long-running worker
     * never holds a previous request's token between requests.
     */
    #[Override]
    public function reset(): void
    {
        $this->resolvedFor = null;
        $this->tokenResolved = false;
        $this->resolvedToken = null;
    }

    /**
     * Whether the current request's token grants the ability. A token with no
     * abilities (`[]` or NULL) or with `*` grants every ability; otherwise the
     * ability must be listed exactly. False without a valid token, and for an
     * abilities column that is not a JSON array (fail closed).
     */
    public function hasAbility(
        string $ability,
    ): bool {
        $tokenEntity = $this->resolveTokenEntity();

        if ($tokenEntity === null) {
            return false;
        }

        if ($tokenEntity->abilities === null) {
            return true;
        }

        $abilities = json_decode($tokenEntity->abilities, true);

        if (!is_array($abilities)) {
            return false;
        }

        if ($abilities === [] || in_array(self::WILDCARD_ABILITY, $abilities, true)) {
            return true;
        }

        return in_array($ability, $abilities, true);
    }

    /**
     * Resolve the valid token entity for the current request, once per request.
     */
    private function resolveTokenEntity(): ?PersonalAccessToken
    {
        $request = $this->currentRequest->get();

        if ($this->tokenResolved && $this->resolvedFor === $request) {
            return $this->resolvedToken;
        }

        $this->resolvedFor = $request;
        $this->tokenResolved = true;
        $this->resolvedToken = $this->lookUpToken($request);

        return $this->resolvedToken;
    }

    private function lookUpToken(
        ?Request $request,
    ): ?PersonalAccessToken {
        $rawToken = $this->extractToken();

        if ($request === null || $rawToken === null) {
            return null;
        }

        $token = $this->repository->findByToken(hash('sha256', $rawToken));

        if ($token === null) {
            $this->reportFailure($request, TokenFailureReason::Invalid, null);

            return null;
        }

        if ($token->expiresAt !== null && $this->databaseTimezoneConfig->parse(
            $token->expiresAt,
        ) < $this->clock->now()) {
            $this->reportFailure($request, TokenFailureReason::Expired, $token->id);

            return null;
        }

        return $token;
    }

    private function reportFailure(
        Request $request,
        TokenFailureReason $reason,
        ?int $tokenId,
    ): void {
        $this->eventDispatcher?->dispatch(new TokenAuthenticationFailedEvent(
            guard: $this->name,
            reason: $reason,
            tokenId: $tokenId,
            ipAddress: $request->ip(),
        ));
    }
}
