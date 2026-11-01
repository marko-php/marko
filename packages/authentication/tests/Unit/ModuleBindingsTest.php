<?php

declare(strict_types=1);

use Marko\Authentication\AuthenticatableInterface;
use Marko\Authentication\AuthManager;
use Marko\Authentication\Config\AuthConfig;
use Marko\Authentication\Contracts\CookieJarInterface;
use Marko\Authentication\Contracts\EmailVerifierInterface;
use Marko\Authentication\Contracts\GuardInterface;
use Marko\Authentication\Contracts\IntendedUrlInterface;
use Marko\Authentication\Contracts\LoginChallengeInterface;
use Marko\Authentication\Contracts\LoginThrottleInterface;
use Marko\Authentication\Contracts\PasswordConfirmationInterface;
use Marko\Authentication\Contracts\PasswordHasherInterface;
use Marko\Authentication\Contracts\PasswordResetBrokerInterface;
use Marko\Authentication\Contracts\PasswordResetTokenRepositoryInterface;
use Marko\Authentication\Contracts\PasswordVerifierInterface;
use Marko\Authentication\Contracts\ResetLinkNotifierInterface;
use Marko\Authentication\Contracts\UserPersisterInterface;
use Marko\Authentication\Contracts\UserProviderInterface;
use Marko\Authentication\Contracts\VerificationNotifierInterface;
use Marko\Authentication\EmailVerification\EmailVerifier;
use Marko\Authentication\Guard\GuardDriverRegistry;
use Marko\Authentication\Hashing\BcryptPasswordHasher;
use Marko\Authentication\Hashing\HashManagerPasswordHasher;
use Marko\Authentication\Hashing\PasswordVerifier;
use Marko\Authentication\Http\IntendedUrl;
use Marko\Authentication\PasswordConfirmation\PasswordConfirmation;
use Marko\Authentication\PasswordReset\CachePasswordResetTokenRepository;
use Marko\Authentication\PasswordReset\PasswordResetBroker;
use Marko\Authentication\Throttle\NullLoginThrottle;
use Marko\Authentication\Token\RememberTokenManager;
use Marko\Config\ConfigRepositoryInterface;
use Marko\Core\Container\Container;
use Marko\Core\Container\ContainerInterface;
use Marko\Core\Event\EventDispatcherInterface;
use Marko\Core\Module\ModuleManifest;
use Marko\Core\Module\ModuleRepository;
use Marko\Core\Module\ModuleRepositoryInterface;
use Marko\Session\Contracts\SessionInterface;
use Marko\Testing\Fake\FakeAuthenticatable;
use Marko\Testing\Fake\FakeClock;
use Marko\Testing\Fake\FakeConfigRepository;
use Marko\Testing\Fake\FakeCookieJar;
use Marko\Testing\Fake\FakeEventDispatcher;
use Marko\Testing\Fake\FakeSession;
use Marko\Testing\Fake\FakeUserProvider;
use Psr\Clock\ClockInterface;

it('has enabled set to true', function () {
    $modulePath = dirname(__DIR__, 2) . '/module.php';

    expect(file_exists($modulePath))->toBeTrue();

    $config = require $modulePath;

    expect($config)->toBeArray();
});

it('has bindings array', function () {
    $modulePath = dirname(__DIR__, 2) . '/module.php';
    $config = require $modulePath;

    expect($config)->toHaveKey('bindings')
        ->and($config['bindings'])->toBeArray();
});

it('binds PasswordHasherInterface with a factory', function () {
    $modulePath = dirname(__DIR__, 2) . '/module.php';
    $config = require $modulePath;

    expect($config['bindings'])->toHaveKey(PasswordHasherInterface::class)
        ->and($config['bindings'][PasswordHasherInterface::class])->toBeInstanceOf(Closure::class);
});

it('binds GuardInterface with factory', function () {
    $modulePath = dirname(__DIR__, 2) . '/module.php';
    $config = require $modulePath;

    expect($config['bindings'])->toHaveKey(GuardInterface::class)
        ->and($config['bindings'][GuardInterface::class])->toBeInstanceOf(Closure::class);
});

it('creates password hasher with config cost', function () {
    $modulePath = dirname(__DIR__, 2) . '/module.php';
    $config = require $modulePath;
    $binding = $config['bindings'][PasswordHasherInterface::class];

    $authConfig = $this->createMock(AuthConfig::class);
    $authConfig->expects($this->once())
        ->method('bcryptCost')
        ->willReturn(10);

    $container = $this->createMock(ContainerInterface::class);
    $container->expects($this->once())
        ->method('get')
        ->with(AuthConfig::class)
        ->willReturn($authConfig);

    $result = $binding($container);

    expect($result)->toBeInstanceOf(BcryptPasswordHasher::class)
        ->and($result)->toBeInstanceOf(PasswordHasherInterface::class);
});

