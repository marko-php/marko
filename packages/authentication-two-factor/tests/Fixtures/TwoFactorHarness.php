<?php

declare(strict_types=1);

namespace Marko\AuthenticationTwoFactor\Tests\Fixtures;

use DateTimeImmutable;
use Marko\Authentication\AuthManager;
use Marko\Authentication\Config\AuthConfig;
use Marko\Authentication\Contracts\UserPersisterInterface;
use Marko\Authentication\Contracts\UserProviderInterface;
use Marko\Authentication\LoginChallengeResolver;
use Marko\Authentication\Token\RememberTokenManager;
use Marko\Authentication\UserPersisterResolver;
use Marko\Authentication\UserProviderResolver;
use Marko\AuthenticationTwoFactor\Config\TwoFactorConfig;
use Marko\AuthenticationTwoFactor\Contracts\TotpInterface;
use Marko\AuthenticationTwoFactor\Contracts\TwoFactorManagerInterface;
use Marko\AuthenticationTwoFactor\Totp\OtphpTotp;
use Marko\AuthenticationTwoFactor\TwoFactorChallenge;
use Marko\AuthenticationTwoFactor\TwoFactorLoginChallenge;
use Marko\AuthenticationTwoFactor\TwoFactorManager;
use Marko\Cache\CacheOperations;
use Marko\Cache\Config\CacheConfig;
use Marko\Cache\Memory\Driver\ArrayCacheDriver;
use Marko\Core\Container\Container;
use Marko\RateLimiter\RateLimiter;
use Marko\Testing\Fake\FakeClock;
use Marko\Testing\Fake\FakeConfigRepository;
use Marko\Testing\Fake\FakeCookieJar;
use Marko\Testing\Fake\FakeEventDispatcher;
use Marko\Testing\Fake\FakeSession;
use Marko\Testing\Fake\FakeUserProvider;
use OTPHP\TOTP;

/**
 * Everything a two-factor test needs, wired to in-memory collaborators: a
 * 'web' session guard that lists TwoFactorLoginChallenge, its 'users'
 * provider and persister, and a rate limiter on the array cache.
 */
class TwoFactorHarness
{
    public FakeClock $clock;

    public FakeSession $session;

    public FakeEventDispatcher $events;

    public RecordingUserPersister $persister;

    public TwoFactorUser $user;

    public Container $container;

    public AuthConfig $authConfig;

    private ?AuthManager $authManager = null;

    private ?TwoFactorManager $manager = null;

    /**
     * @param array<string, mixed> $config
     */
    public function __construct(
        private readonly array $config = [],
    ) {
        $this->clock = new FakeClock(new DateTimeImmutable('@1700000000'));
        $this->session = new FakeSession();
        $this->session->start();
        $this->events = new FakeEventDispatcher();
        $this->persister = new RecordingUserPersister();
        $this->user = new TwoFactorUser(id: 7);
        $this->container = new Container();
        $this->container->instance(UserProviderInterface::class, new FakeUserProvider([7 => $this->user]));
        $this->container->instance(UserPersisterInterface::class, $this->persister);
        $this->authConfig = new AuthConfig(new FakeConfigRepository([
            'authentication.default.guard' => 'web',
            'authentication.default.provider' => 'users',
            'authentication.remember.cookie.prefix' => 'remember_',
            'authentication.guards' => [
                'web' => ['driver' => 'session', 'challenges' => [TwoFactorLoginChallenge::class]],
            ],
        ]));
    }

    public function twoFactorConfig(): TwoFactorConfig
    {
        return new TwoFactorConfig(new FakeConfigRepository([
            'authentication-two-factor.issuer' => 'Acme',
            'authentication-two-factor.setup_route' => '/two-factor/setup',
            'authentication-two-factor.recovery_code_count' => 8,
            'authentication-two-factor.throttle.max_attempts' => 3,
            'authentication-two-factor.throttle.decay_seconds' => 60,
            ...$this->config,
        ]));
    }

    public function totp(): TotpInterface
    {
        return new OtphpTotp($this->clock);
    }

    public function manager(): TwoFactorManager
    {
        if ($this->manager === null) {
            $this->manager = new TwoFactorManager(
                authManager: $this->authManager(),
                authConfig: $this->authConfig,
                userPersisterResolver: new UserPersisterResolver($this->authConfig, $this->container),
                totp: $this->totp(),
                twoFactorConfig: $this->twoFactorConfig(),
                eventDispatcher: $this->events,
                clock: $this->clock,
            );
        }

        return $this->manager;
    }

    public function authManager(): AuthManager
    {
        if ($this->authManager === null) {
            // Resolved lazily: TwoFactorLoginChallenge needs the manager, which needs this AuthManager
            $this->container->bind(TwoFactorManagerInterface::class, fn (): TwoFactorManager => $this->manager());
            $this->authManager = new AuthManager(
                config: $this->authConfig,
                session: $this->session,
                providerResolver: new UserProviderResolver($this->authConfig, $this->container),
                eventDispatcher: $this->events,
                cookieJar: new FakeCookieJar(),
                rememberTokenManager: new RememberTokenManager($this->clock),
                userPersisterResolver: new UserPersisterResolver($this->authConfig, $this->container),
                loginChallengeResolver: new LoginChallengeResolver($this->container),
            );
        }

        return $this->authManager;
    }

    public function challenge(): TwoFactorChallenge
    {
        $cache = new ArrayCacheDriver(new CacheConfig(new FakeConfigRepository([
            'cache.path' => sys_get_temp_dir(),
            'cache.default_ttl' => 3600,
            'cache.driver' => 'array',
        ])), $this->clock, new CacheOperations());

        return new TwoFactorChallenge(
            authManager: $this->authManager(),
            twoFactorManager: $this->manager(),
            twoFactorConfig: $this->twoFactorConfig(),
            rateLimiter: new RateLimiter($cache, $this->clock),
            eventDispatcher: $this->events,
        );
    }

    /**
     * Enable and confirm two-factor for the harness user, returning its recovery codes.
     *
     * @return list<string>
     */
    public function enableTwoFactor(): array
    {
        $setup = $this->manager()->enable($this->user, 'ada@example.com');
        $this->manager()->confirm($this->user, $this->currentCode());
        // Move past the step confirm() used so the next code is not a replay
        $this->clock->travel('+30 seconds');
        $this->persister->persisted = [];
        $this->events->clear();

        return $setup->recoveryCodes;
    }

    /**
     * The TOTP code for the harness user's secret at the current fake time.
     */
    public function currentCode(): string
    {
        return TOTP::createFromSecret((string) $this->user->getTwoFactorSecret(), $this->clock)
            ->at($this->clock->now()->getTimestamp());
    }
}
