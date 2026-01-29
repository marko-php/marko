<?php

declare(strict_types=1);

use Marko\Config\ConfigRepository;
use Marko\Config\ConfigRepositoryInterface;
use Marko\Config\Exceptions\ConfigException;
use Marko\Config\Exceptions\ConfigNotFoundException;

it('gets simple key value', function () {
    $config = new ConfigRepository(['name' => 'Marko']);

    expect($config->get('name'))->toBe('Marko');
});

it('gets nested value with dot notation', function () {
    $config = new ConfigRepository([
        'database' => [
            'connection' => [
                'host' => 'localhost',
            ],
        ],
    ]);

    expect($config->get('database.connection.host'))->toBe('localhost');
});

it('throws ConfigNotFoundException when get is called with missing key', function () {
    $config = new ConfigRepository(['name' => 'Marko']);

    expect(fn () => $config->get('missing'))
        ->toThrow(ConfigNotFoundException::class)
        ->and(fn () => $config->get('missing.nested.key'))
        ->toThrow(ConfigNotFoundException::class);
});

it('checks if key exists with has()', function () {
    $config = new ConfigRepository([
        'name' => 'Marko',
        'database' => [
            'host' => 'localhost',
        ],
    ]);

    expect($config->has('name'))->toBeTrue()
        ->and($config->has('database.host'))->toBeTrue()
        ->and($config->has('missing'))->toBeFalse()
        ->and($config->has('database.missing'))->toBeFalse();
});

it('returns value when key exists for all getter types', function () {
    $config = new ConfigRepository([
        'name' => 'Marko',
        'port' => 8080,
        'debug' => true,
        'rate' => 1.5,
        'tags' => ['php', 'framework'],
    ]);

    expect($config->get('name'))->toBe('Marko')
        ->and($config->getString('name'))->toBe('Marko')
        ->and($config->getInt('port'))->toBe(8080)
        ->and($config->getBool('debug'))->toBeTrue()
        ->and($config->getFloat('rate'))->toBe(1.5)
        ->and($config->getArray('tags'))->toBe(['php', 'framework']);
});

it('throws ConfigException on type mismatch', function () {
    $config = new ConfigRepository([
        'name' => 'Marko',
        'tags' => ['php', 'framework'],
    ]);

    expect(fn () => $config->getString('tags'))
        ->toThrow(ConfigException::class)
        ->and(fn () => $config->getInt('tags'))
        ->toThrow(ConfigException::class)
        ->and(fn () => $config->getBool('tags'))
        ->toThrow(ConfigException::class)
        ->and(fn () => $config->getFloat('tags'))
        ->toThrow(ConfigException::class)
        ->and(fn () => $config->getArray('name'))
        ->toThrow(ConfigException::class);
});

it('throws ConfigNotFoundException when getString is called with missing key', function () {
    $config = new ConfigRepository(['name' => 'Marko']);

    expect(fn () => $config->getString('missing'))
        ->toThrow(ConfigNotFoundException::class);
});

it('throws ConfigNotFoundException when getInt is called with missing key', function () {
    $config = new ConfigRepository(['name' => 'Marko']);

    expect(fn () => $config->getInt('missing'))
        ->toThrow(ConfigNotFoundException::class);
});

it('throws ConfigNotFoundException when getBool is called with missing key', function () {
    $config = new ConfigRepository(['name' => 'Marko']);

    expect(fn () => $config->getBool('missing'))
        ->toThrow(ConfigNotFoundException::class);
});

it('throws ConfigNotFoundException when getFloat is called with missing key', function () {
    $config = new ConfigRepository(['name' => 'Marko']);

    expect(fn () => $config->getFloat('missing'))
        ->toThrow(ConfigNotFoundException::class);
});

it('throws ConfigNotFoundException when getArray is called with missing key', function () {
    $config = new ConfigRepository(['name' => 'Marko']);

    expect(fn () => $config->getArray('missing'))
        ->toThrow(ConfigNotFoundException::class);
});