it('creates the bcrypt password hasher when marko/hashing is not loaded', function () {
    $config = require dirname(__DIR__, 2) . '/module.php';
    $binding = $config['bindings'][PasswordHasherInterface::class];

    $authConfig = $this->createStub(AuthConfig::class);
    $authConfig->method('bcryptCost')->willReturn(10);
    $modules = new ModuleRepository([new ModuleManifest(name: 'marko/session', version: '1.0.0')]);

    $container = $this->createStub(ContainerInterface::class);
    $container->method('has')->willReturn(true);
    $container->method('get')->willReturnCallback(
        fn (string $id): object => match ($id) {
            ModuleRepositoryInterface::class => $modules,
            AuthConfig::class => $authConfig,
        },
    );

    expect($binding($container))->toBeInstanceOf(BcryptPasswordHasher::class);
});

it('delegates password hashing to marko/hashing when that module is loaded', function () {
    $config = require dirname(__DIR__, 2) . '/module.php';
    $binding = $config['bindings'][PasswordHasherInterface::class];

    $hashingHasher = $this->createStub(HashManagerPasswordHasher::class);
    $modules = new ModuleRepository([
        new ModuleManifest(name: 'marko/authentication', version: '1.0.0'),
        new ModuleManifest(name: 'marko/hashing', version: '1.0.0'),
    ]);

    $container = $this->createStub(ContainerInterface::class);
    $container->method('has')->willReturn(true);
    $container->method('get')->willReturnCallback(
        fn (string $id): object => match ($id) {
            ModuleRepositoryInterface::class => $modules,
            HashManagerPasswordHasher::class => $hashingHasher,
        },
    );

    expect($binding($container))->toBe($hashingHasher);
});

it('builds RememberTokenManager with the bound clock from the module', function () {
    $config = require dirname(__DIR__, 2) . '/module.php';
    $binding = $config['bindings'][RememberTokenManager::class];

    $authConfig = $this->createStub(AuthConfig::class);
    $authConfig->method('rememberLifetime')->willReturn(60);
    $clock = new FakeClock('2026-01-01 12:00:00 UTC');

    $container = $this->createStub(ContainerInterface::class);
    $container->method('get')->willReturnCallback(
        fn (string $id): object => match ($id) {
            AuthConfig::class => $authConfig,
            ClockInterface::class => $clock,
        },
    );

    $manager = $binding($container);
    $createdAt = $clock->now();

    $clock->travel('+60 minutes');
    expect($manager->isExpired($createdAt))->toBeFalse();

    $clock->travel('+1 second');
    expect($manager->isExpired($createdAt))->toBeTrue();
});

it('creates guard via AuthManager', function () {
    $modulePath = dirname(__DIR__, 2) . '/module.php';
    $config = require $modulePath;
    $binding = $config['bindings'][GuardInterface::class];

    $guard = $this->createStub(GuardInterface::class);

    $authManager = $this->createMock(AuthManager::class);
    $authManager->expects($this->once())
        ->method('guard')
        ->willReturn($guard);

    $container = $this->createMock(ContainerInterface::class);
    $container->expects($this->once())
        ->method('get')
        ->with(AuthManager::class)
        ->willReturn($authManager);

    $result = $binding($container);

    expect($result)->toBeInstanceOf(GuardInterface::class);
});

it('registers AuthManager as singleton', function () {
    $modulePath = dirname(__DIR__, 2) . '/module.php';
    $config = require $modulePath;

    expect($config)->toHaveKey('singletons')
        ->and($config['singletons'])->toContain(AuthManager::class);
});

it('registers GuardInterface as singleton', function () {
    $modulePath = dirname(__DIR__, 2) . '/module.php';
    $config = require $modulePath;

    expect($config['singletons'])->toContain(GuardInterface::class);
});

it('registers the guard driver registry as a singleton', function () {
    $modulePath = dirname(__DIR__, 2) . '/module.php';
    $config = require $modulePath;

    expect($config['singletons'])->toContain(GuardDriverRegistry::class);
});

it('binds the password reset broker', function () {
    $config = require dirname(__DIR__, 2) . '/module.php';

    expect($config['bindings'][PasswordResetBrokerInterface::class])->toBe(PasswordResetBroker::class);
});

