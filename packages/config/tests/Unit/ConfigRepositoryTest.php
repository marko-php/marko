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

it('returns default value when key is missing', function () {
    $config = new ConfigRepository(['name' => 'Marko']);

    expect($config->get('missing', 'default'))->toBe('default');
    expect($config->get('missing.nested.key', 'fallback'))->toBe('fallback');
});

it('checks if key exists with has()', function () {
    $config = new ConfigRepository([
        'name' => 'Marko',
        'database' => [
            'host' => 'localhost',
        ],
    ]);

    expect($config->has('name'))->toBeTrue();
    expect($config->has('database.host'))->toBeTrue();
    expect($config->has('missing'))->toBeFalse();
    expect($config->has('database.missing'))->toBeFalse();
});

it('gets typed values with type-safe accessors', function () {
    $config = new ConfigRepository([
        'name' => 'Marko',
        'port' => 8080,
        'debug' => true,
        'rate' => 1.5,
        'tags' => ['php', 'framework'],
    ]);

    expect($config->getString('name'))->toBe('Marko');
    expect($config->getInt('port'))->toBe(8080);
    expect($config->getBool('debug'))->toBe(true);
    expect($config->getFloat('rate'))->toBe(1.5);
    expect($config->getArray('tags'))->toBe(['php', 'framework']);
});

it('throws ConfigException on type mismatch', function () {
    $config = new ConfigRepository([
        'name' => 'Marko',
        'tags' => ['php', 'framework'],
    ]);

    expect(fn () => $config->getString('tags'))
        ->toThrow(ConfigException::class);
    expect(fn () => $config->getInt('tags'))
        ->toThrow(ConfigException::class);
    expect(fn () => $config->getBool('tags'))
        ->toThrow(ConfigException::class);
    expect(fn () => $config->getFloat('tags'))
        ->toThrow(ConfigException::class);
    expect(fn () => $config->getArray('name'))
        ->toThrow(ConfigException::class);
});

it('throws ConfigNotFoundException when key is missing and no default provided', function () {
    $config = new ConfigRepository(['name' => 'Marko']);

    expect(fn () => $config->getString('missing'))
        ->toThrow(ConfigNotFoundException::class);
    expect(fn () => $config->getInt('missing'))
        ->toThrow(ConfigNotFoundException::class);
    expect(fn () => $config->getBool('missing'))
        ->toThrow(ConfigNotFoundException::class);
    expect(fn () => $config->getFloat('missing'))
        ->toThrow(ConfigNotFoundException::class);
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

    expect($config->get('store.currency', scope: 'us'))->toBe('USD');
    expect($config->get('store.currency', scope: 'eu'))->toBe('EUR');
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

    // 'ca' scope doesn't exist, should fall back to default
    expect($config->get('store.currency', scope: 'ca'))->toBe('GBP');
    // 'us' scope doesn't have timezone, should fall back to default
    expect($config->get('store.timezone', scope: 'us'))->toBe('UTC');
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
    expect($config->get('store.name', scope: 'us'))->toBe('My Store');
    expect($config->get('store.name', scope: 'unknown'))->toBe('My Store');
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

    // Scoped value exists
    expect($config->get('pricing.currency', scope: 'tenant-1'))->toBe('EUR');
    expect($config->get('pricing.tax_rate', scope: 'tenant-1'))->toBe(0.2);

    // Another scope
    expect($config->get('pricing.currency', scope: 'tenant-2'))->toBe('GBP');

    // Falls back to default when scope doesn't have the key
    expect($config->get('pricing.tax_rate', scope: 'tenant-2'))->toBe(0.1);

    // Falls back to default when scope doesn't exist
    expect($config->get('pricing.currency', scope: 'unknown'))->toBe('USD');
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

    // Level 1: Scoped value exists - use it
    expect($config->get('settings.feature_a', scope: 'tenant-1'))->toBe('tenant1-a');

    // Level 2: No scoped value, falls back to default
    expect($config->get('settings.feature_b', scope: 'tenant-1'))->toBe('default-b');

    // Level 3: No scoped or default value, falls back to unscoped
    expect($config->get('settings.feature_c', scope: 'tenant-1'))->toBe('unscoped-c');
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

    // Should return a ConfigRepositoryInterface instance
    expect($tenantConfig)->toBeInstanceOf(ConfigRepositoryInterface::class);

    // Should automatically use the scope without passing it explicitly
    expect($tenantConfig->get('pricing.currency'))->toBe('EUR');

    // Original config should remain unscoped
    expect($config->get('pricing.currency'))->toBe(null);
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
    expect($config->get('app.name', scope: null))->toBe('MyApp');
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

    // Test has() uses scope
    expect($tenantConfig->has('settings.name'))->toBeTrue();
    expect($tenantConfig->has('settings.port'))->toBeTrue();

    // Test getString() uses scope
    expect($tenantConfig->getString('settings.name'))->toBe('tenant-name');

    // Test getInt() uses scope
    expect($tenantConfig->getInt('settings.port'))->toBe(3000);

    // Test getBool() uses scope
    expect($tenantConfig->getBool('settings.debug'))->toBe(true);

    // Test getFloat() uses scope
    expect($tenantConfig->getFloat('settings.rate'))->toBe(2.5);

    // Test getArray() uses scope
    expect($tenantConfig->getArray('settings.tags'))->toBe(['tenant', 'custom']);
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

    // Production scope has deeply nested value
    expect($config->get('database.connection.host', scope: 'production'))->toBe('prod-host');

    // Production scope has double-nested value
    expect($config->get('database.connection.credentials.username', scope: 'production'))->toBe('prod-user');

    // Production scope missing port, falls back to default nested value
    expect($config->get('database.connection.port', scope: 'production'))->toBe(3306);

    // Production scope missing password in credentials, falls back to default
    expect($config->get('database.connection.credentials.password', scope: 'production'))->toBe('default-pass');
});
