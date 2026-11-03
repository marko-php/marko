<?php

declare(strict_types=1);

use Marko\Authentication\AuthManager;
use Marko\Authentication\Config\AuthConfig;
use Marko\Authentication\Contracts\GuardInterface;
use Marko\Authentication\Contracts\UserPersisterInterface;
use Marko\Authentication\Contracts\UserProviderInterface;
use Marko\Authentication\Event\LoginEvent;
use Marko\Authentication\Exceptions\AuthException;
use Marko\Authentication\Guard\GuardDriverRegistry;
use Marko\Authentication\Guard\SessionGuard;
use Marko\Authentication\Tests\Fixtures\BoundProviderResolver;
use Marko\Authentication\Tests\Fixtures\StatelessFakeGuard;
use Marko\Authentication\Tests\Fixtures\VersionedUser;
use Marko\Authentication\Token\RememberTokenManager;
use Marko\Authentication\UserPersisterResolver;
use Marko\Authentication\UserProviderResolver;
use Marko\Core\Container\Container;
use Marko\Core\Contracts\ResettableInterface;
use Marko\Testing\Fake\FakeAuthenticatable;
use Marko\Testing\Fake\FakeClock;
use Marko\Testing\Fake\FakeConfigRepository;
use Marko\Testing\Fake\FakeCookieJar;
use Marko\Testing\Fake\FakeEventDispatcher;
use Marko\Testing\Fake\FakeGuard;
use Marko\Testing\Fake\FakeSession;
use Marko\Testing\Fake\FakeUserPersister;
use Marko\Testing\Fake\FakeUserProvider;

class PerGuardAdminProvider extends FakeUserProvider {}

class ManagerAdminPersister extends FakeUserPersister {}

test('auth manager exists', function (): void {
    expect(class_exists(AuthManager::class))->toBeTrue();
});

test('it resolves default guard', function (): void {
    $configRepo = new FakeConfigRepository([
        'authentication.remember.cookie.prefix' => 'remember_',
        'authentication.default.guard' => 'web',
        'authentication.guards' => [
            'web' => ['driver' => 'session', 'provider' => 'users'],
        ],
    ]);

    $authConfig = new AuthConfig($configRepo);
    $session = new FakeSession();
    $session->start();
    $provider = new FakeUserProvider();

    $manager = new AuthManager(
        config: $authConfig,
        session: $session,
        providerResolver: BoundProviderResolver::for($provider),
        eventDispatcher: new FakeEventDispatcher(),
        cookieJar: new FakeCookieJar(),
        rememberTokenManager: new RememberTokenManager(new FakeClock()),
        guardDriverRegistry: StatelessFakeGuard::tokenDriverRegistry(),
    );

    $guard = $manager->guard();

    expect($guard)->toBeInstanceOf(GuardInterface::class);
});

test('it resolves named guard', function (): void {
    $configRepo = new FakeConfigRepository([
        'authentication.remember.cookie.prefix' => 'remember_',
        'authentication.default.guard' => 'web',
        'authentication.guards' => [
            'web' => ['driver' => 'session', 'provider' => 'users'],
            'api' => ['driver' => 'token', 'provider' => 'users'],
        ],
    ]);

    $authConfig = new AuthConfig($configRepo);
    $session = new FakeSession();
    $session->start();
    $provider = new FakeUserProvider();

    $manager = new AuthManager(
        config: $authConfig,
        session: $session,
        providerResolver: BoundProviderResolver::for($provider),
        eventDispatcher: new FakeEventDispatcher(),
        cookieJar: new FakeCookieJar(),
        rememberTokenManager: new RememberTokenManager(new FakeClock()),
        guardDriverRegistry: StatelessFakeGuard::tokenDriverRegistry(),
    );

    $guard = $manager->guard('api');

    expect($guard)->toBeInstanceOf(GuardInterface::class)
        ->and($guard->getName())->toBe('api');
});

