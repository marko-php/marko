<?php

declare(strict_types=1);

use Marko\Config\ConfigDiscovery;
use Marko\Config\ConfigLoader;
use Marko\Config\ConfigMerger;
use Marko\Config\ConfigRepositoryInterface;
use Marko\Config\ConfigServiceProvider;

it('can be instantiated with ConfigDiscovery', function () {
    $discovery = new ConfigDiscovery(
        loader: new ConfigLoader(),
        merger: new ConfigMerger(),
    );

    $provider = new ConfigServiceProvider(
        discovery: $discovery,
    );

    expect($provider)->toBeInstanceOf(ConfigServiceProvider::class);
});

it('creates ConfigRepository from module paths', function () {
    $discovery = new ConfigDiscovery(
        loader: new ConfigLoader(),
        merger: new ConfigMerger(),
    );

    $provider = new ConfigServiceProvider(
        discovery: $discovery,
    );

    $modulePaths = [
        __DIR__ . '/fixtures/discovery/module-a',
        __DIR__ . '/fixtures/discovery/module-b',
    ];

    $repository = $provider->createRepository(
        modulePaths: $modulePaths,
        rootConfigPath: __DIR__ . '/fixtures/discovery/root-config',
    );

    expect($repository)->toBeInstanceOf(ConfigRepositoryInterface::class)
        ->and($repository->get('database.host'))->toBe('production.db.com')
        ->and($repository->get('database.port'))->toBe(3306)
        ->and($repository->get('database.name'))->toBe('app_db');
});

it('returns empty repository when no config found', function () {
    $discovery = new ConfigDiscovery(
        loader: new ConfigLoader(),
        merger: new ConfigMerger(),
    );

    $provider = new ConfigServiceProvider(
        discovery: $discovery,
    );

    $repository = $provider->createRepository(
        modulePaths: [],
        rootConfigPath: __DIR__ . '/fixtures/discovery/empty-root',
    );

    expect($repository)->toBeInstanceOf(ConfigRepositoryInterface::class)
        ->and($repository->all())->toBe([]);
});
