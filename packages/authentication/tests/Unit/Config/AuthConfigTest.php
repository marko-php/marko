<?php

declare(strict_types=1);

use Marko\Authentication\Config\AuthConfig;
use Marko\Testing\Fake\FakeConfigRepository;

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
        'expiration' => 43200,
        'cookie' => 'remember_token',
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
