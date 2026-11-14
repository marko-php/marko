<?php

declare(strict_types=1);

namespace Marko\Authentication\Tests\Unit\Guard;

use DateTimeImmutable;
use Marko\Authentication\AuthenticatableInterface;
use Marko\Authentication\Contracts\CookieJarInterface;
use Marko\Authentication\Contracts\UserProviderInterface;
use Marko\Authentication\Event\LoginEvent;
use Marko\Authentication\Exceptions\AuthException;
use Marko\Authentication\Guard\SessionGuard;
use Marko\Authentication\Token\RememberTokenManager;
use Marko\Testing\Fake\FakeAuthenticatable;
use Marko\Testing\Fake\FakeClock;
use Marko\Testing\Fake\FakeCookieJar;
use Marko\Testing\Fake\FakeEventDispatcher;
use Marko\Testing\Fake\FakeSession;
use Marko\Testing\Fake\FakeUserProvider;

function startedRememberSession(): FakeSession
{
    $session = new FakeSession();
    $session->start();

    return $session;
}

describe('SessionGuard remember-me', function (): void {
    it('throws AuthException when remember is requested without a cookie jar or token manager', function (): void {
        $user = new FakeAuthenticatable(id: 42);
        $guard = new SessionGuard(
            session: startedRememberSession(),
            provider: new FakeUserProvider([42 => $user]),
            name: 'web',
        );

        expect(fn () => $guard->login($user, remember: true))
            ->toThrow(AuthException::class, "Remember-me was requested on guard 'web'");
    });

    it('throws AuthException when the user provider does not store the remember token', function (): void {
        $user = new FakeAuthenticatable(id: 42);
        $provider = new readonly class () implements UserProviderInterface
        {
            public function retrieveById(
                int|string $identifier,
            ): ?AuthenticatableInterface {
                return null;
            }

            public function retrieveByCredentials(
                array $credentials,
            ): ?AuthenticatableInterface {
                return null;
            }

            public function validateCredentials(
                AuthenticatableInterface $user,
                array $credentials,
            ): bool {
                return false;
            }

            public function rehashPasswordIfNeeded(
                AuthenticatableInterface $user,
                array $credentials,
            ): void {}

            public function retrieveByRememberToken(
                int|string $identifier,
                string $token,
            ): ?AuthenticatableInterface {
                return null;
            }

            public function updateRememberToken(
                AuthenticatableInterface $user,
                ?string $token,
                ?DateTimeImmutable $expiresAt,
            ): void {}
        };
        $guard = new SessionGuard(
            session: startedRememberSession(),
            provider: $provider,
            name: 'web',
            cookieJar: new FakeCookieJar(),
            tokenManager: new RememberTokenManager(new FakeClock()),
        );

        expect(fn () => $guard->login($user, remember: true))
            ->toThrow(AuthException::class, 'did not store the remember token');
    });

    it('looks up remember users by the hashed token', function (): void {
        $tokenManager = new RememberTokenManager(new FakeClock());
        $user = new FakeAuthenticatable(id: 42);
        $user->setRememberToken($tokenManager->hash('plain-token'));
        $user->setRememberTokenExpiresAt($tokenManager->expiresAt());
        $cookieJar = new FakeCookieJar();
        $cookieJar->set('remember_web', '42|plain-token');
        $provider = new FakeUserProvider([42 => $user]);

        $guard = new SessionGuard(
            session: startedRememberSession(),
            provider: $provider,
            name: 'web',
            cookieJar: $cookieJar,
            tokenManager: $tokenManager,
        );

        expect($guard->user())->toBe($user)
            ->and($provider->lastRememberTokenUpdate['token'])->not->toBe($tokenManager->hash('plain-token'));
    });

    it('authenticates via remember cookie set by a previous login with FakeUserProvider', function (): void {
        $user = new FakeAuthenticatable(id: 42);
        $provider = new FakeUserProvider([42 => $user]);
        $tokenManager = new RememberTokenManager(new FakeClock());
        $cookieJar = new FakeCookieJar();

        $first = new SessionGuard(
            session: startedRememberSession(),
            provider: $provider,
            name: 'web',
            cookieJar: $cookieJar,
            tokenManager: $tokenManager,
        );
        $first->login($user, remember: true);

        $second = new SessionGuard(
            session: startedRememberSession(),
            provider: $provider,
            name: 'web',
            cookieJar: $cookieJar,
            tokenManager: $tokenManager,
        );

        expect($second->user())->toBe($user);
    });

    it('rejects a remember cookie whose token does not match', function (): void {
        $tokenManager = new RememberTokenManager(new FakeClock());
        $user = new FakeAuthenticatable(id: 42);
        $user->setRememberToken($tokenManager->hash('real-token'));
        $cookieJar = new FakeCookieJar();
        $cookieJar->set('remember_web', '42|forged-token');

        $guard = new SessionGuard(
            session: startedRememberSession(),
            provider: new FakeUserProvider([42 => $user]),
            name: 'web',
            cookieJar: $cookieJar,
            tokenManager: $tokenManager,
        );

        expect($guard->user())->toBeNull();
    });

    it('sets the remember cookie for the token manager lifetime', function (): void {
        $user = new FakeAuthenticatable(id: 42);
        $cookieJar = new class () implements CookieJarInterface
        {
            public ?int $minutes = null;

            public function get(
                string $name,
            ): ?string {
                return null;
            }

            public function set(
                string $name,
                string $value,
                int $minutes = 0,
            ): void {
                $this->minutes = $minutes;
            }

            public function delete(string $name): void {}
        };

        $guard = new SessionGuard(
            session: startedRememberSession(),
            provider: new FakeUserProvider([42 => $user]),
            name: 'web',
            cookieJar: $cookieJar,
            tokenManager: new RememberTokenManager(new FakeClock(), lifetimeMinutes: 90),
        );
        $guard->login($user, remember: true);

        expect($cookieJar->minutes)->toBe(90);
    });

    it('names the remember cookie with the configured prefix', function (): void {
        $user = new FakeAuthenticatable(id: 42);
        $cookieJar = new FakeCookieJar();

        $guard = new SessionGuard(
            session: startedRememberSession(),
            provider: new FakeUserProvider([42 => $user]),
            name: 'web',
            cookieJar: $cookieJar,
            tokenManager: new RememberTokenManager(new FakeClock()),
            rememberCookiePrefix: 'keep_',
        );
        $guard->login($user, remember: true);

        expect($cookieJar->cookies)->toHaveKey('keep_web')
            ->not->toHaveKey('remember_web');
    });
});

