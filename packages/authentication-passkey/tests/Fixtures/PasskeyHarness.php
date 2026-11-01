<?php

declare(strict_types=1);

namespace Marko\AuthenticationPasskey\Tests\Fixtures;

use Marko\Authentication\AuthManager;
use Marko\Authentication\Config\AuthConfig;
use Marko\Authentication\Contracts\GuardInterface;
use Marko\Authentication\Contracts\UserProviderInterface;
use Marko\Authentication\Event\LoginEvent;
use Marko\Authentication\Hashing\BcryptPasswordHasher;
use Marko\Authentication\Hashing\PasswordVerifier;
use Marko\Authentication\Http\CurrentRequest;
use Marko\Authentication\LoginChallengeResolver;
use Marko\Authentication\Observer\ForgetPasswordConfirmationOnLogin;
use Marko\Authentication\PasswordConfirmation\PasswordConfirmation;
use Marko\Authentication\Token\RememberTokenManager;
use Marko\Authentication\UserProviderResolver;
use Marko\AuthenticationPasskey\Ceremony\Base64Url;
use Marko\AuthenticationPasskey\Ceremony\ClientDataVerifier;
use Marko\AuthenticationPasskey\Ceremony\PasskeyChallengeStore;
use Marko\AuthenticationPasskey\Config\PasskeyConfig;
use Marko\AuthenticationPasskey\Contracts\PasskeyVerifierInterface;
use Marko\AuthenticationPasskey\Entity\PasskeyCredential;
use Marko\AuthenticationPasskey\Guard\PasskeyGuard;
use Marko\AuthenticationPasskey\Repository\PasskeyCredentialRepository;
use Marko\AuthenticationPasskey\Service\PasskeyAuthentication;
use Marko\AuthenticationPasskey\Service\PasskeyManager;
use Marko\AuthenticationPasskey\Service\PasskeyRegistration;
use Marko\AuthenticationPasskey\Verifier\LbuchsWebAuthnVerifier;
use Marko\Cache\CacheOperations;
use Marko\Cache\Config\CacheConfig;
use Marko\Cache\Memory\Driver\ArrayCacheDriver;
use Marko\Core\Container\Container;
use Marko\Core\Event\Event;
use Marko\Database\Entity\EntityHydrator;
use Marko\Database\Entity\EntityMetadataFactory;
use Marko\RateLimiter\Contracts\RateLimitKeyResolverInterface;
use Marko\RateLimiter\RateLimiter;
use Marko\Routing\Http\Request;
use Marko\Testing\Fake\FakeAuthenticatable;
use Marko\Testing\Fake\FakeClock;
use Marko\Testing\Fake\FakeConfigRepository;
use Marko\Testing\Fake\FakeCookieJar;
use Marko\Testing\Fake\FakeEventDispatcher;
use Marko\Testing\Fake\FakeSession;
use Marko\Testing\Fake\FakeUserProvider;

/**
 * The whole passkey package wired the way an app wires it, with the real
 * SessionGuard, AuthManager, PasswordConfirmation (and its LoginEvent
 * observer), rate limiter, lbuchs verifier and SQLite-backed repository,
 * around a fake clock, session and user provider.
 *
 * Users 7 (Ada) and 8 (Grace) exist in the 'users' provider.
 */
class PasskeyHarness
{
    public const array PASSKEY_CONFIG = [
        'authentication-passkey.relying_party.id' => 'example.com',
        'authentication-passkey.relying_party.name' => 'Example',
        'authentication-passkey.allowed_origins' => ['https://example.com'],
        'authentication-passkey.guard' => 'session',
        'authentication-passkey.timeout_seconds' => 300,
        'authentication-passkey.throttle.max_attempts' => 5,
        'authentication-passkey.throttle.decay_seconds' => 60,
    ];

    public FakeClock $clock;

    public FakeSession $session;

    public FakeEventDispatcher $eventDispatcher;

    public FakeAuthenticatable $ada;

    public FakeAuthenticatable $grace;

    public SqlitePasskeyConnection $connection;

    public PasskeyCredentialRepository $repository;

    public CurrentRequest $currentRequest;

    public AuthManager $authManager;

    public FakeConfigRepository $configRepository;

    public PasskeyVerifierInterface $verifier;

    private RateLimiter $rateLimiter;

    private PasswordConfirmation $passwordConfirmation;