test('it caches guard instances', function (): void {
    $configRepo = new FakeConfigRepository([
        'authentication.remember.cookie.prefix' => 'remember_',
        'authentication.default.guard' => 'web',
        'authentication.guards' => [
            'web' => ['driver' => 'session', 'provider' => 'users'],
        ],
    ]);

    $authConfig = new AuthConfig($configRepo);
    $session = new FakeSession();
    $session->start();
    $provider = new FakeUserProvider();

    $manager = new AuthManager(
        config: $authConfig,
        session: $session,
        providerResolver: BoundProviderResolver::for($provider),
        eventDispatcher: new FakeEventDispatcher(),
        cookieJar: new FakeCookieJar(),
        rememberTokenManager: new RememberTokenManager(new FakeClock()),
        guardDriverRegistry: StatelessFakeGuard::tokenDriverRegistry(),
    );

    $guard1 = $manager->guard('web');
    $guard2 = $manager->guard('web');

    expect($guard1)->toBe($guard2);
});

test('it proxies check to default guard', function (): void {
    $configRepo = new FakeConfigRepository([
        'authentication.remember.cookie.prefix' => 'remember_',
        'authentication.default.guard' => 'web',
        'authentication.guards' => [
            'web' => ['driver' => 'session', 'provider' => 'users'],
        ],
    ]);

    $authConfig = new AuthConfig($configRepo);
    $session = new FakeSession();
    $session->start();
    $provider = new FakeUserProvider();

    $manager = new AuthManager(
        config: $authConfig,
        session: $session,
        providerResolver: BoundProviderResolver::for($provider),
        eventDispatcher: new FakeEventDispatcher(),
        cookieJar: new FakeCookieJar(),
        rememberTokenManager: new RememberTokenManager(new FakeClock()),
        guardDriverRegistry: StatelessFakeGuard::tokenDriverRegistry(),
    );

    // No user authenticated, so check() should return false
    expect($manager->check())->toBeFalse();
});

test('it proxies user to default guard', function (): void {
    $configRepo = new FakeConfigRepository([
        'authentication.remember.cookie.prefix' => 'remember_',
        'authentication.default.guard' => 'web',
        'authentication.guards' => [
            'web' => ['driver' => 'session', 'provider' => 'users'],
        ],
    ]);

    $authConfig = new AuthConfig($configRepo);
    $session = new FakeSession();
    $session->start();
    $provider = new FakeUserProvider();

    $manager = new AuthManager(
        config: $authConfig,
        session: $session,
        providerResolver: BoundProviderResolver::for($provider),
        eventDispatcher: new FakeEventDispatcher(),
        cookieJar: new FakeCookieJar(),
        rememberTokenManager: new RememberTokenManager(new FakeClock()),
        guardDriverRegistry: StatelessFakeGuard::tokenDriverRegistry(),
    );

    // No user authenticated, so user() should return null
    expect($manager->user())->toBeNull();
});

test('it proxies id to default guard', function (): void {
    $configRepo = new FakeConfigRepository([
        'authentication.remember.cookie.prefix' => 'remember_',
        'authentication.default.guard' => 'web',
        'authentication.guards' => [
            'web' => ['driver' => 'session', 'provider' => 'users'],
        ],
    ]);

    $authConfig = new AuthConfig($configRepo);
    $session = new FakeSession();
    $session->start();
    $provider = new FakeUserProvider();

    $manager = new AuthManager(
        config: $authConfig,
        session: $session,
        providerResolver: BoundProviderResolver::for($provider),
        eventDispatcher: new FakeEventDispatcher(),
        cookieJar: new FakeCookieJar(),
        rememberTokenManager: new RememberTokenManager(new FakeClock()),
        guardDriverRegistry: StatelessFakeGuard::tokenDriverRegistry(),
    );

    // No user authenticated, so id() should return null
    expect($manager->id())->toBeNull();
});