/**
 * A user holding a valid remember token, the matching cookie, and a guard to read it.
 *
 * @return array{guard: SessionGuard, user: FakeAuthenticatable, provider: FakeUserProvider, cookieJar: FakeCookieJar, session: FakeSession, clock: FakeClock, events: FakeEventDispatcher, tokenManager: RememberTokenManager}
 */
function rememberedUser(
    bool $withExpiry = true,
): array {
    $clock = new FakeClock('2026-01-01 12:00:00');
    $tokenManager = new RememberTokenManager($clock, lifetimeMinutes: 60);
    $user = new FakeAuthenticatable(id: 42);
    $user->setRememberToken($tokenManager->hash('plain-token'));
    $user->setRememberTokenExpiresAt($withExpiry ? $tokenManager->expiresAt() : null);
    $cookieJar = new FakeCookieJar();
    $cookieJar->set('remember_web', '42|plain-token');
    $provider = new FakeUserProvider([42 => $user]);
    $session = startedRememberSession();
    $events = new FakeEventDispatcher();

    $guard = new SessionGuard(
        session: $session,
        provider: $provider,
        name: 'web',
        cookieJar: $cookieJar,
        tokenManager: $tokenManager,
        eventDispatcher: $events,
    );

    return [
        'guard' => $guard,
        'user' => $user,
        'provider' => $provider,
        'cookieJar' => $cookieJar,
        'session' => $session,
        'clock' => $clock,
        'events' => $events,
        'tokenManager' => $tokenManager,
    ];
}