it('returns scope-specific value when scope is provided', function () {
    $config = new ConfigRepository([
        'store' => [
            'scopes' => [
                'us' => [
                    'currency' => 'USD',
                ],
                'eu' => [
                    'currency' => 'EUR',
                ],
            ],
            'default' => [
                'currency' => 'GBP',
            ],
        ],
    ]);

    expect($config->get('store.currency', scope: 'us'))->toBe('USD')
        ->and($config->get('store.currency', scope: 'eu'))->toBe('EUR');
});

it('falls back to default scope when scope-specific value is missing', function () {
    $config = new ConfigRepository([
        'store' => [
            'scopes' => [
                'us' => [
                    'currency' => 'USD',
                ],
            ],
            'default' => [
                'currency' => 'GBP',
                'timezone' => 'UTC',
            ],
        ],
    ]);

    // 'ca' scope doesn't exist, 'us' scope doesn't have timezone - both fall back to default
    expect($config->get('store.currency', scope: 'ca'))->toBe('GBP')
        ->and($config->get('store.timezone', scope: 'us'))->toBe('UTC');
});

it('falls back to unscoped value when scope and default are missing', function () {
    $config = new ConfigRepository([
        'store' => [
            'name' => 'My Store',
            'scopes' => [
                'us' => [
                    'currency' => 'USD',
                ],
            ],
        ],
    ]);

    // 'name' is not in scopes or default, should fall back to unscoped value
    expect($config->get('store.name', scope: 'us'))->toBe('My Store')
        ->and($config->get('store.name', scope: 'unknown'))->toBe('My Store');
});

it('still supports scoped config cascade for missing scope keys', function () {
    $config = new ConfigRepository([
        'app' => [
            'default' => [
                'name' => 'DefaultApp',
                'version' => '1.0',
            ],
            'scopes' => [
                'tenant-1' => [
                    'name' => 'Tenant1App',
                    // version not defined - should cascade to default
                ],
            ],
        ],
    ]);

    // Scope-specific value exists
    expect($config->get('app.name', scope: 'tenant-1'))->toBe('Tenant1App')
        // Scope-specific value missing - cascades to default
        ->and($config->get('app.version', scope: 'tenant-1'))->toBe('1.0')
        // Unknown scope - cascades to default
        ->and($config->get('app.name', scope: 'unknown'))->toBe('DefaultApp');
});

it('returns entire config with all()', function () {
    $configData = [
        'name' => 'Marko',
        'database' => [
            'host' => 'localhost',
            'port' => 3306,
        ],
    ];
    $config = new ConfigRepository($configData);

    expect($config->all())->toBe($configData);
});

// Scoped Configuration Support Tests

it('supports scoped config structure with default and scopes keys', function () {
    $config = new ConfigRepository([
        'pricing' => [
            'default' => [
                'currency' => 'USD',
                'tax_rate' => 0.1,
            ],
            'scopes' => [
                'tenant-1' => [
                    'currency' => 'EUR',
                    'tax_rate' => 0.2,
                ],
                'tenant-2' => [
                    'currency' => 'GBP',
                ],
            ],
        ],
    ]);

    // Scoped values, fallback to default when key missing, fallback when scope doesn't exist
    expect($config->get('pricing.currency', scope: 'tenant-1'))->toBe('EUR')
        ->and($config->get('pricing.tax_rate', scope: 'tenant-1'))->toBe(0.2)
        ->and($config->get('pricing.currency', scope: 'tenant-2'))->toBe('GBP')
        ->and($config->get('pricing.tax_rate', scope: 'tenant-2'))->toBe(0.1)
        ->and($config->get('pricing.currency', scope: 'unknown'))->toBe('USD');
});