test('it proxies attempt to default guard', function (): void {
    $user = new FakeAuthenticatable(id: 42);
    $configRepo = new FakeConfigRepository([
        'authentication.remember.cookie.prefix' => 'remember_',
        'authentication.default.guard' => 'web',
        'authentication.guards' => [
            'web' => ['driver' => 'session', 'provider' => 'users'],
        ],
    ]);

    $authConfig = new AuthConfig($configRepo);
    $session = new FakeSession();
    $session->start();
    $provider = new FakeUserProvider([42 => $user]);

    $manager = new AuthManager(
        config: $authConfig,
        session: $session,
        providerResolver: BoundProviderResolver::for($provider),
        eventDispatcher: new FakeEventDispatcher(),
        cookieJar: new FakeCookieJar(),
        rememberTokenManager: new RememberTokenManager(new FakeClock()),
        guardDriverRegistry: StatelessFakeGuard::tokenDriverRegistry(),
    );

    $result = $manager->attempt(['email' => 'test@example.com', 'password' => 'secret']);

    expect($result)->toBeTrue()
        ->and($manager->check())->toBeTrue();
});

test('it forwards the remember flag of attempt to the guard', function (): void {
    $manager = new AuthManager(
        config: new AuthConfig(new FakeConfigRepository([
            'authentication.remember.cookie.prefix' => 'remember_',
            'authentication.default.guard' => 'web',
            'authentication.guards' => [
                'web' => ['driver' => 'session', 'provider' => 'users'],
            ],
        ])),
        session: new FakeSession(),
        providerResolver: BoundProviderResolver::for(new FakeUserProvider()),
        eventDispatcher: new FakeEventDispatcher(),
        cookieJar: new FakeCookieJar(),
        rememberTokenManager: new RememberTokenManager(new FakeClock()),
        guardDriverRegistry: StatelessFakeGuard::tokenDriverRegistry(),
    );
    $guard = new FakeGuard(name: 'web');
    $manager->useGuard('web', $guard);

    $manager->attempt(['email' => 'test@example.com', 'password' => 'secret'], true);
    $manager->attempt(['email' => 'other@example.com', 'password' => 'secret']);

    expect($guard->attemptRemembers)->toBe([true, false]);
});

test('it proxies logout to default guard', function (): void {
    $user = new FakeAuthenticatable(id: 42);
    $configRepo = new FakeConfigRepository([
        'authentication.remember.cookie.prefix' => 'remember_',
        'authentication.default.guard' => 'web',
        'authentication.guards' => [
            'web' => ['driver' => 'session', 'provider' => 'users'],
        ],
    ]);

    $authConfig = new AuthConfig($configRepo);
    $session = new FakeSession();
    $session->start();
    $provider = new FakeUserProvider([42 => $user]);

    $manager = new AuthManager(
        config: $authConfig,
        session: $session,
        providerResolver: BoundProviderResolver::for($provider),
        eventDispatcher: new FakeEventDispatcher(),
        cookieJar: new FakeCookieJar(),
        rememberTokenManager: new RememberTokenManager(new FakeClock()),
        guardDriverRegistry: StatelessFakeGuard::tokenDriverRegistry(),
    );

    // Login first
    $manager->attempt(['email' => 'test@example.com', 'password' => 'secret']);
    expect($manager->check())->toBeTrue();

    // Logout
    $manager->logout();

    expect($manager->check())->toBeFalse();
});

test('it creates session guard for session driver', function (): void {
    $configRepo = new FakeConfigRepository([
        'authentication.remember.cookie.prefix' => 'remember_',
        'authentication.default.guard' => 'web',
        'authentication.guards' => [
            'web' => ['driver' => 'session', 'provider' => 'users'],
        ],
    ]);

    $authConfig = new AuthConfig($configRepo);
    $session = new FakeSession();
    $session->start();
    $provider = new FakeUserProvider();

    $manager = new AuthManager(
        config: $authConfig,
        session: $session,
        providerResolver: BoundProviderResolver::for($provider),
        eventDispatcher: new FakeEventDispatcher(),
        cookieJar: new FakeCookieJar(),
        rememberTokenManager: new RememberTokenManager(new FakeClock()),
        guardDriverRegistry: StatelessFakeGuard::tokenDriverRegistry(),
    );

    $guard = $manager->guard('web');

    expect($guard)->toBeInstanceOf(SessionGuard::class);
});

