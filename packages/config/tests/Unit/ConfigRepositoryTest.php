<?php

declare(strict_types=1);

use Marko\Config\ConfigRepository;
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