describe('SessionGuard remember-me expiry', function (): void {
    it('stores an expiry one token lifetime from now when logging in with remember', function (): void {
        $clock = new FakeClock('2026-01-01 12:00:00');
        $user = new FakeAuthenticatable(id: 42);
        $provider = new FakeUserProvider([42 => $user]);

        $guard = new SessionGuard(
            session: startedRememberSession(),
            provider: $provider,
            name: 'web',
            cookieJar: new FakeCookieJar(),
            tokenManager: new RememberTokenManager($clock, lifetimeMinutes: 60),
        );
        $guard->login($user, remember: true);

        expect($provider->lastRememberTokenUpdate['expiresAt'] ?? null)
            ->toEqual(new DateTimeImmutable('2026-01-01 13:00:00'))
            ->and($user->getRememberTokenExpiresAt())->toEqual(new DateTimeImmutable('2026-01-01 13:00:00'));
    });

    it('rejects a remember cookie once the server-side expiry has passed', function (): void {
        ['guard' => $guard, 'clock' => $clock, 'session' => $session] = rememberedUser();

        $clock->travel('+61 minutes');

        expect($guard->user())->toBeNull()
            ->and($session->has('auth_web_user_id'))->toBeFalse();
    });

    it('rejects a remember token at the exact expiry instant', function (): void {
        ['guard' => $guard, 'clock' => $clock] = rememberedUser();

        $clock->travel('+60 minutes');

        expect($guard->user())->toBeNull();
    });

    it('clears the expired token and cookie so they are not retried', function (): void {
        ['guard' => $guard, 'user' => $user, 'clock' => $clock, 'cookieJar' => $cookieJar] = rememberedUser();

        $clock->travel('+61 minutes');
        $guard->user();

        expect($user->getRememberToken())->toBeNull()
            ->and($user->getRememberTokenExpiresAt())->toBeNull()
            ->and($cookieJar->cookies)->not->toHaveKey('remember_web');
    });

    it('rejects a remember token stored without an expiry', function (): void {
        ['guard' => $guard] = rememberedUser(withExpiry: false);

        expect($guard->user())->toBeNull();
    });

    it('keeps the original expiry when rotating the token on a cookie login', function (): void {
        ['guard' => $guard, 'user' => $user, 'clock' => $clock, 'tokenManager' => $tokenManager] = rememberedUser();

        $clock->travel('+30 minutes');
        $guard->user();

        expect($user->getRememberToken())->not->toBe($tokenManager->hash('plain-token'))
            ->and($user->getRememberTokenExpiresAt())->toEqual(new DateTimeImmutable('2026-01-01 13:00:00'));
    });

    it('sets the rotated cookie for the time remaining until the original expiry', function (): void {
        $clock = new FakeClock('2026-01-01 12:30:00');
        $tokenManager = new RememberTokenManager($clock, lifetimeMinutes: 60);
        $user = new FakeAuthenticatable(id: 42);
        $user->setRememberToken($tokenManager->hash('plain-token'));
        $user->setRememberTokenExpiresAt(new DateTimeImmutable('2026-01-01 13:00:00'));
        $cookieJar = new class () implements CookieJarInterface
        {
            public ?int $minutes = null;

            public function get(
                string $name,
            ): ?string {
                return '42|plain-token';
            }

            public function set(
                string $name,
                string $value,
                int $minutes = 0,
            ): void {
                $this->minutes = $minutes;
            }

            public function delete(string $name): void {}
        };

        $guard = new SessionGuard(
            session: startedRememberSession(),
            provider: new FakeUserProvider([42 => $user]),
            name: 'web',
            cookieJar: $cookieJar,
            tokenManager: $tokenManager,
        );
        $guard->user();

        expect($cookieJar->minutes)->toBe(30);
    });

    it('throws AuthException when the user provider does not store the remember token expiry', function (): void {
        $user = new FakeAuthenticatable(id: 42);
        $provider = new readonly class () implements UserProviderInterface
        {
            public function retrieveById(
                int|string $identifier,
            ): ?AuthenticatableInterface {
                return null;
            }

            public function retrieveByCredentials(
                array $credentials,
            ): ?AuthenticatableInterface {
                return null;
            }

            public function validateCredentials(
                AuthenticatableInterface $user,
                array $credentials,
            ): bool {
                return false;
            }

            public function rehashPasswordIfNeeded(
                AuthenticatableInterface $user,
                array $credentials,
            ): void {}

            public function retrieveByRememberToken(
                int|string $identifier,
                string $token,
            ): ?AuthenticatableInterface {
                return null;
            }

            public function updateRememberToken(
                AuthenticatableInterface $user,
                ?string $token,
                ?DateTimeImmutable $expiresAt,
            ): void {
                // Stores the token but drops the expiry
                $user->setRememberToken($token);
            }
        };
        $guard = new SessionGuard(
            session: startedRememberSession(),
            provider: $provider,
            name: 'web',
            cookieJar: new FakeCookieJar(),
            tokenManager: new RememberTokenManager(new FakeClock()),
        );

        expect(fn () => $guard->login($user, remember: true))
            ->toThrow(AuthException::class, 'did not store the remember token');
    });
});