test('it creates the guard registered for the token driver', function (): void {
    $configRepo = new FakeConfigRepository([
        'authentication.remember.cookie.prefix' => 'remember_',
        'authentication.default.guard' => 'web',
        'authentication.guards' => [
            'web' => ['driver' => 'session', 'provider' => 'users'],
            'api' => ['driver' => 'token', 'provider' => 'users'],
        ],
    ]);

    $authConfig = new AuthConfig($configRepo);
    $session = new FakeSession();
    $session->start();
    $provider = new FakeUserProvider();

    $manager = new AuthManager(
        config: $authConfig,
        session: $session,
        providerResolver: BoundProviderResolver::for($provider),
        eventDispatcher: new FakeEventDispatcher(),
        cookieJar: new FakeCookieJar(),
        rememberTokenManager: new RememberTokenManager(new FakeClock()),
        guardDriverRegistry: StatelessFakeGuard::tokenDriverRegistry(),
    );

    $guard = $manager->guard('api');

    expect($guard)->toBeInstanceOf(StatelessFakeGuard::class);
});

test('it throws for unknown guard driver', function (): void {
    $configRepo = new FakeConfigRepository([
        'authentication.remember.cookie.prefix' => 'remember_',
        'authentication.default.guard' => 'custom',
        'authentication.guards' => [
            'custom' => ['driver' => 'unknown_driver', 'provider' => 'users'],
        ],
    ]);

    $authConfig = new AuthConfig($configRepo);
    $session = new FakeSession();
    $session->start();
    $provider = new FakeUserProvider();

    $manager = new AuthManager(
        config: $authConfig,
        session: $session,
        providerResolver: BoundProviderResolver::for($provider),
        eventDispatcher: new FakeEventDispatcher(),
        cookieJar: new FakeCookieJar(),
        rememberTokenManager: new RememberTokenManager(new FakeClock()),
        guardDriverRegistry: StatelessFakeGuard::tokenDriverRegistry(),
    );

    $manager->guard('custom');
})->throws(AuthException::class, 'Unknown guard driver');

describe('undefined guards', function (): void {
    beforeEach(function (): void {
        $session = new FakeSession();
        $session->start();

        $this->manager = new AuthManager(
            config: new AuthConfig(new FakeConfigRepository([
                'authentication.remember.cookie.prefix' => 'remember_',
                'authentication.default.guard' => 'wbe',
                'authentication.guards' => [
                    'web' => ['driver' => 'session', 'provider' => 'users'],
                    'api' => ['driver' => 'token', 'provider' => 'users'],
                    'driverless' => ['provider' => 'users'],
                ],
            ])),
            session: $session,
            providerResolver: BoundProviderResolver::for(new FakeUserProvider()),
            eventDispatcher: new FakeEventDispatcher(),
            cookieJar: new FakeCookieJar(),
            rememberTokenManager: new RememberTokenManager(new FakeClock()),
            guardDriverRegistry: StatelessFakeGuard::tokenDriverRegistry(),
        );
    });

    it('throws for a guard name missing from authentication.guards', function (): void {
        expect(fn () => $this->manager->guard('nonexistent'))
            ->toThrow(AuthException::class, "Guard 'nonexistent' is not defined");
    });

    it('throws for a default guard name missing from authentication.guards', function (): void {
        expect(fn () => $this->manager->guard())
            ->toThrow(AuthException::class, "Guard 'wbe' is not defined");
    });

    it('lists the configured guards when the guard name is undefined', function (): void {
        try {
            $this->manager->guard('nonexistent');
            $this->fail('Expected AuthException');
        } catch (AuthException $e) {
            expect($e->getContext())->toContain('Configured guards: web, api, driverless');
        }
    });

    it('suggests adding the guard or fixing the default guard name', function (): void {
        try {
            $this->manager->guard('nonexistent');
            $this->fail('Expected AuthException');
        } catch (AuthException $e) {
            expect($e->getSuggestion())->toContain('authentication.guards.nonexistent')
                ->toContain('authentication.default.guard')
                ->toContain('authorization.default_guard');
        }
    });

    it('throws for a configured guard with no driver', function (): void {
        expect(fn () => $this->manager->guard('driverless'))
            ->toThrow(AuthException::class, "Guard 'driverless' has no driver");
    });

    it('throws missingGuardDriver when the driver is null, empty or not a string', function (mixed $guardEntry): void {
        $session = new FakeSession();
        $session->start();

        $manager = new AuthManager(
            config: new AuthConfig(new FakeConfigRepository([
                'authentication.remember.cookie.prefix' => 'remember_',
                'authentication.default.guard' => 'web',
                'authentication.guards' => [
                    'web' => $guardEntry,
                ],
            ])),
            session: $session,
            providerResolver: BoundProviderResolver::for(new FakeUserProvider()),
            eventDispatcher: new FakeEventDispatcher(),
            cookieJar: new FakeCookieJar(),
            rememberTokenManager: new RememberTokenManager(new FakeClock()),
        );

        expect(fn () => $manager->guard())
            ->toThrow(AuthException::class, "Guard 'web' has no driver");
    })->with([
        'null driver' => [['driver' => null, 'provider' => 'users']],
        'empty driver' => [['driver' => '', 'provider' => 'users']],
        'integer driver' => [['driver' => 1, 'provider' => 'users']],
        'entry that is not an array' => ['session'],
    ]);

    it('names the driver config key when the guard has no driver', function (): void {
        try {
            $this->manager->guard('driverless');
            $this->fail('Expected AuthException');
        } catch (AuthException $e) {
            expect($e->getSuggestion())->toContain('authentication.guards.driverless.driver');
        }
    });
});