it('uses the cache token repository when marko/authentication-database is not loaded', function () {
    $config = require dirname(__DIR__, 2) . '/module.php';
    $binding = $config['bindings'][PasswordResetTokenRepositoryInterface::class];

    $cacheRepository = $this->createStub(CachePasswordResetTokenRepository::class);
    $modules = new ModuleRepository([new ModuleManifest(name: 'marko/authentication', version: '1.0.0')]);

    $container = $this->createStub(ContainerInterface::class);
    $container->method('has')->willReturn(true);
    $container->method('get')->willReturnCallback(
        fn (string $id): object => match ($id) {
            ModuleRepositoryInterface::class => $modules,
            CachePasswordResetTokenRepository::class => $cacheRepository,
        },
    );

    expect($binding($container))->toBe($cacheRepository);
});

it('uses the database token repository when marko/authentication-database is loaded', function () {
    $config = require dirname(__DIR__, 2) . '/module.php';
    $binding = $config['bindings'][PasswordResetTokenRepositoryInterface::class];

    $databaseRepository = $this->createStub(PasswordResetTokenRepositoryInterface::class);
    $modules = new ModuleRepository([
        new ModuleManifest(name: 'marko/authentication', version: '1.0.0'),
        new ModuleManifest(name: 'marko/authentication-database', version: '1.0.0'),
    ]);

    $container = $this->createStub(ContainerInterface::class);
    $container->method('has')->willReturn(true);
    $container->method('get')->willReturnCallback(
        fn (string $id): object => match ($id) {
            ModuleRepositoryInterface::class => $modules,
            'Marko\\Authentication\\Database\\Repository\\DatabasePasswordResetTokenRepository' => $databaseRepository,
        },
    );

    expect($binding($container))->toBe($databaseRepository);
});

it('binds the password verifier, password confirmation and intended URL', function () {
    $config = require dirname(__DIR__, 2) . '/module.php';

    expect($config['bindings'][PasswordVerifierInterface::class])->toBe(PasswordVerifier::class)
        ->and($config['bindings'][PasswordConfirmationInterface::class])->toBe(PasswordConfirmation::class)
        ->and($config['bindings'][IntendedUrlInterface::class])->toBe(IntendedUrl::class);
});

it('binds the email verifier', function () {
    $config = require dirname(__DIR__, 2) . '/module.php';

    expect($config['bindings'][EmailVerifierInterface::class])->toBe(EmailVerifier::class);
});

it(
    'ships no default UserPersisterInterface, ResetLinkNotifierInterface or VerificationNotifierInterface binding',
    function () {
        $config = require dirname(__DIR__, 2) . '/module.php';

        expect($config['bindings'])->not->toHaveKey(UserPersisterInterface::class)
            ->and($config['bindings'])->not->toHaveKey(ResetLinkNotifierInterface::class)
            ->and($config['bindings'])->not->toHaveKey(VerificationNotifierInterface::class);
    },
);

it('builds AuthManager with a LoginChallengeResolver so guards get their configured challenges', function () {
    $config = require dirname(__DIR__, 2) . '/module.php';
    $session = new FakeSession();
    $session->start();

    $container = new Container();
    $container->instance(ContainerInterface::class, $container);
    $container->instance(ConfigRepositoryInterface::class, new FakeConfigRepository([
        'authentication.default.guard' => 'web',
        'authentication.remember.cookie.prefix' => 'remember_',
        'authentication.guards' => [
            'web' => ['driver' => 'session', 'challenges' => [ModuleBindingsTestChallenge::class]],
        ],
    ]));
    $container->instance(SessionInterface::class, $session);
    $container->instance(UserProviderInterface::class, new FakeUserProvider([1 => new FakeAuthenticatable(id: 1)]));
    $container->instance(EventDispatcherInterface::class, new FakeEventDispatcher());
    $container->instance(CookieJarInterface::class, new FakeCookieJar());
    $container->instance(RememberTokenManager::class, new RememberTokenManager(new FakeClock()));
    $container->instance(LoginThrottleInterface::class, new NullLoginThrottle());

    $guard = $config['bindings'][AuthManager::class]($container)->guard('web');

    expect($guard->attempt(['email' => 'ada@example.com']))->toBeFalse()
        ->and($guard->pendingChallengeName())->toBe('module_test');
});

class ModuleBindingsTestChallenge implements LoginChallengeInterface
{
    public function requiresChallenge(
        AuthenticatableInterface $user,
    ): bool {
        return true;
    }

    public function getName(): string
    {
        return 'module_test';
    }
}
