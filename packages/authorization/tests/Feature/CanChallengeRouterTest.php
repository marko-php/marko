<?php

declare(strict_types=1);

namespace Marko\Authorization\Tests\Feature;

use DateTimeImmutable;
use Marko\Authentication\Contracts\GuardInterface;
use Marko\AuthenticationToken\Contracts\TokenRepositoryInterface;
use Marko\AuthenticationToken\Entity\PersonalAccessToken;
use Marko\AuthenticationToken\Guard\TokenGuard;
use Marko\AuthenticationToken\Http\CurrentRequest;
use Marko\AuthenticationToken\Middleware\TokenRequestMiddleware;
use Marko\Authorization\Attributes\Can;
use Marko\Authorization\AuthorizableInterface;
use Marko\Authorization\Contracts\GateInterface;
use Marko\Authorization\Gate;
use Marko\Authorization\Middleware\AuthorizationMiddleware;
use Marko\Authorization\PolicyRegistry;
use Marko\Authorization\Tests\Support\GateHooks;
use Marko\Core\Container\Container;
use Marko\Database\Config\DatabaseTimezoneConfig;
use Marko\Routing\Http\Request;
use Marko\Routing\Http\Response;
use Marko\Routing\RouteCollection;
use Marko\Routing\RouteDefinition;
use Marko\Routing\RouteMatcher;
use Marko\Routing\Router;
use Marko\Testing\Fake\FakeEventDispatcher;
use Marko\Testing\Fake\FakeGuard;
use Marko\Testing\Fake\FakeUserProvider;
use Psr\Clock\ClockInterface;

class ChallengeApiController
{
    /** @noinspection PhpUnused - Invoked via the router */
    #[Can('view-reports')]
    public function reports(): Response
    {
        return new Response(body: 'reports');
    }
}

class ChallengeStubUser implements AuthorizableInterface
{
    public function getAuthIdentifier(): int
    {
        return 7;
    }

    public function getAuthIdentifierName(): string
    {
        return 'id';
    }

    public function getAuthPassword(): string
    {
        return 'hashed';
    }

    public function getRememberToken(): ?string
    {
        return null;
    }

    public function setRememberToken(
        ?string $token,
    ): void {}

    public function getRememberTokenExpiresAt(): ?DateTimeImmutable
    {
        return null;
    }

    public function setRememberTokenExpiresAt(
        ?DateTimeImmutable $expiresAt,
    ): void {}

    public function getRememberTokenName(): string
    {
        return 'remember_token';
    }

    public function can(
        string $ability,
        mixed ...$arguments,
    ): bool {
        return true;
    }
}

/**
 * Knows three tokens belonging to ChallengeStubUser: "valid-token" (no
 * abilities, so full access), "reports-token" (scoped to view-reports) and
 * "read-only-token" (scoped to posts:read).
 */
class ChallengeTokenRepository implements TokenRepositoryInterface
{
    private const array ABILITIES = [
        'valid-token' => null,
        'reports-token' => ['view-reports'],
        'read-only-token' => ['posts:read'],
    ];

    public function find(
        int $id,
    ): ?PersonalAccessToken {
        return null;
    }

    public function findByToken(
        string $tokenHash,
    ): ?PersonalAccessToken {
        foreach (self::ABILITIES as $rawToken => $abilities) {
            if (!hash_equals(hash('sha256', $rawToken), $tokenHash)) {
                continue;
            }

            $token = new PersonalAccessToken();
            $token->id = 1;
            $token->tokenableType = ChallengeStubUser::class;
            $token->tokenableId = 7;
            $token->tokenHash = $tokenHash;
            $token->abilities = $abilities !== null ? json_encode($abilities) : null;

            return $token;
        }

        return null;
    }

    public function create(
        PersonalAccessToken $token,
    ): PersonalAccessToken {
        return $token;
    }

    public function revoke(
        int $id,
    ): void {}

    public function revokeAllForUser(
        string $type,
        int|string $id,
    ): void {}
}

class ChallengeClock implements ClockInterface
{
    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('2026-01-01 00:00:00');
    }
}

/**
 * A Router with TokenRequestMiddleware (so TokenGuard sees the request) ahead
 * of AuthorizationMiddleware, whose guard and Gate use the given guard.
 */