test('it handles multiple guards', function (): void {
    $user = new FakeAuthenticatable(id: 42);
    $configRepo = new FakeConfigRepository([
        'authentication.remember.cookie.prefix' => 'remember_',
        'authentication.default.guard' => 'web',
        'authentication.guards' => [
            'web' => ['driver' => 'session', 'provider' => 'users'],
            'api' => ['driver' => 'token', 'provider' => 'users'],
            'admin' => ['driver' => 'session', 'provider' => 'admins'],
        ],
    ]);

    $authConfig = new AuthConfig($configRepo);
    $session = new FakeSession();
    $session->start();
    $provider = new FakeUserProvider([42 => $user]);

    $manager = new AuthManager(
        config: $authConfig,
        session: $session,
        providerResolver: BoundProviderResolver::for($provider),
        eventDispatcher: new FakeEventDispatcher(),
        cookieJar: new FakeCookieJar(),
        rememberTokenManager: new RememberTokenManager(new FakeClock()),
        guardDriverRegistry: StatelessFakeGuard::tokenDriverRegistry(),
    );

    // Get multiple guards
    $webGuard = $manager->guard('web');
    $apiGuard = $manager->guard('api');
    $adminGuard = $manager->guard('admin');

    // Verify they are different instances
    expect($webGuard)->not->toBe($apiGuard)
        ->and($webGuard)->not->toBe($adminGuard)
        ->and($apiGuard)->not->toBe($adminGuard)
        ->and($webGuard->getName())->toBe('web')
        ->and($apiGuard->getName())->toBe('api')
        ->and($adminGuard->getName())->toBe('admin')
        ->and($webGuard)->toBeInstanceOf(SessionGuard::class)
        ->and($apiGuard)->toBeInstanceOf(StatelessFakeGuard::class)
        ->and($adminGuard)->toBeInstanceOf(SessionGuard::class);

    // Verify they have correct names

    // Verify they are correct types

    // Login on web guard
    $manager->guard('web')->attempt(['email' => 'test@example.com', 'password' => 'secret']);

    // Web guard should be authenticated
    expect($manager->guard('web')->check())->toBeTrue()
        ->and($manager->guard('api')->check())->toBeFalse();

    // API guard (token-based) should not be authenticated
});