    /**
     * @param array<string, mixed> $config Overrides for PASSKEY_CONFIG and the authentication config; null removes a key
     */
    public function __construct(
        array $config = [],
    ) {
        $this->clock = new FakeClock('2026-06-01 12:00:00');
        $this->session = new FakeSession();
        $this->session->start();
        $this->ada = new FakeAuthenticatable(id: 7);
        $this->grace = new FakeAuthenticatable(id: 8);
        // A null override removes the key, as if the app's config left it out.
        $this->configRepository = new FakeConfigRepository(array_filter([
            'authentication.default.guard' => 'session',
            'authentication.default.provider' => 'users',
            'authentication.guards' => [
                'session' => ['driver' => 'session', 'provider' => 'users'],
            ],
            'authentication.providers' => ['users' => []],
            'authentication.remember.cookie.prefix' => 'remember_',
            'authentication.password_confirmation.timeout_seconds' => 10800,
            'authentication.password_confirmation.route' => '/confirm-password',
            'authentication.password_confirmation.throttle.max_attempts' => 5,
            'authentication.password_confirmation.throttle.decay_seconds' => 60,
            ...self::PASSKEY_CONFIG,
            ...$config,
        ], static fn (mixed $value): bool => $value !== null));

        $session = $this->session;
        $this->eventDispatcher = new class ($session) extends FakeEventDispatcher
        {
            public function __construct(
                private readonly FakeSession $session,
            ) {}

            public function dispatch(
                Event $event,
            ): void {
                parent::dispatch($event);

                if ($event instanceof LoginEvent) {
                    new ForgetPasswordConfirmationOnLogin($this->session)->handle($event);
                }
            }
        };

        $container = new Container();
        $container->instance(UserProviderInterface::class, new FakeUserProvider([7 => $this->ada, 8 => $this->grace]));
        $authConfig = new AuthConfig($this->configRepository);

        $this->currentRequest = new CurrentRequest();
        $this->currentRequest->set(new Request(server: ['REMOTE_ADDR' => '203.0.113.9']));

        $this->authManager = new AuthManager(
            config: $authConfig,
            session: $this->session,
            providerResolver: new UserProviderResolver($authConfig, $container),
            eventDispatcher: $this->eventDispatcher,
            cookieJar: new FakeCookieJar(),
            rememberTokenManager: new RememberTokenManager($this->clock),
            currentRequest: $this->currentRequest,
            loginChallengeResolver: new LoginChallengeResolver($container),
        );

        $metadataFactory = new EntityMetadataFactory();
        $this->connection = new SqlitePasskeyConnection();
        $this->repository = new PasskeyCredentialRepository(
            connection: $this->connection,
            metadataFactory: $metadataFactory,
            hydrator: new EntityHydrator($metadataFactory),
            clock: $this->clock,
        );

        $cache = new ArrayCacheDriver(new CacheConfig(new FakeConfigRepository([
            'cache.path' => sys_get_temp_dir(),
            'cache.default_ttl' => 3600,
            'cache.driver' => 'array',
        ])), $this->clock, new CacheOperations());
        $this->rateLimiter = new RateLimiter($cache, $this->clock);
        $this->passwordConfirmation = new PasswordConfirmation(
            passwordVerifier: new PasswordVerifier(new BcryptPasswordHasher(cost: 4)),
            session: $this->session,
            clock: $this->clock,
            rateLimiter: $this->rateLimiter,
            authConfig: $authConfig,
        );
        $this->verifier = new LbuchsWebAuthnVerifier($this->config());
    }

    public function config(): PasskeyConfig
    {
        return new PasskeyConfig($this->configRepository);
    }

    public function guard(): GuardInterface
    {
        return $this->authManager->guard('session');
    }

    public function passkeyGuard(): PasskeyGuard
    {
        $authConfig = new AuthConfig($this->configRepository);
        $container = new Container();
        $container->instance(UserProviderInterface::class, new FakeUserProvider([7 => $this->ada, 8 => $this->grace]));

        return new PasskeyGuard(
            authManager: $this->authManager,
            authConfig: $authConfig,
            userProviderResolver: new UserProviderResolver($authConfig, $container),
            config: $this->config(),
        );
    }

    public function challengeStore(): PasskeyChallengeStore
    {
        return new PasskeyChallengeStore($this->session, $this->clock, $this->config());
    }

    public function registration(): PasskeyRegistration
    {
        return new PasskeyRegistration(
            passkeyGuard: $this->passkeyGuard(),
            passkeyVerifier: $this->verifier,
            passkeyCredentialRepository: $this->repository,
            passkeyChallengeStore: $this->challengeStore(),
            clientDataVerifier: new ClientDataVerifier($this->config()),
            eventDispatcher: $this->eventDispatcher,
            clock: $this->clock,
        );
    }

    public function authentication(): PasskeyAuthentication
    {
        $currentRequest = $this->currentRequest;

        return new PasskeyAuthentication(
            passkeyGuard: $this->passkeyGuard(),
            passkeyVerifier: $this->verifier,
            passkeyCredentialRepository: $this->repository,
            passkeyChallengeStore: $this->challengeStore(),
            clientDataVerifier: new ClientDataVerifier($this->config()),
            passwordConfirmation: $this->passwordConfirmation,
            rateLimiter: $this->rateLimiter,
            rateLimitKeyResolver: new class () implements RateLimitKeyResolverInterface
            {
                public function resolve(
                    Request $request,
                ): string {
                    return (string) $request->ip();
                }
            },
            currentRequest: $currentRequest,
            eventDispatcher: $this->eventDispatcher,
            config: $this->config(),
            clock: $this->clock,
        );
    }

    public function manager(): PasskeyManager
    {
        return new PasskeyManager(
            passkeyGuard: $this->passkeyGuard(),
            passkeyCredentialRepository: $this->repository,
            passwordConfirmation: $this->passwordConfirmation,
            eventDispatcher: $this->eventDispatcher,
        );
    }

    public function passwordConfirmation(): PasswordConfirmation
    {
        return $this->passwordConfirmation;
    }

    /**
     * Register a passkey on $authenticator for $user through the real
     * ceremony, then sign the user out again.
     */
    public function registerPasskey(
        SoftwareAuthenticator $authenticator,
        FakeAuthenticatable $user,
        string $name = 'Laptop',
    ): PasskeyCredential {
        $this->guard()->login($user);
        $options = $this->registration()->creationOptions(
            'user' . $user->getAuthIdentifier() . '@example.com',
            'User',
        );
        $passkey = $this->registration()->register(
            $authenticator->register(self::challengeOf($options)),
            $name,
        );
        $this->guard()->logout();
        $this->eventDispatcher->clear();

        return $passkey;
    }

    /**
     * The challenge bytes of creation or request options.
     *
     * @param array<string, mixed> $options
     */
    public static function challengeOf(
        array $options,
    ): string {
        return (string) Base64Url::decode((string) $options['challenge']);
    }
}