it('follows correct scope resolution order: scoped -> default -> unscoped', function () {
    $config = new ConfigRepository([
        'settings' => [
            // Unscoped value (level 3 fallback)
            'feature_a' => 'unscoped-a',
            'feature_b' => 'unscoped-b',
            'feature_c' => 'unscoped-c',
            'default' => [
                // Default scope value (level 2 fallback)
                'feature_a' => 'default-a',
                'feature_b' => 'default-b',
            ],
            'scopes' => [
                'tenant-1' => [
                    // Scoped value (level 1 - highest priority)
                    'feature_a' => 'tenant1-a',
                ],
            ],
        ],
    ]);

    // Level 1: scoped value, Level 2: default fallback, Level 3: unscoped fallback
    expect($config->get('settings.feature_a', scope: 'tenant-1'))->toBe('tenant1-a')
        ->and($config->get('settings.feature_b', scope: 'tenant-1'))->toBe('default-b')
        ->and($config->get('settings.feature_c', scope: 'tenant-1'))->toBe('unscoped-c');
});

it('withScope returns a new ConfigRepository with default scope set', function () {
    $config = new ConfigRepository([
        'pricing' => [
            'default' => [
                'currency' => 'USD',
            ],
            'scopes' => [
                'tenant-1' => [
                    'currency' => 'EUR',
                ],
            ],
        ],
    ]);

    $tenantConfig = $config->withScope('tenant-1');

    // Returns ConfigRepositoryInterface, uses scope automatically
    expect($tenantConfig)->toBeInstanceOf(ConfigRepositoryInterface::class)
        ->and($tenantConfig->get('pricing.currency'))->toBe('EUR')
        // Original without scope throws because key doesn't exist at unscoped level
        ->and(fn () => $config->get('pricing.currency'))->toThrow(ConfigNotFoundException::class);
});

it('uses unscoped values when scope is null', function () {
    $config = new ConfigRepository([
        'app' => [
            'name' => 'MyApp',
            'default' => [
                'name' => 'DefaultApp',
            ],
            'scopes' => [
                'tenant-1' => [
                    'name' => 'Tenant1App',
                ],
            ],
        ],
    ]);

    // When scope is null, get the unscoped value directly (not from default or scopes)
    expect($config->get('app.name'))->toBe('MyApp');
});

it('scoped repository respects scope on all methods', function () {
    $config = new ConfigRepository([
        'settings' => [
            'default' => [
                'name' => 'default-name',
                'port' => 8080,
                'debug' => false,
                'rate' => 1.0,
                'tags' => ['default'],
            ],
            'scopes' => [
                'tenant-1' => [
                    'name' => 'tenant-name',
                    'port' => 3000,
                    'debug' => true,
                    'rate' => 2.5,
                    'tags' => ['tenant', 'custom'],
                ],
            ],
        ],
    ]);

    $tenantConfig = $config->withScope('tenant-1');

    // All typed accessors respect the scope
    expect($tenantConfig->has('settings.name'))->toBeTrue()
        ->and($tenantConfig->has('settings.port'))->toBeTrue()
        ->and($tenantConfig->getString('settings.name'))->toBe('tenant-name')
        ->and($tenantConfig->getInt('settings.port'))->toBe(3000)
        ->and($tenantConfig->getBool('settings.debug'))->toBeTrue()
        ->and($tenantConfig->getFloat('settings.rate'))->toBe(2.5)
        ->and($tenantConfig->getArray('settings.tags'))->toBe(['tenant', 'custom']);
});

it('supports nested scoped values multiple levels deep', function () {
    $config = new ConfigRepository([
        'database' => [
            'default' => [
                'connection' => [
                    'host' => 'default-host',
                    'port' => 3306,
                    'credentials' => [
                        'username' => 'default-user',
                        'password' => 'default-pass',
                    ],
                ],
            ],
            'scopes' => [
                'production' => [
                    'connection' => [
                        'host' => 'prod-host',
                        'credentials' => [
                            'username' => 'prod-user',
                        ],
                    ],
                ],
            ],
        ],
    ]);

    // Deeply nested scoped values with fallback to default for missing keys
    expect($config->get('database.connection.host', scope: 'production'))->toBe('prod-host')
        ->and($config->get('database.connection.credentials.username', scope: 'production'))->toBe('prod-user')
        ->and($config->get('database.connection.port', scope: 'production'))->toBe(3306)
        ->and($config->get('database.connection.credentials.password', scope: 'production'))->toBe('default-pass');
});