describe('session guard collaborators', function (): void {
    it('passes the event dispatcher to session guards', function (): void {
        $user = new FakeAuthenticatable(id: 42);
        $session = new FakeSession();
        $session->start();
        $dispatcher = new FakeEventDispatcher();

        $manager = new AuthManager(
            config: new AuthConfig(new FakeConfigRepository([
                'authentication.remember.cookie.prefix' => 'remember_',
                'authentication.default.guard' => 'web',
                'authentication.guards' => ['web' => ['driver' => 'session']],
            ])),
            session: $session,
            providerResolver: BoundProviderResolver::for(new FakeUserProvider([42 => $user])),
            eventDispatcher: $dispatcher,
            cookieJar: new FakeCookieJar(),
            rememberTokenManager: new RememberTokenManager(new FakeClock()),
            guardDriverRegistry: StatelessFakeGuard::tokenDriverRegistry(),
        );

        $manager->guard()->login($user);

        expect($dispatcher->dispatched)->toHaveCount(1)
            ->and($dispatcher->dispatched[0])->toBeInstanceOf(LoginEvent::class);
    });

    it('passes the cookie jar, token manager and configured cookie prefix to session guards', function (): void {
        $user = new FakeAuthenticatable(id: 42);
        $session = new FakeSession();
        $session->start();
        $cookieJar = new FakeCookieJar();

        $manager = new AuthManager(
            config: new AuthConfig(new FakeConfigRepository([
                'authentication.remember.cookie.prefix' => 'keep_',
                'authentication.default.guard' => 'web',
                'authentication.guards' => ['web' => ['driver' => 'session']],
            ])),
            session: $session,
            providerResolver: BoundProviderResolver::for(new FakeUserProvider([42 => $user])),
            eventDispatcher: new FakeEventDispatcher(),
            cookieJar: $cookieJar,
            rememberTokenManager: new RememberTokenManager(new FakeClock()),
            guardDriverRegistry: StatelessFakeGuard::tokenDriverRegistry(),
        );

        $manager->guard()->login($user, remember: true);

        expect($cookieJar->cookies)->toHaveKey('keep_web')
            ->and($cookieJar->cookies['keep_web'])->toStartWith('42|')
            ->and($user->getRememberToken())->not->toBeNull();
    });
});

describe('useGuard', function (): void {
    beforeEach(function (): void {
        $session = new FakeSession();
        $session->start();

        $this->manager = new AuthManager(
            config: new AuthConfig(new FakeConfigRepository([
                'authentication.remember.cookie.prefix' => 'remember_',
                'authentication.default.guard' => 'web',
                'authentication.guards' => [
                    'web' => ['driver' => 'session', 'provider' => 'users'],
                    'api' => ['driver' => 'token', 'provider' => 'users'],
                ],
            ])),
            session: $session,
            providerResolver: BoundProviderResolver::for(new FakeUserProvider()),
            eventDispatcher: new FakeEventDispatcher(),
            cookieJar: new FakeCookieJar(),
            rememberTokenManager: new RememberTokenManager(new FakeClock()),
            guardDriverRegistry: StatelessFakeGuard::tokenDriverRegistry(),
        );
    });

    it('returns the guard registered with useGuard for that name', function (): void {
        $guard = new FakeGuard(name: 'api');

        $this->manager->useGuard('api', $guard);

        expect($this->manager->guard('api'))->toBe($guard);
    });

    it('uses the registered guard for the default guard when no name is given', function (): void {
        $guard = new FakeGuard(name: 'web');

        $this->manager->useGuard('web', $guard);

        expect($this->manager->guard())->toBe($guard);
    });

    it('replaces a guard that was already built', function (): void {
        $built = $this->manager->guard('web');
        $guard = new FakeGuard(name: 'web');

        $this->manager->useGuard('web', $guard);

        expect($built)->toBeInstanceOf(SessionGuard::class)
            ->and($this->manager->guard('web'))->toBe($guard);
    });

    it('returns a guard put in place with useGuard under an unconfigured name', function (): void {
        $guard = new FakeGuard(name: 'unconfigured');

        $this->manager->useGuard('unconfigured', $guard);

        expect($this->manager->guard('unconfigured'))->toBe($guard);
    });
});

