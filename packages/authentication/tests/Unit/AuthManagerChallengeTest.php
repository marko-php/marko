<?php

declare(strict_types=1);

use Marko\Authentication\AuthenticatableInterface;
use Marko\Authentication\AuthManager;
use Marko\Authentication\Config\AuthConfig;
use Marko\Authentication\Contracts\ChallengeableGuardInterface;
use Marko\Authentication\Contracts\LoginChallengeInterface;
use Marko\Authentication\Exceptions\AuthException;
use Marko\Authentication\LoginChallengeResolver;
use Marko\Authentication\Tests\Fixtures\BoundProviderResolver;
use Marko\Authentication\Tests\Fixtures\StatelessFakeGuard;
use Marko\Authentication\Token\RememberTokenManager;
use Marko\Core\Container\Container;
use Marko\Testing\Fake\FakeAuthenticatable;
use Marko\Testing\Fake\FakeClock;
use Marko\Testing\Fake\FakeConfigRepository;
use Marko\Testing\Fake\FakeCookieJar;
use Marko\Testing\Fake\FakeEventDispatcher;
use Marko\Testing\Fake\FakeSession;
use Marko\Testing\Fake\FakeUserProvider;

class AlwaysRequiredChallenge implements LoginChallengeInterface
{
    public function requiresChallenge(
        AuthenticatableInterface $user,
    ): bool {
        return true;
    }

    public function getName(): string
    {
        return 'always';
    }
}

/**
 * @param array<string, mixed> $guards
 */
function challengeAuthManager(
    array $guards,
    bool $withResolver = true,
): AuthManager {
    $session = new FakeSession();
    $session->start();
    $config = new AuthConfig(new FakeConfigRepository([
        'authentication.remember.cookie.prefix' => 'remember_',
        'authentication.default.guard' => 'web',
        'authentication.guards' => $guards,
    ]));

    return new AuthManager(
        config: $config,
        session: $session,
        providerResolver: BoundProviderResolver::for(new FakeUserProvider([1 => new FakeAuthenticatable(id: 1)])),
        eventDispatcher: new FakeEventDispatcher(),
        cookieJar: new FakeCookieJar(),
        rememberTokenManager: new RememberTokenManager(new FakeClock()),
        guardDriverRegistry: StatelessFakeGuard::tokenDriverRegistry(),
        loginChallengeResolver: $withResolver ? new LoginChallengeResolver(new Container()) : null,
    );
}

it('gives a session guard the challenges listed in its config', function (): void {
    $manager = challengeAuthManager([
        'web' => ['driver' => 'session', 'challenges' => [AlwaysRequiredChallenge::class]],
    ]);

    $guard = $manager->guard('web');

    expect($guard)->toBeInstanceOf(ChallengeableGuardInterface::class)
        ->and($guard->attempt(['email' => 'ada@example.com']))->toBeFalse()
        ->and($guard->pendingChallengeName())->toBe('always');
});

it('builds a session guard without challenges when none are configured', function (): void {
    $guard = challengeAuthManager(['web' => ['driver' => 'session']])->guard('web');

    expect($guard->attempt(['email' => 'ada@example.com']))->toBeTrue();
});

it('throws when a challenge class does not implement LoginChallengeInterface', function (): void {
    challengeAuthManager([
        'web' => ['driver' => 'session', 'challenges' => [stdClass::class]],
    ])->guard('web');
})->throws(
    AuthException::class,
    "Login challenge 'stdClass' on guard 'web' does not implement LoginChallengeInterface",
);

it('throws when challenges is not a list of class names', function (): void {
    challengeAuthManager([
        'web' => ['driver' => 'session', 'challenges' => AlwaysRequiredChallenge::class],
    ])->guard('web');
})->throws(AuthException::class, 'authentication.guards.web.challenges must be a list of class names');

it('throws when a guard whose driver does not support challenges lists them', function (): void {
    challengeAuthManager([
        'api' => ['driver' => 'token', 'challenges' => [AlwaysRequiredChallenge::class]],
    ])->guard('api');
})->throws(AuthException::class, "Guard 'api' lists login challenges, but its 'token' driver does not support them");

it('throws when challenges are configured but the manager has no challenge resolver', function (): void {
    challengeAuthManager(
        ['web' => ['driver' => 'session', 'challenges' => [AlwaysRequiredChallenge::class]]],
        withResolver: false,
    )->guard('web');
})->throws(AuthException::class, "Guard 'web' lists login challenges, but AuthManager has no LoginChallengeResolver");
