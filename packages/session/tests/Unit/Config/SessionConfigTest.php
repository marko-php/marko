<?php

declare(strict_types=1);

use Marko\Config\ConfigRepository;
use Marko\Session\Config\SessionConfig;

it('reads driver from config without fallback', function () {
    $config = new ConfigRepository([
        'session' => [
            'driver' => 'array',
        ],
    ]);

    $sessionConfig = new SessionConfig($config);

    expect($sessionConfig->driver())->toBe('array');
});

it('reads lifetime from config without fallback', function () {
    $config = new ConfigRepository([
        'session' => [
            'lifetime' => 60,
        ],
    ]);

    $sessionConfig = new SessionConfig($config);

    expect($sessionConfig->lifetime())->toBe(60);
});

it('reads expire_on_close from config without fallback', function () {
    $config = new ConfigRepository([
        'session' => [
            'expire_on_close' => true,
        ],
    ]);

    $sessionConfig = new SessionConfig($config);

    expect($sessionConfig->expireOnClose())->toBeTrue();
});

it('reads path from config without fallback', function () {
    $config = new ConfigRepository([
        'session' => [
            'path' => '/tmp/sessions',
        ],
    ]);

    $sessionConfig = new SessionConfig($config);

    expect($sessionConfig->path())->toBe('/tmp/sessions');
});

it('reads all cookie settings from config without fallback', function () {
    $config = new ConfigRepository([
        'session' => [
            'cookie' => [
                'name' => 'test_session',
                'path' => '/app',
                'domain' => 'example.com',
                'secure' => false,
                'httponly' => false,
                'samesite' => 'strict',
            ],
        ],
    ]);

    $sessionConfig = new SessionConfig($config);

    expect($sessionConfig->cookieName())->toBe('test_session')
        ->and($sessionConfig->cookiePath())->toBe('/app')
        ->and($sessionConfig->cookieDomain())->toBe('example.com')
        ->and($sessionConfig->cookieSecure())->toBeFalse()
        ->and($sessionConfig->cookieHttpOnly())->toBeFalse()
        ->and($sessionConfig->cookieSameSite())->toBe('strict');
});

it('reads gc_probability from config without fallback', function () {
    $config = new ConfigRepository([
        'session' => [
            'gc_probability' => 5,
        ],
    ]);

    $sessionConfig = new SessionConfig($config);

    expect($sessionConfig->gcProbability())->toBe(5);
});

it('reads gc_divisor from config without fallback', function () {
    $config = new ConfigRepository([
        'session' => [
            'gc_divisor' => 200,
        ],
    ]);

    $sessionConfig = new SessionConfig($config);

    expect($sessionConfig->gcDivisor())->toBe(200);
});

it('returns null for empty cookie domain', function () {
    $config = new ConfigRepository([
        'session' => [
            'cookie' => [
                'domain' => '',
            ],
        ],
    ]);

    $sessionConfig = new SessionConfig($config);

    expect($sessionConfig->cookieDomain())->toBeNull();
});

it('config file contains all required keys with defaults', function () {
    $configPath = dirname(__DIR__, 3) . '/config/session.php';
    $configValues = require $configPath;

    // Check top-level keys
    expect($configValues)->toHaveKey('driver')
        ->and($configValues)->toHaveKey('lifetime')
        ->and($configValues)->toHaveKey('expire_on_close')
        ->and($configValues)->toHaveKey('path')
        ->and($configValues)->toHaveKey('cookie')
        ->and($configValues)->toHaveKey('gc_probability')
        ->and($configValues)->toHaveKey('gc_divisor');

    // Check cookie nested keys
    expect($configValues['cookie'])->toHaveKey('name')
        ->and($configValues['cookie'])->toHaveKey('path')
        ->and($configValues['cookie'])->toHaveKey('domain')
        ->and($configValues['cookie'])->toHaveKey('secure')
        ->and($configValues['cookie'])->toHaveKey('httponly')
        ->and($configValues['cookie'])->toHaveKey('samesite');

    // Check default values are reasonable
    expect($configValues['driver'])->toBe('file')
        ->and($configValues['lifetime'])->toBe(120)
        ->and($configValues['expire_on_close'])->toBeFalse()
        ->and($configValues['path'])->toBe('storage/sessions')
        ->and($configValues['cookie']['name'])->toBe('marko_session')
        ->and($configValues['cookie']['path'])->toBe('/')
        ->and($configValues['cookie']['domain'])->toBe('')
        ->and($configValues['cookie']['secure'])->toBeTrue()
        ->and($configValues['cookie']['httponly'])->toBeTrue()
        ->and($configValues['cookie']['samesite'])->toBe('lax')
        ->and($configValues['gc_probability'])->toBe(2)
        ->and($configValues['gc_divisor'])->toBe(100);
});