describe('SessionGuard remember-me cookie login', function (): void {
    it('stores the user in the session on a cookie login', function (): void {
        ['guard' => $guard, 'session' => $session] = rememberedUser();

        $guard->user();

        expect($session->get('auth_web_user_id'))->toBe(42);
    });

    it('regenerates the session ID on a cookie login', function (): void {
        ['guard' => $guard, 'session' => $session] = rememberedUser();
        $anonymousId = $session->getId();

        $guard->user();

        expect($session->regenerated)->toBeTrue()
            ->and($session->getId())->not->toBe($anonymousId);
    });

    it('dispatches LoginEvent with remember on a cookie login', function (): void {
        ['guard' => $guard, 'user' => $user, 'events' => $events] = rememberedUser();

        $guard->user();

        $dispatched = $events->dispatched(LoginEvent::class);

        expect($dispatched)->toHaveCount(1)
            ->and($dispatched[0])->toBeInstanceOf(LoginEvent::class)
            ->and($dispatched[0]->getUser())->toBe($user)
            ->and($dispatched[0]->getGuard())->toBe('web')
            ->and($dispatched[0]->getRemember())->toBeTrue();
    });

    it('consumes the remember cookie once per session', function (): void {
        [
            'guard' => $first,
            'user' => $user,
            'provider' => $provider,
            'cookieJar' => $cookieJar,
            'session' => $session,
            'tokenManager' => $tokenManager,
        ] = rememberedUser();

        $first->user();
        $tokenAfterFirstRequest = $user->getRememberToken();
        $cookieAfterFirstRequest = $cookieJar->cookies['remember_web'];

        // The next request carries the same session
        $events = new FakeEventDispatcher();
        $second = new SessionGuard(
            session: $session,
            provider: $provider,
            name: 'web',
            cookieJar: $cookieJar,
            tokenManager: $tokenManager,
            eventDispatcher: $events,
        );

        expect($second->user())->toBe($user)
            ->and($user->getRememberToken())->toBe($tokenAfterFirstRequest)
            ->and($cookieJar->cookies['remember_web'])->toBe($cookieAfterFirstRequest)
            ->and($events->dispatched(LoginEvent::class))->toBe([]);
    });

    it('does not dispatch LoginEvent for a rejected remember cookie', function (): void {
        ['guard' => $guard, 'clock' => $clock, 'events' => $events] = rememberedUser();

        $clock->travel('+2 hours');
        $guard->user();

        expect($events->dispatched(LoginEvent::class))->toBe([]);
    });
});
