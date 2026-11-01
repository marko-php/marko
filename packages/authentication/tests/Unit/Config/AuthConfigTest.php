<?php

declare(strict_types=1);

use Marko\Authentication\Config\AuthConfig;
use Marko\Authentication\Exceptions\AuthException;
use Marko\Testing\Fake\FakeConfigRepository;

describe('password reset', function (): void {
    it('loads the password reset provider, expiry and throttle', function (): void {
        $config = new AuthConfig(new FakeConfigRepository([
            'authentication.password_reset.provider' => 'customers',
            'authentication.password_reset.expire_seconds' => 900,
            'authentication.password_reset.throttle_seconds' => 30,
        ]));

        expect($config->passwordResetProvider())->toBe('customers')
            ->and($config->passwordResetExpireSeconds())->toBe(900)
            ->and($config->passwordResetThrottleSeconds())->toBe(30);
    });

    it('rejects a password reset expiry or throttle below one second', function (string $key, string $method): void {
        $config = new AuthConfig(new FakeConfigRepository([
            "authentication.password_reset.$key" => 0,
        ]));

        expect(fn () => $config->$method())
            ->toThrow(AuthException::class, "authentication.password_reset.$key must be a positive integer, got 0");
    })->with([
        ['expire_seconds', 'passwordResetExpireSeconds'],
        ['throttle_seconds', 'passwordResetThrottleSeconds'],
    ]);

    it('ships password reset defaults in the package config file', function (): void {
        $defaults = require dirname(__DIR__, 3) . '/config/authentication.php';

        expect($defaults['password_reset'])->toBe([
            'provider' => 'users',
            'expire_seconds' => 3600,
            'throttle_seconds' => 60,
        ]);
    });
});

describe('password confirmation', function (): void {
    it('loads the password confirmation timeout, route and throttle', function (): void {
        $config = new AuthConfig(new FakeConfigRepository([
            'authentication.password_confirmation.timeout_seconds' => 900,
            'authentication.password_confirmation.route' => '/user/confirm-password',
            'authentication.password_confirmation.throttle.max_attempts' => 3,
            'authentication.password_confirmation.throttle.decay_seconds' => 120,
        ]));

        expect($config->passwordConfirmationTimeoutSeconds())->toBe(900)
            ->and($config->passwordConfirmationRoute())->toBe('/user/confirm-password')
            ->and($config->passwordConfirmationThrottleMaxAttempts())->toBe(3)
            ->and($config->passwordConfirmationThrottleDecaySeconds())->toBe(120);
    });

    it('rejects a password confirmation number below one', function (string $key, string $method): void {
        $config = new AuthConfig(new FakeConfigRepository([
            "authentication.password_confirmation.$key" => 0,
        ]));

        expect(fn () => $config->$method())
            ->toThrow(
                AuthException::class,
                "authentication.password_confirmation.$key must be a positive integer, got 0",
            );
    })->with([
        ['timeout_seconds', 'passwordConfirmationTimeoutSeconds'],
        ['throttle.max_attempts', 'passwordConfirmationThrottleMaxAttempts'],
        ['throttle.decay_seconds', 'passwordConfirmationThrottleDecaySeconds'],
    ]);

    it('ships password confirmation defaults in the package config file', function (): void {
        $defaults = require dirname(__DIR__, 3) . '/config/authentication.php';

        expect($defaults['password_confirmation'])->toBe([
            'timeout_seconds' => 10800,
            'route' => '/confirm-password',
            'throttle' => [
                'max_attempts' => 5,
                'decay_seconds' => 60,
            ],
        ]);
    });
});

it('creates AuthConfig class', function () {
    $config = new AuthConfig(new FakeConfigRepository());

    expect($config)->toBeInstanceOf(AuthConfig::class);
});

it('loads default guard name', function () {
    $config = new AuthConfig(new FakeConfigRepository([
        'authentication.default.guard' => 'session',
    ]));

    expect($config->defaultGuard())->toBe('session');
});

it('loads default provider name', function () {
    $config = new AuthConfig(new FakeConfigRepository([
        'authentication.default.provider' => 'users',
    ]));

    expect($config->defaultProvider())->toBe('users');
});

it('loads guards configuration array', function () {
    $guardsConfig = [
        'session' => ['driver' => 'session', 'provider' => 'users'],
        'token' => ['driver' => 'token', 'provider' => 'users'],
    ];
    $config = new AuthConfig(new FakeConfigRepository([
        'authentication.guards' => $guardsConfig,
    ]));

    expect($config->guards())->toBe($guardsConfig);
});

it('loads providers configuration array', function () {
    $providersConfig = [
        'users' => ['driver' => 'eloquent', 'model' => 'App\\User'],
        'admins' => ['driver' => 'database', 'table' => 'admins'],
    ];
    $config = new AuthConfig(new FakeConfigRepository([
        'authentication.providers' => $providersConfig,
    ]));

    expect($config->providers())->toBe($providersConfig);
});

