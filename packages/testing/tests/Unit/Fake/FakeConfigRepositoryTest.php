<?php

declare(strict_types=1);

use Marko\Config\ConfigRepositoryInterface;
use Marko\Config\Exceptions\ConfigNotFoundException;
use Marko\Testing\Fake\FakeConfigRepository;

it('implements ConfigRepositoryInterface', function () {
    $config = new FakeConfigRepository();

    expect($config)->toBeInstanceOf(ConfigRepositoryInterface::class);
});

it('stores and retrieves values using dot notation', function () {
    $config = new FakeConfigRepository([
        'auth.defaults.guard' => 'web',
        'auth.guards.web.driver' => 'session',
    ]);

    expect($config->get('auth.defaults.guard'))->toBe('web')
        ->and($config->get('auth.guards.web.driver'))->toBe('session');
});

it('checks if key exists with has()', function () {
    $config = new FakeConfigRepository([
        'app.name' => 'Marko',
    ]);

    expect($config->has('app.name'))->toBeTrue()
        ->and($config->has('app.missing'))->toBeFalse();
});

it('throws ConfigNotFoundException for missing keys', function () {
    $config = new FakeConfigRepository();

    $config->get('missing.key');
})->throws(ConfigNotFoundException::class);

it('returns typed values via getString, getInt, getBool, getFloat, getArray', function () {
    $config = new FakeConfigRepository([
        'app.name' => 'Marko',
        'app.port' => 8080,
        'app.debug' => true,
        'app.version' => 1.5,
        'app.tags' => ['php', 'framework'],
    ]);

    expect($config->getString('app.name'))->toBe('Marko')
        ->and($config->getInt('app.port'))->toBe(8080)
        ->and($config->getBool('app.debug'))->toBeTrue()
        ->and($config->getFloat('app.version'))->toBe(1.5)
        ->and($config->getArray('app.tags'))->toBe(['php', 'framework']);
});

it('supports scoped config access', function () {
    $config = new FakeConfigRepository([
        'scopes.eu.app.name' => 'Marko EU',
        'default.app.name' => 'Marko Default',
        'app.name' => 'Marko',
    ]);

    // Scope-specific key takes priority
    expect($config->get('app.name', 'eu'))->toBe('Marko EU')
        // Default scope key used when scope-specific not found
        ->and($config->get('app.name', 'us'))->toBe('Marko Default')
        // Global key used when neither scope nor default found
        ->and($config->get('app.name'))->toBe('Marko');
});

it('returns all config values', function () {
    $values = [
        'app.name' => 'Marko',
        'app.debug' => false,
    ];
    $config = new FakeConfigRepository($values);

    expect($config->all())->toBe($values);
});

it('creates scoped instance via withScope', function () {
    $config = new FakeConfigRepository([
        'scopes.eu.app.name' => 'Marko EU',
        'default.app.name' => 'Marko Default',
        'app.name' => 'Marko',
    ]);

    $scoped = $config->withScope('eu');

    // Scoped instance auto-applies the scope for all lookups
    expect($scoped->get('app.name'))->toBe('Marko EU')
        ->and($scoped)->toBeInstanceOf(ConfigRepositoryInterface::class)
        ->and($scoped)->not->toBe($config);
});

it('accepts initial config values via constructor', function () {
    $config = new FakeConfigRepository([
        'auth.defaults.guard' => 'web',
        'auth.guards.web.driver' => 'session',
        'auth.guards.web.provider' => 'users',
        'auth.session_key' => 'auth_user_id',
    ]);

    expect($config->get('auth.defaults.guard'))->toBe('web')
        ->and($config->get('auth.guards.web.driver'))->toBe('session')
        ->and($config->get('auth.guards.web.provider'))->toBe('users')
        ->and($config->get('auth.session_key'))->toBe('auth_user_id');
});

it('supports setting values after construction', function () {
    $config = new FakeConfigRepository();

    $config->set('app.name', 'Marko');
    $config->set('app.debug', true);

    expect($config->get('app.name'))->toBe('Marko')
        ->and($config->get('app.debug'))->toBeTrue()
        ->and($config->has('app.name'))->toBeTrue();
});