describe('reset', function (): void {
    beforeEach(function (): void {
        $this->session = new FakeSession();
        $this->session->start();

        $this->manager = new AuthManager(
            config: new AuthConfig(new FakeConfigRepository([
                'authentication.remember.cookie.prefix' => 'remember_',
                'authentication.default.guard' => 'web',
                'authentication.guards' => [
                    'web' => ['driver' => 'session', 'provider' => 'users'],
                    'api' => ['driver' => 'token', 'provider' => 'users'],
                ],
            ])),
            session: $this->session,
            providerResolver: BoundProviderResolver::for(
                new FakeUserProvider(users: [1 => new FakeAuthenticatable(id: 1)]),
            ),
            eventDispatcher: new FakeEventDispatcher(),
            cookieJar: new FakeCookieJar(),
            rememberTokenManager: new RememberTokenManager(new FakeClock()),
        );
    });

    it('is resettable, so a long-running worker clears it between requests', function (): void {
        expect($this->manager)->toBeInstanceOf(ResettableInterface::class);
    });

    it('clears the user a session guard it built cached for the previous request', function (): void {
        $this->manager->guard('web')->loginById(1);

        // The next request carries no session: only the guard's cache remembers the user.
        $this->session->remove('auth_web_user_id');
        $this->manager->reset();

        expect($this->manager->guard('web')->check())->toBeFalse();
    });

    it('keeps the guards it built, and guards registered with useGuard, in place', function (): void {
        $built = $this->manager->guard('web');
        $registered = new FakeGuard(name: 'api');
        $registered->setUser(new FakeAuthenticatable(id: 7));
        $this->manager->useGuard('api', $registered);

        $this->manager->reset();

        expect($this->manager->guard('web'))->toBe($built)
            ->and($this->manager->guard('api'))->toBe($registered)
            ->and($registered->id())->toBe(7);
    });

    it('resets a resettable guard built by a registered driver, such as the token guard', function (): void {
        $tokenGuard = new class (name: 'api') extends FakeGuard implements ResettableInterface
        {
            public int $resets = 0;

            public function reset(): void
            {
                $this->resets++;
            }
        };
        $registry = new GuardDriverRegistry();
        $registry->extend('token', fn (): GuardInterface => $tokenGuard);
        $manager = new AuthManager(
            config: new AuthConfig(new FakeConfigRepository([
                'authentication.remember.cookie.prefix' => 'remember_',
                'authentication.default.guard' => 'web',
                'authentication.guards' => [
                    'api' => ['driver' => 'token', 'provider' => 'users'],
                ],
            ])),
            session: $this->session,
            providerResolver: BoundProviderResolver::for(new FakeUserProvider()),
            eventDispatcher: new FakeEventDispatcher(),
            cookieJar: new FakeCookieJar(),
            rememberTokenManager: new RememberTokenManager(new FakeClock()),
            guardDriverRegistry: $registry,
        );

        $built = $manager->guard('api');
        $manager->reset();

        expect($built)->toBe($tokenGuard)
            ->and($tokenGuard->resets)->toBe(1)
            ->and($manager->guard('api'))->toBe($tokenGuard);
    });
});