it('loads password hasher settings', function () {
    $passwordConfig = [
        'driver' => 'bcrypt',
        'bcrypt' => ['cost' => 12],
    ];
    $config = new AuthConfig(new FakeConfigRepository([
        'authentication.password' => $passwordConfig,
    ]));

    expect($config->passwordConfig())->toBe($passwordConfig);
});

it('loads remember token settings', function () {
    $rememberConfig = [
        'lifetime' => 43200,
        'cookie' => ['prefix' => 'remember_'],
    ];
    $config = new AuthConfig(new FakeConfigRepository([
        'authentication.remember' => $rememberConfig,
    ]));

    expect($config->rememberConfig())->toBe($rememberConfig);
});

it('provides getter for bcrypt cost', function () {
    $config = new AuthConfig(new FakeConfigRepository([
        'authentication.password.bcrypt.cost' => 14,
    ]));

    expect($config->bcryptCost())->toBe(14);
});

it('reads default guard from config without fallback', function () {
    $config = new AuthConfig(new FakeConfigRepository([
        'authentication.default.guard' => 'token',
    ]));

    expect($config->defaultGuard())->toBe('token');
});

it('reads default provider from config without fallback', function () {
    $config = new AuthConfig(new FakeConfigRepository([
        'authentication.default.provider' => 'admins',
    ]));

    expect($config->defaultProvider())->toBe('admins');
});

it('reads bcrypt cost from config without fallback', function () {
    $config = new AuthConfig(new FakeConfigRepository([
        'authentication.password.bcrypt.cost' => 10,
    ]));

    expect($config->bcryptCost())->toBe(10);
});

it('config file contains all required keys with defaults', function () {
    $configPath = dirname(__DIR__, 3) . '/config/authentication.php';
    $config = require $configPath;

    expect(file_exists($configPath))->toBeTrue()
        ->and($config)->toBeArray()
        ->and($config)->toHaveKey('default')
        ->and($config['default'])->toHaveKey('guard')
        ->and($config['default'])->toHaveKey('provider')
        ->and($config)->toHaveKey('password')
        ->and($config['password'])->toHaveKey('bcrypt')
        ->and($config['password']['bcrypt'])->toHaveKey('cost');
});

describe('remember cookie', function (): void {
    it('returns remember lifetime in minutes from config', function (): void {
        $config = new AuthConfig(new FakeConfigRepository([
            'authentication.remember.lifetime' => 1440,
        ]));

        expect($config->rememberLifetime())->toBe(1440);
    });

    it('returns remember cookie prefix from config', function (): void {
        $config = new AuthConfig(new FakeConfigRepository([
            'authentication.remember.cookie.prefix' => 'keep_',
        ]));

        expect($config->rememberCookiePrefix())->toBe('keep_');
    });

    it('returns remember cookie path, domain, http only and same site from config', function (): void {
        $config = new AuthConfig(new FakeConfigRepository([
            'authentication.remember.cookie.path' => '/app',
            'authentication.remember.cookie.domain' => 'example.com',
            'authentication.remember.cookie.http_only' => false,
            'authentication.remember.cookie.same_site' => 'Strict',
        ]));

        expect($config->rememberCookiePath())->toBe('/app')
            ->and($config->rememberCookieDomain())->toBe('example.com')
            ->and($config->rememberCookieHttpOnly())->toBeFalse()
            ->and($config->rememberCookieSameSite())->toBe('Strict');
    });

    it('returns null remember cookie domain when configured as empty string', function (): void {
        $config = new AuthConfig(new FakeConfigRepository([
            'authentication.remember.cookie.domain' => '',
        ]));

        expect($config->rememberCookieDomain())->toBeNull();
    });

    it('follows session cookie secure flag when remember cookie secure is null', function (): void {
        $config = new AuthConfig(new FakeConfigRepository([
            'authentication.remember.cookie.secure' => null,
            'session.cookie.secure' => false,
        ]));

        expect($config->rememberCookieSecure())->toBeFalse();
    });

    it('uses explicit remember cookie secure flag when configured', function (): void {
        $config = new AuthConfig(new FakeConfigRepository([
            'authentication.remember.cookie.secure' => true,
            'session.cookie.secure' => false,
        ]));

        expect($config->rememberCookieSecure())->toBeTrue();
    });

    it('ships remember defaults in the package config file', function (): void {
        $defaults = require dirname(__DIR__, 3) . '/config/authentication.php';

        expect($defaults['remember'])->toBe([
            'lifetime' => 43200,
            'cookie' => [
                'prefix' => 'remember_',
                'path' => '/',
                'domain' => '',
                'secure' => null,
                'http_only' => true,
                'same_site' => 'Lax',
            ],
        ]);
    });
});
