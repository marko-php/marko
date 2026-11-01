<?php

declare(strict_types=1);

namespace Marko\Authentication\Tests\Unit\Guard;

use Marko\Authentication\Config\AuthConfig;
use Marko\Authentication\Contracts\OtherDeviceLogoutInterface;
use Marko\Authentication\Contracts\UserPersisterInterface;
use Marko\Authentication\Event\OtherDevicesLoggedOutEvent;
use Marko\Authentication\Exceptions\AuthException;
use Marko\Authentication\Guard\SessionGuard;
use Marko\Authentication\Tests\Fixtures\InMemoryRememberTokenStorage;
use Marko\Authentication\Tests\Fixtures\VersionedUser;
use Marko\Authentication\Token\RememberTokenManager;
use Marko\Authentication\UserPersisterResolver;
use Marko\Core\Container\Container;
use Marko\Testing\Fake\FakeAuthenticatable;
use Marko\Testing\Fake\FakeClock;
use Marko\Testing\Fake\FakeConfigRepository;
use Marko\Testing\Fake\FakeCookieJar;
use Marko\Testing\Fake\FakeEventDispatcher;
use Marko\Testing\Fake\FakeSession;
use Marko\Testing\Fake\FakeUserPersister;
use Marko\Testing\Fake\FakeUserProvider;

/**
 * One versioned user, a second (unversioned) user, and the browsers (a session plus a cookie jar each)
 * they use. guardFor() builds the guard of a fresh request from one browser.
 */
class OtherDeviceWorld
{
    public FakeClock $clock;

    public RememberTokenManager $tokenManager;

    public InMemoryRememberTokenStorage $storage;

    public VersionedUser $user;

    public FakeAuthenticatable $plainUser;

    public FakeUserProvider $provider;

    public FakeUserPersister $persister;

    public FakeEventDispatcher $events;

    public function __construct(
        public bool $deviceStorage = true,
    ) {
        $this->clock = new FakeClock('2026-01-01 12:00:00');
        $this->tokenManager = new RememberTokenManager($this->clock, lifetimeMinutes: 60);
        $this->storage = new InMemoryRememberTokenStorage($this->clock);
        $this->user = new VersionedUser(id: 42);
        $this->plainUser = new FakeAuthenticatable(id: 7);
        $this->provider = new FakeUserProvider([42 => $this->user, 7 => $this->plainUser]);
        $this->persister = new FakeUserPersister();
        $this->events = new FakeEventDispatcher();
    }

    /**
     * @return array{session: FakeSession, cookies: FakeCookieJar}
     */
    public function browser(): array
    {
        $session = new FakeSession();
        $session->start();

        return ['session' => $session, 'cookies' => new FakeCookieJar()];
    }

    /**
     * @param array{session: FakeSession, cookies: FakeCookieJar} $browser
     */
    public function guardFor(
        array $browser,
        bool $withPersister = true,
    ): SessionGuard {
        $container = new Container();
        $container->instance(UserPersisterInterface::class, $this->persister);

        return new SessionGuard(
            session: $browser['session'],
            provider: $this->provider,
            name: 'web',
            cookieJar: $browser['cookies'],
            tokenManager: $this->tokenManager,
            eventDispatcher: $this->events,
            rememberTokenStorage: $this->deviceStorage ? $this->storage : null,
            userPersisterResolver: $withPersister
                ? new UserPersisterResolver(new AuthConfig(new FakeConfigRepository([])), $container)
                : null,
        );
    }
}

it('implements OtherDeviceLogoutInterface', function (): void {
    $world = new OtherDeviceWorld();

    expect($world->guardFor($world->browser()))->toBeInstanceOf(OtherDeviceLogoutInterface::class);
});

it(
    'keeps the current session and logs out another session with a stale version on its next request',
    function (): void {
        $world = new OtherDeviceWorld();
        $laptop = $world->browser();
        $phone = $world->browser();
        $world->guardFor($laptop)->login($world->user);
        $world->guardFor($phone)->login($world->user);

        $world->guardFor($laptop)->logoutOtherDevices($world->user);

        expect($world->guardFor($laptop)->user())->toBe($world->user)
            ->and($world->guardFor($phone)->user())->toBeNull()
            ->and($phone['session']->get('auth_web_user_id'))->toBeNull()
            ->and($phone['session']->get('auth_web_session_version'))->toBeNull()
            ->and($phone['session']->regenerated)->toBeTrue();
    },
);

it('stores a new session version on the user and saves it through the persister', function (): void {
    $world = new OtherDeviceWorld();
    $laptop = $world->browser();
    $world->guardFor($laptop)->login($world->user);

    $world->guardFor($laptop)->logoutOtherDevices($world->user);

    expect($world->user->getSessionVersion())->toMatch('/^[0-9a-f]{32}$/')
        ->and($world->persister->persisted())->toBe([$world->user])
        ->and($laptop['session']->get('auth_web_session_version'))->toBe($world->user->getSessionVersion());
});

it('copies the session version into the session at login', function (): void {
    $world = new OtherDeviceWorld();
    $world->user->setSessionVersion('v1');
    $laptop = $world->browser();

    $world->guardFor($laptop)->login($world->user);

    expect($laptop['session']->get('auth_web_session_version'))->toBe('v1');
});

it('accepts a session started before versioning while the user version was never changed', function (): void {
    $world = new OtherDeviceWorld();
    $laptop = $world->browser();
    $laptop['session']->set('auth_web_user_id', 42);

    expect($world->guardFor($laptop)->user())->toBe($world->user);
});

