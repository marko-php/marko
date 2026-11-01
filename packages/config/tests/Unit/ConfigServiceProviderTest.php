<?php

declare(strict_types=1);

use Marko\Config\ConfigDiscovery;
use Marko\Config\ConfigLoader;
use Marko\Config\ConfigMerger;
use Marko\Config\ConfigRepositoryInterface;
use Marko\Config\ConfigServiceProvider;
use Marko\Core\Discovery\CachedDiscovery;
use Marko\Core\Exceptions\DiscoveryCacheException;

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

/**
 * A ConfigDiscovery that fails the test if live discovery runs.
 */
function failingConfigDiscovery(): ConfigDiscovery
{
    return new readonly class (new ConfigLoader(), new ConfigMerger()) extends ConfigDiscovery
    {
        public function discover(
            array $modulePaths,
            string $rootConfigPath,
        ): array {
            throw new RuntimeException('live config discovery must not run on a cached boot');
        }
    };
}

it('builds the repository from the cached config section without running config discovery', function (): void {
    $provider = new ConfigServiceProvider(discovery: failingConfigDiscovery());

    $repository = $provider->createRepository(
        modulePaths: [__DIR__ . '/fixtures/discovery/module-a'],
        rootConfigPath: __DIR__ . '/fixtures/discovery/root-config',
        cachedDiscovery: new CachedDiscovery([
            'config' => ['values' => ['database' => ['host' => 'cached.db']], 'env' => []],
        ], '/var/app/storage/cache/discovery.php'),
    );

    expect($repository->get('database.host'))->toBe('cached.db');
})->issue(556);

it('loads config live when the boot did not use the discovery cache', function (): void {
    $provider = new ConfigServiceProvider(discovery: new ConfigDiscovery(new ConfigLoader(), new ConfigMerger()));

    $repository = $provider->createRepository(
        modulePaths: [],
        rootConfigPath: __DIR__ . '/fixtures/discovery/root-config',
        cachedDiscovery: new CachedDiscovery(),
    );

    expect($repository->get('database.host'))->toBe('production.db.com');
})->issue(556);

it('throws when the cached config section is malformed', function (array $section, string $reason): void {
    $provider = new ConfigServiceProvider(discovery: failingConfigDiscovery());

    expect(fn () => $provider->createRepository(
        modulePaths: [],
        rootConfigPath: __DIR__ . '/fixtures/discovery/root-config',
        cachedDiscovery: new CachedDiscovery(['config' => $section], '/var/app/storage/cache/discovery.php'),
    ))->toThrow(DiscoveryCacheException::class, "Discovery cache section 'config' is malformed: $reason");
})->with([
    'values missing' => [['env' => []], "'values' must be an array"],
    'env missing' => [['values' => []], "'env' must map variable names to hashes"],
    'env hash not a string' => [['values' => [], 'env' => ['APP_KEY' => 1]], "'env' must map variable names to hashes"],
])->issue(556);
