<?php

declare(strict_types=1);

namespace Marko\Authentication\Tests\Unit\Guard;

use Marko\Authentication\AuthenticatableInterface;
use Marko\Authentication\Contracts\LoginChallengeInterface;
use Marko\Authentication\Contracts\LoginThrottleInterface;
use Marko\Authentication\Event\LoginEvent;
use Marko\Authentication\Guard\SessionGuard;
use Marko\Authentication\Token\RememberTokenManager;
use Marko\Testing\Fake\FakeAuthenticatable;
use Marko\Testing\Fake\FakeClock;
use Marko\Testing\Fake\FakeCookieJar;
use Marko\Testing\Fake\FakeEventDispatcher;
use Marko\Testing\Fake\FakeSession;
use Marko\Testing\Fake\FakeUserProvider;

/**
 * A session that counts how many times its ID was regenerated.
 */
function regenerationCountingSession(): FakeSession
{
    $session = new class () extends FakeSession
    {
        public int $regenerations = 0;

        public function regenerate(
            bool $deleteOldSession = true,
        ): void {
            $this->regenerations++;
            parent::regenerate($deleteOldSession);
        }
    };
    $session->start();

    return $session;
}

/**
 * A throttle that records every failure it is told about.
 */
function failureRecordingThrottle(): LoginThrottleInterface
{
    return new class () implements LoginThrottleInterface
    {
        public int $failures = 0;

        public function ensureNotLockedOut(
            string $guard,
            array $credentials,
        ): void {}

        public function recordFailure(
            string $guard,
            array $credentials,
        ): void {
            $this->failures++;
        }

        public function clear(
            string $guard,
            array $credentials,
        ): void {}
    };
}

function requiredChallenge(): LoginChallengeInterface
{
    return new readonly class () implements LoginChallengeInterface
    {
        public function requiresChallenge(
            AuthenticatableInterface $user,
        ): bool {
            return true;
        }

        public function getName(): string
        {
            return 'two_factor';
        }
    };
}

/**
 * @param list<LoginChallengeInterface> $challenges
 * @return array{guard: SessionGuard, session: FakeSession, events: FakeEventDispatcher, cookies: FakeCookieJar, provider: FakeUserProvider, throttle: LoginThrottleInterface}
 */
function rememberAttemptGuard(
    array $challenges = [],
    bool $validCredentials = true,
): array {
    $session = regenerationCountingSession();
    $user = new FakeAuthenticatable(id: 42);
    $events = new FakeEventDispatcher();
    $cookies = new FakeCookieJar();
    $throttle = failureRecordingThrottle();
    $provider = new FakeUserProvider(
        [42 => $user],
        fn (): bool => $validCredentials,
    );

    $guard = new SessionGuard(
        session: $session,
        provider: $provider,
        name: 'web',
        cookieJar: $cookies,
        tokenManager: new RememberTokenManager(new FakeClock()),
        eventDispatcher: $events,
        loginThrottle: $throttle,
        challenges: $challenges,
    );

    return [
        'guard' => $guard,
        'session' => $session,
        'events' => $events,
        'cookies' => $cookies,
        'provider' => $provider,
        'throttle' => $throttle,
    ];
}

/**
 * @return list<LoginEvent>
 */
function loginEvents(
    FakeEventDispatcher $events,
): array {
    return array_values(array_filter(
        $events->dispatched,
        fn (object $event): bool => $event instanceof LoginEvent,
    ));
}

const REMEMBER_CREDENTIALS = ['email' => 'ada@example.com', 'password' => 'secret'];