function createChallengeRouter(
    GuardInterface $guard,
    CurrentRequest $currentRequest,
): Router {
    $gate = new Gate(
        guard: $guard,
        policyRegistry: new PolicyRegistry(),
        gateHookRegistry: GateHooks::registry(),
        eventDispatcher: new FakeEventDispatcher(),
    );
    $gate->define('view-reports', fn (?AuthorizableInterface $user): bool => true);

    $container = new Container();
    $container->instance(CurrentRequest::class, $currentRequest);
    $container->bind(
        TokenRequestMiddleware::class,
        fn (): TokenRequestMiddleware => new TokenRequestMiddleware($currentRequest),
    );
    $container->bind(
        AuthorizationMiddleware::class,
        fn (): AuthorizationMiddleware => new AuthorizationMiddleware(
            gate: fn (): GateInterface => $gate,
            guard: fn (): GuardInterface => $guard,
        ),
    );

    $routes = new RouteCollection();
    $routes->add(new RouteDefinition(
        method: 'GET',
        path: '/api/reports',
        controller: ChallengeApiController::class,
        action: 'reports',
    ));

    return new Router(
        matcher: new RouteMatcher($routes),
        container: $container,
        globalMiddleware: [TokenRequestMiddleware::class, AuthorizationMiddleware::class],
    );
}

function createTokenGuardRouter(): Router
{
    $currentRequest = new CurrentRequest();
    $guard = new TokenGuard(
        repository: new ChallengeTokenRepository(),
        currentRequest: $currentRequest,
        clock: new ChallengeClock(),
        databaseTimezoneConfig: DatabaseTimezoneConfig::fromName('UTC'),
        provider: new FakeUserProvider([7 => new ChallengeStubUser()]),
    );

    return createChallengeRouter($guard, $currentRequest);
}

/**
 * @param array<string, string> $headers
 */
function createChallengeRequest(
    array $headers = [],
): Request {
    return new Request(server: [
        'REQUEST_METHOD' => 'GET',
        'REQUEST_URI' => '/api/reports',
        'HTTP_ACCEPT' => 'application/json',
        ...$headers,
    ]);
}

it('sends WWW-Authenticate: Bearer on a token guard Can route without a token', function (): void {
    $response = createTokenGuardRouter()->handle(createChallengeRequest());

    expect($response->statusCode())->toBe(401)
        ->and($response->headers()['WWW-Authenticate'] ?? null)->toBe('Bearer')
        ->and(json_decode($response->body(), true))->toBe(['message' => 'Unauthorized.']);
});

it('sends WWW-Authenticate: Bearer on a token guard Can route with an unknown token', function (): void {
    $response = createTokenGuardRouter()->handle(
        createChallengeRequest(['HTTP_AUTHORIZATION' => 'Bearer unknown-token']),
    );

    expect($response->statusCode())->toBe(401)
        ->and($response->headers()['WWW-Authenticate'] ?? null)->toBe('Bearer');
});

it('returns 200 through the router for a Can route on the token guard with a valid token', function (): void {
    $response = createTokenGuardRouter()->handle(
        createChallengeRequest(['HTTP_AUTHORIZATION' => 'Bearer valid-token']),
    );

    expect($response->statusCode())->toBe(200)
        ->and($response->body())->toBe('reports');
});

it('returns 200 for a Can route when the token is scoped to that ability', function (): void {
    $response = createTokenGuardRouter()->handle(
        createChallengeRequest(['HTTP_AUTHORIZATION' => 'Bearer reports-token']),
    );

    expect($response->statusCode())->toBe(200);
});

it('returns 403 for a Can route when the token is not scoped to that ability', function (): void {
    $response = createTokenGuardRouter()->handle(
        createChallengeRequest(['HTTP_AUTHORIZATION' => 'Bearer read-only-token']),
    );

    expect($response->statusCode())->toBe(403)
        ->and(json_decode($response->body(), true))->toBe(['message' => 'Forbidden.']);
});

it('returns 401 without WWW-Authenticate through the router for a guest on a session-style guard', function (): void {
    $router = createChallengeRouter(
        new FakeGuard(name: 'web', attemptResult: false),
        new CurrentRequest(),
    );

    $response = $router->handle(createChallengeRequest());

    expect($response->statusCode())->toBe(401)
        ->and($response->headers())->not->toHaveKey('WWW-Authenticate');
});
