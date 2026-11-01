<?php

declare(strict_types=1);

use Marko\Authentication\AuthManager;
use Marko\Authentication\Config\AuthConfig;
use Marko\Authentication\Contracts\CookieJarInterface;
use Marko\Authentication\Contracts\GuardInterface;
use Marko\Authentication\Contracts\LoginThrottleInterface;
use Marko\Authentication\Contracts\PasswordHasherInterface;
use Marko\Authentication\Contracts\PasswordResetBrokerInterface;
use Marko\Authentication\Contracts\PasswordResetTokenRepositoryInterface;
use Marko\Authentication\Contracts\RememberTokenStorageInterface;
use Marko\Authentication\Cookie\RequestCookieJar;
use Marko\Authentication\Guard\GuardDriverRegistry;
use Marko\Authentication\Hashing\BcryptPasswordHasher;
use Marko\Authentication\Hashing\HashManagerPasswordHasher;
use Marko\Authentication\Http\CurrentRequest;
use Marko\Authentication\Middleware\QueuedCookiesMiddleware;
use Marko\Authentication\PasswordReset\CachePasswordResetTokenRepository;
use Marko\Authentication\PasswordReset\PasswordResetBroker;
use Marko\Authentication\Throttle\LoginThrottle;
use Marko\Authentication\Throttle\NullLoginThrottle;
use Marko\Authentication\Token\RememberTokenManager;
use Marko\Authentication\UserProviderResolver;
use Marko\Cache\Contracts\CacheInterface;
use Marko\Core\Container\ContainerInterface;
use Marko\Core\Event\EventDispatcherInterface;
use Marko\Core\Module\ModuleManifest;
use Marko\Core\Module\ModuleRepositoryInterface;
use Marko\RateLimiter\Contracts\RateLimitKeyResolverInterface;
use Marko\Session\Contracts\SessionInterface;
use Psr\Clock\ClockInterface;

return [
    // Load after the session drivers so QueuedCookiesMiddleware runs inside SessionMiddleware.
    'sequence' => [
        'after' => ['marko/session-file', 'marko/session-database'],
    ],
    'bindings' => [
        PasswordHasherInterface::class => function (ContainerInterface $container): PasswordHasherInterface {
            // With marko/hashing loaded, its driver config (HASH_DRIVER) governs login passwords too
            if (
                $container->has(ModuleRepositoryInterface::class)
                && array_any(
                    $container->get(ModuleRepositoryInterface::class)->all(),
                    fn (ModuleManifest $module): bool => $module->name === 'marko/hashing',
                )
            ) {
                return $container->get(HashManagerPasswordHasher::class);
            }

            $config = $container->get(AuthConfig::class);

            return new BcryptPasswordHasher(
                cost: $config->bcryptCost(),
            );
        },
        RememberTokenManager::class => function (ContainerInterface $container): RememberTokenManager {
            return new RememberTokenManager(
                clock: $container->get(ClockInterface::class),
                lifetimeMinutes: $container->get(AuthConfig::class)->rememberLifetime(),
            );
        },
        // The guard and QueuedCookiesMiddleware must share one jar so queued cookies reach the response.
        CookieJarInterface::class => function (ContainerInterface $container): CookieJarInterface {
            return $container->get(RequestCookieJar::class);
        },
        // Per-device remember-me tokens are used when a module (e.g. marko/admin-auth) binds
        // RememberTokenStorageInterface; without one, session guards fall back to the user
        // provider's single remember_token column.
        AuthManager::class => function (ContainerInterface $container): AuthManager {
            return new AuthManager(
                config: $container->get(AuthConfig::class),
                session: $container->get(SessionInterface::class),
                providerResolver: $container->get(UserProviderResolver::class),
                eventDispatcher: $container->get(EventDispatcherInterface::class),
                cookieJar: $container->get(CookieJarInterface::class),
                rememberTokenManager: $container->get(RememberTokenManager::class),
                guardDriverRegistry: $container->get(GuardDriverRegistry::class),
                loginThrottle: $container->get(LoginThrottleInterface::class),
                rememberTokenStorage: $container->has(RememberTokenStorageInterface::class)
                    ? $container->get(RememberTokenStorageInterface::class)
                    : null,
                currentRequest: $container->get(CurrentRequest::class),
            );
        },
        // Apps bind UserPersisterInterface and ResetLinkNotifierInterface themselves:
        // only they know how to save their user entity and what the reset email says.
        PasswordResetBrokerInterface::class => PasswordResetBroker::class,
        PasswordResetTokenRepositoryInterface::class => CachePasswordResetTokenRepository::class,
        GuardInterface::class => function (ContainerInterface $container): GuardInterface {
            return $container->get(AuthManager::class)->guard();
        },
        // Counts failed logins in the cache. A missing cache driver only fails when a
        // login is attempted, so guards that never call attempt() keep working. The
        // client identity comes from marko/ratelimiter when it is installed.
        LoginThrottleInterface::class => function (ContainerInterface $container): LoginThrottleInterface {
            $config = $container->get(AuthConfig::class);

            if (!$config->throttleEnabled()) {
                return new NullLoginThrottle();
            }

            return new LoginThrottle(
                cache: $container->has(CacheInterface::class) ? $container->get(CacheInterface::class) : null,
                clock: $container->get(ClockInterface::class),
                currentRequest: $container->get(CurrentRequest::class),
                maxAttempts: $config->throttleMaxAttempts(),
                decaySeconds: $config->throttleDecaySeconds(),
                lockoutSeconds: $config->throttleLockoutSeconds(),
                maxLockoutSeconds: $config->throttleMaxLockoutSeconds(),
                rateLimitKeyResolver: $container->has(RateLimitKeyResolverInterface::class)
                    ? $container->get(RateLimitKeyResolverInterface::class)
                    : null,
            );
        },
    ],
    'singletons' => [
        AuthManager::class,
        // Drivers registered from module boot callbacks must reach the AuthManager.
        GuardDriverRegistry::class,
        GuardInterface::class,
        RequestCookieJar::class,
        CookieJarInterface::class,
        RememberTokenManager::class,
        CurrentRequest::class,
        LoginThrottleInterface::class,
    ],
    'globalMiddleware' => [
        QueuedCookiesMiddleware::class,
    ],
];