describe('SessionGuard::attempt() with remember', function (): void {
    it('logs in once with a remember cookie when remember is true', function (): void {
        ['guard' => $guard, 'session' => $session, 'events' => $events, 'cookies' => $cookies] = rememberAttemptGuard();

        expect($guard->attempt(REMEMBER_CREDENTIALS, remember: true))->toBeTrue();

        $logins = loginEvents($events);

        expect($cookies->get('remember_web'))->not->toBeNull()
            ->and($logins)->toHaveCount(1)
            ->and($logins[0]->remember)->toBeTrue()
            ->and($session->regenerations)->toBe(1);
    });

    it('logs in without a remember cookie by default', function (): void {
        ['guard' => $guard, 'events' => $events, 'cookies' => $cookies, 'provider' => $provider] = rememberAttemptGuard();

        expect($guard->attempt(REMEMBER_CREDENTIALS))->toBeTrue();

        $logins = loginEvents($events);

        expect($cookies->get('remember_web'))->toBeNull()
            ->and($provider->lastRememberTokenUpdate)->toBeNull()
            ->and($logins)->toHaveCount(1)
            ->and($logins[0]->remember)->toBeFalse();
    });

    it('creates no remember token for invalid credentials and records a throttle failure', function (): void {
        [
            'guard' => $guard,
            'events' => $events,
            'cookies' => $cookies,
            'provider' => $provider,
            'throttle' => $throttle,
        ] = rememberAttemptGuard(validCredentials: false);

        expect($guard->attempt(REMEMBER_CREDENTIALS, remember: true))->toBeFalse()
            ->and($cookies->get('remember_web'))->toBeNull()
            ->and($provider->lastRememberTokenUpdate)->toBeNull()
            ->and(loginEvents($events))->toBeEmpty()
            ->and($throttle->failures)->toBe(1);
    });

    it('carries remember through a challenge so completeChallenge() issues the cookie', function (): void {
        ['guard' => $guard, 'events' => $events, 'cookies' => $cookies] = rememberAttemptGuard([requiredChallenge()]);

        expect($guard->attempt(REMEMBER_CREDENTIALS, remember: true))->toBeFalse()
            ->and($cookies->get('remember_web'))->toBeNull()
            ->and(loginEvents($events))->toBeEmpty();

        $guard->completeChallenge();

        $logins = loginEvents($events);

        expect($guard->check())->toBeTrue()
            ->and($cookies->get('remember_web'))->not->toBeNull()
            ->and($logins)->toHaveCount(1)
            ->and($logins[0]->remember)->toBeTrue();
    });

    it('completes a challenge without remember when attempt() did not ask for it', function (): void {
        ['guard' => $guard, 'events' => $events, 'cookies' => $cookies] = rememberAttemptGuard([requiredChallenge()]);

        $guard->attempt(REMEMBER_CREDENTIALS);
        $guard->completeChallenge();

        expect($cookies->get('remember_web'))->toBeNull()
            ->and(loginEvents($events)[0]->remember)->toBeFalse();
    });

    it('lets an explicit completeChallenge() flag override the one from attempt()', function (): void {
        ['guard' => $guard, 'events' => $events, 'cookies' => $cookies] = rememberAttemptGuard([requiredChallenge()]);

        $guard->attempt(REMEMBER_CREDENTIALS, remember: true);
        $guard->completeChallenge(remember: false);

        expect($cookies->get('remember_web'))->toBeNull()
            ->and(loginEvents($events)[0]->remember)->toBeFalse();
    });

    it('forgets the remember flag when the challenge is cancelled', function (): void {
        ['guard' => $guard, 'session' => $session] = rememberAttemptGuard([requiredChallenge()]);

        $guard->attempt(REMEMBER_CREDENTIALS, remember: true);

        expect($session->has('auth_web_challenge_remember'))->toBeTrue();

        $guard->cancelChallenge();

        expect($session->has('auth_web_challenge_remember'))->toBeFalse();
    });

    it('forgets the remember flag once the challenge completes', function (): void {
        ['guard' => $guard, 'session' => $session] = rememberAttemptGuard([requiredChallenge()]);

        $guard->attempt(REMEMBER_CREDENTIALS, remember: true);
        $guard->completeChallenge();

        expect($session->has('auth_web_challenge_remember'))->toBeFalse();
    });
});