it('rejects a session started before versioning once the user version changed', function (): void {
    $world = new OtherDeviceWorld();
    $world->user->setSessionVersion('v2');
    $laptop = $world->browser();
    $laptop['session']->set('auth_web_user_id', 42);

    expect($world->guardFor($laptop)->user())->toBeNull();
});

it('deletes the other remember-me devices and keeps the current one', function (): void {
    $world = new OtherDeviceWorld();
    $laptop = $world->browser();
    $phone = $world->browser();
    $tablet = $world->browser();
    $world->guardFor($laptop)->login($world->user, remember: true);
    $world->guardFor($phone)->login($world->user, remember: true);
    $world->guardFor($tablet)->login($world->user, remember: true);
    $laptopSelector = explode(':', (string) $laptop['cookies']->get('remember_web'))[0];

    $world->guardFor($laptop)->logoutOtherDevices($world->user);

    $phoneLater = ['session' => $world->browser()['session'], 'cookies' => $phone['cookies']];
    $laptopLater = ['session' => $world->browser()['session'], 'cookies' => $laptop['cookies']];

    expect(array_map(fn ($token) => $token->selector, $world->storage->tokensFor('web', 42)))->toBe([$laptopSelector])
        ->and($world->guardFor($phoneLater)->user())->toBeNull()
        ->and($world->guardFor($laptopLater)->user())->toBe($world->user);
});

it('deletes every remember-me device when the current cookie belongs to another user', function (): void {
    $world = new OtherDeviceWorld();
    $phone = $world->browser();
    $adminBrowser = $world->browser();
    $world->guardFor($phone)->login($world->user, remember: true);
    $world->guardFor($adminBrowser)->login($world->plainUser, remember: true);

    $world->guardFor($adminBrowser)->logoutOtherDevices($world->user);

    expect($world->storage->tokensFor('web', 42))->toBe([])
        ->and($world->storage->tokensFor('web', 7))->toHaveCount(1)
        ->and($world->guardFor($adminBrowser)->user())->toBe($world->plainUser)
        ->and($adminBrowser['session']->get('auth_web_session_version'))->toBeNull();
});

it(
    'rotates the single-column token without device storage and keeps the current device remembered',
    function (): void {
        $world = new OtherDeviceWorld(deviceStorage: false);
        $laptop = $world->browser();
        $phone = $world->browser();
        $world->guardFor($laptop)->login($world->user, remember: true);
        $phone['cookies']->set('remember_web', (string) $laptop['cookies']->get('remember_web'));
        $oldCookie = $laptop['cookies']->get('remember_web');
        $expiresAt = $world->user->getRememberTokenExpiresAt();

        $world->guardFor($laptop)->logoutOtherDevices($world->user);

        $laptopLater = ['session' => $world->browser()['session'], 'cookies' => $laptop['cookies']];
        $phoneLater = ['session' => $world->browser()['session'], 'cookies' => $phone['cookies']];

        expect($laptop['cookies']->get('remember_web'))->not->toBe($oldCookie)
            ->and($world->user->getRememberTokenExpiresAt())->toEqual($expiresAt)
            ->and($world->guardFor($phoneLater)->user())->toBeNull()
            ->and($world->guardFor($laptopLater)->user())->toBe($world->user);
    },
);

it(
    'clears the single-column token without device storage when the current device is not remembered',
    function (): void {
        $world = new OtherDeviceWorld(deviceStorage: false);
        $phone = $world->browser();
        $laptop = $world->browser();
        $world->guardFor($phone)->login($world->user, remember: true);
        $world->guardFor($laptop)->login($world->user);

        $world->guardFor($laptop)->logoutOtherDevices($world->user);

        $phoneLater = ['session' => $world->browser()['session'], 'cookies' => $phone['cookies']];

        expect($world->user->getRememberToken())->toBeNull()
            ->and($world->user->getRememberTokenExpiresAt())->toBeNull()
            ->and($world->guardFor($phoneLater)->user())->toBeNull();
    },
);

it('dispatches OtherDevicesLoggedOutEvent with the user and guard', function (): void {
    $world = new OtherDeviceWorld();
    $laptop = $world->browser();
    $world->guardFor($laptop)->login($world->user);

    $world->guardFor($laptop)->logoutOtherDevices($world->user);

    $events = array_values(array_filter(
        $world->events->dispatched,
        fn (object $event): bool => $event instanceof OtherDevicesLoggedOutEvent,
    ));

    expect($events)->toHaveCount(1)
        ->and($events[0]->getUser())->toBe($world->user)
        ->and($events[0]->getGuard())->toBe('web');
});

it('only revokes remember-me devices for a user without a session version', function (): void {
    $world = new OtherDeviceWorld();
    $laptop = $world->browser();
    $phone = $world->browser();
    $world->guardFor($laptop)->login($world->plainUser, remember: true);
    $world->guardFor($phone)->login($world->plainUser, remember: true);

    $world->guardFor($laptop, withPersister: false)->logoutOtherDevices($world->plainUser);

    expect($world->storage->tokensFor('web', 7))->toHaveCount(1)
        ->and($world->persister->persisted())->toBe([])
        ->and($world->guardFor($phone)->user())->toBe($world->plainUser);
});

it('throws for a versioned user when the guard has no user persister resolver', function (): void {
    $world = new OtherDeviceWorld();
    $laptop = $world->browser();
    $world->guardFor($laptop)->login($world->user);

    expect(fn () => $world->guardFor($laptop, withPersister: false)->logoutOtherDevices($world->user))
        ->toThrow(AuthException::class, "Guard 'web' cannot end other sessions");
});
