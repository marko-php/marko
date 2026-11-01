<?php

declare(strict_types=1);

use Marko\Authentication\AuthManager;
use Marko\Authentication\Config\AuthConfig;
use Marko\Authentication\Contracts\CookieJarInterface;
use Marko\Authentication\Contracts\EmailVerifierInterface;
use Marko\Authentication\Contracts\GuardInterface;
use Marko\Authentication\Contracts\IntendedUrlInterface;
use Marko\Authentication\Contracts\LoginThrottleInterface;
use Marko\Authentication\Contracts\PasswordConfirmationInterface;
use Marko\Authentication\Contracts\PasswordHasherInterface;
use Marko\Authentication\Contracts\PasswordResetBrokerInterface;
use Marko\Authentication\Contracts\PasswordResetTokenRepositoryInterface;
use Marko\Authentication\Contracts\PasswordVerifierInterface;
use Marko\Authentication\Contracts\RememberTokenStorageInterface;
use Marko\Authentication\Cookie\RequestCookieJar;
use Marko\Authentication\Database\Repository\DatabasePasswordResetTokenRepository;
use Marko\Authentication\EmailVerification\EmailVerifier;
use Marko\Authentication\Guard\GuardDriverRegistry;
use Marko\Authentication\Hashing\BcryptPasswordHasher;
use Marko\Authentication\Hashing\HashManagerPasswordHasher;
use Marko\Authentication\Hashing\PasswordVerifier;
use Marko\Authentication\Http\CurrentRequest;
use Marko\Authentication\Http\IntendedUrl;
use Marko\Authentication\LoginChallengeResolver;
use Marko\Authentication\Middleware\QueuedCookiesMiddleware;
use Marko\Authentication\PasswordConfirmation\PasswordConfirmation;
use Marko\Authentication\PasswordReset\CachePasswordResetTokenRepository;
use Marko\Authentication\PasswordReset\PasswordResetBroker;
use Marko\Authentication\Throttle\LoginThrottle;
use Marko\Authentication\Throttle\NullLoginThrottle;
use Marko\Authentication\Token\RememberTokenManager;
use Marko\Authentication\UserPersisterResolver;
use Marko\Authentication\UserProviderResolver;
use Marko\Cache\Contracts\CacheInterface;
use Marko\Core\Container\ContainerInterface;
use Marko\Core\Event\EventDispatcherInterface;
use Marko\Core\Module\ModuleManifest;
use Marko\Core\Module\ModuleRepositoryInterface;
use Marko\RateLimiter\Contracts\RateLimitKeyResolverInterface;
use Marko\Session\Contracts\SessionInterface;
use Psr\Clock\ClockInterface;

// Whether a module is installed, for defaults a sibling package replaces when present.
$moduleLoaded = static fn (ContainerInterface $container, string $name): bool => $container->has(
    ModuleRepositoryInterface::class,
) && array_any(
    $container->get(ModuleRepositoryInterface::class)->all(),
    fn (ModuleManifest $module): bool => $module->name === $name,
);

return [
    // Load after the session drivers so QueuedCookiesMiddleware runs inside SessionMiddleware.
    'sequence' => [
        'after' => ['marko/session-file', 'marko/session-database'],
    ],
    'bindings' => [
        PasswordHasherInterface::class => function (ContainerInterface $container) use ($moduleLoaded): PasswordHasherInterface {
            // With marko/hashing loaded, its driver config (HASH_DRIVER) governs login passwords too
            if ($moduleLoaded($container, 'marko/hashing')) {
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
                userPersisterResolver: $container->get(UserPersisterResolver::class),
                loginChallengeResolver: $container->get(LoginChallengeResolver::class),
            );
        },
        // Apps bind UserPersisterInterface, ResetLinkNotifierInterface and VerificationNotifierInterface themselves:
        // only they know how to save their user entity and what the emails say.
        EmailVerifierInterface::class => EmailVerifier::class,
        PasswordResetBrokerInterface::class => PasswordResetBroker::class,
        // Reset tokens live in the cache unless marko/authentication-database is installed. Its repository is
        // chosen here, not bound in its own module.php, because two vendor modules binding one interface is a
        // binding conflict. An app module binding the interface still overrides both.
        PasswordResetTokenRepositoryInterface::class => function (
            ContainerInterface $container,
        ) use ($moduleLoaded): PasswordResetTokenRepositoryInterface {
            if ($moduleLoaded($container, 'marko/authentication-database')) {
                return $container->get(DatabasePasswordResetTokenRepository::class);
            }

            return $container->get(CachePasswordResetTokenRepository::class);
        },
        PasswordVerifierInterface::class => PasswordVerifier::class,
        PasswordConfirmationInterface::class => PasswordConfirmation::class,
        IntendedUrlInterface::class => IntendedUrl::class,
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