describe('per-guard user providers', function (): void {
    beforeEach(function (): void {
        $this->session = new FakeSession();
        $this->session->start();

        $this->customer = new FakeAuthenticatable(id: 1);
        $this->admin = new FakeAuthenticatable(id: 1);
        $this->customers = new FakeUserProvider(users: [1 => $this->customer]);
        $this->admins = new PerGuardAdminProvider(users: [1 => $this->admin]);

        $container = new Container();
        $container->instance(UserProviderInterface::class, $this->customers);
        $container->instance(PerGuardAdminProvider::class, $this->admins);

        $config = new AuthConfig(new FakeConfigRepository([
            'authentication.remember.cookie.prefix' => 'remember_',
            'authentication.default.guard' => 'session',
            'authentication.guards' => [
                'session' => ['driver' => 'session', 'provider' => 'users'],
                'admin' => ['driver' => 'session', 'provider' => 'admins'],
            ],
            'authentication.providers' => [
                'users' => [],
                'admins' => ['class' => PerGuardAdminProvider::class],
            ],
        ]));

        $this->manager = new AuthManager(
            config: $config,
            session: $this->session,
            providerResolver: new UserProviderResolver($config, $container),
            eventDispatcher: new FakeEventDispatcher(),
            cookieJar: new FakeCookieJar(),
            rememberTokenManager: new RememberTokenManager(new FakeClock()),
        );
    });

    it('gives each guard the provider its config names', function (): void {
        $web = $this->manager->guard('session');
        $admin = $this->manager->guard('admin');

        expect($web)->toBeInstanceOf(SessionGuard::class)
            ->and($admin)->toBeInstanceOf(SessionGuard::class)
            ->and($web->provider)->toBe($this->customers)
            ->and($admin->provider)->toBe($this->admins);
    });

    it('does not authenticate the admin guard from a frontend login', function (): void {
        $this->manager->guard('session')->loginById(1);

        expect($this->manager->guard('session')->user())->toBe($this->customer)
            ->and($this->manager->guard('admin')->check())->toBeFalse()
            ->and($this->session->get('auth_session_user_id'))->toBe(1)
            ->and($this->session->get('auth_admin_user_id'))->toBeNull();
    });

    it('does not authenticate the frontend guard from an admin login', function (): void {
        $this->manager->guard('admin')->loginById(1);

        expect($this->manager->guard('admin')->user())->toBe($this->admin)
            ->and($this->manager->guard('session')->check())->toBeFalse();
    });
});

describe('logoutOtherDevices', function (): void {
    beforeEach(function (): void {
        $this->user = new VersionedUser(id: 42);
        $this->session = new FakeSession();
        $this->session->start();
        $this->boundPersister = new FakeUserPersister();
        $this->adminPersister = new ManagerAdminPersister();
        $config = new AuthConfig(new FakeConfigRepository([
            'authentication.remember.cookie.prefix' => 'remember_',
            'authentication.default.guard' => 'web',
            'authentication.guards' => [
                'web' => ['driver' => 'session'],
                'admin' => ['driver' => 'session', 'provider' => 'admins'],
                'api' => ['driver' => 'token'],
            ],
            'authentication.providers' => ['admins' => ['persister' => ManagerAdminPersister::class]],
        ]));
        $container = new Container();
        $container->instance(UserPersisterInterface::class, $this->boundPersister);
        $container->instance(ManagerAdminPersister::class, $this->adminPersister);

        $this->manager = new AuthManager(
            config: $config,
            session: $this->session,
            providerResolver: BoundProviderResolver::for(new FakeUserProvider([42 => $this->user])),
            eventDispatcher: new FakeEventDispatcher(),
            cookieJar: new FakeCookieJar(),
            rememberTokenManager: new RememberTokenManager(new FakeClock()),
            guardDriverRegistry: StatelessFakeGuard::tokenDriverRegistry(),
            userPersisterResolver: new UserPersisterResolver($config, $container),
        );
    });

    it('ends other sessions on the default guard, saving through the bound persister', function (): void {
        $this->manager->guard()->login($this->user);

        $this->manager->logoutOtherDevices($this->user);

        expect($this->boundPersister->persisted())->toBe([$this->user])
            ->and($this->session->get('auth_web_session_version'))->toBe($this->user->getSessionVersion())
            ->and($this->manager->guard()->user())->toBe($this->user);
    });

    it("saves through the persister of the named guard's provider", function (): void {
        $this->manager->logoutOtherDevices($this->user, 'admin');

        expect($this->adminPersister->persisted())->toBe([$this->user])
            ->and($this->boundPersister->persisted())->toBe([]);
    });

    it('throws for a guard that cannot log out other devices', function (): void {
        expect(fn () => $this->manager->logoutOtherDevices($this->user, 'api'))
            ->toThrow(AuthException::class, "Guard 'api' cannot log out other devices");
    });
});
