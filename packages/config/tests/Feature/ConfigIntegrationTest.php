<?php

declare(strict_types=1);

use Marko\Config\ConfigDiscovery;
use Marko\Config\ConfigLoader;
use Marko\Config\ConfigMerger;
use Marko\Config\ConfigRepository;
use Marko\Config\ConfigRepositoryInterface;
use Marko\Config\ConfigServiceProvider;
use Marko\Config\Exceptions\ConfigLoadException;
use Marko\Config\Exceptions\ConfigNotFoundException;
use Marko\Core\Container\Container;
use Marko\Core\Container\ContainerInterface;
use Marko\Core\Container\PreferenceRegistry;

beforeEach(function () {
    // Set up fixture paths
    $this->fixturesPath = __DIR__ . '/fixtures';
    $this->moduleAPath = $this->fixturesPath . '/module-a';
    $this->moduleBPath = $this->fixturesPath . '/module-b';
    $this->appConfigPath = $this->fixturesPath . '/app-config';
});

describe('Config Integration', function (): void {
    it('loads config from module config/ directory', function () {
        $container = createContainerWithBindings();

        $provider = $container->get(ConfigServiceProvider::class);
        $repository = $provider->createRepository(
            modulePaths: [$this->moduleAPath],
            rootConfigPath: $this->fixturesPath . '/empty-config',
        );

        // Module A provides database config
        expect($repository->get('database.host'))->toBe('localhost')
            ->and($repository->get('database.port'))->toBe(3306);
    });

    it('loads config from app config/ directory', function () {
        $container = createContainerWithBindings();

        $provider = $container->get(ConfigServiceProvider::class);
        $repository = $provider->createRepository(
            modulePaths: [],
            rootConfigPath: $this->appConfigPath,
        );

        // App config provides cache settings
        expect($repository->get('cache.driver'))->toBe('redis')
            ->and($repository->get('cache.ttl'))->toBe(7200);
    });

    it('app config overrides module config', function () {
        $container = createContainerWithBindings();

        $provider = $container->get(ConfigServiceProvider::class);
        $repository = $provider->createRepository(
            modulePaths: [$this->moduleAPath, $this->moduleBPath],
            rootConfigPath: $this->appConfigPath,
        );

        // Module A: host=localhost, port=3306
        // Module B: host=module-b.db.com, name=module_b_db
        // App: host=production.db.com (overrides module config)
        expect($repository->get('database.host'))->toBe('production.db.com')
            ->and($repository->get('database.port'))->toBe(3306)
            ->and($repository->get('database.name'))->toBe('module_b_db');
    });

    it('ConfigRepository injectable via constructor', function () {
        $container = createContainerWithBindings();

        // Create and register the repository
        $provider = $container->get(ConfigServiceProvider::class);
        $repository = $provider->createRepository(
            modulePaths: [$this->moduleAPath],
            rootConfigPath: $this->fixturesPath . '/empty-config',
        );
        $container->instance(ConfigRepositoryInterface::class, $repository);

        // Create a test service that depends on ConfigRepositoryInterface
        $service = $container->get(TestServiceWithConfig::class);

        expect($service)->toBeInstanceOf(TestServiceWithConfig::class)
            ->and($service->getDatabaseHost())->toBe('localhost');
    });

    it('config file uses environment variable when set', function () {
        // Create temporary directory structure
        $tempDir = sys_get_temp_dir() . '/marko-config-env-test-' . bin2hex(random_bytes(8));
        mkdir($tempDir . '/app-config', 0755, true);

        try {
            // Create config file that uses $_ENV
            file_put_contents($tempDir . '/app-config/database.php', <<<'PHP'
<?php
declare(strict_types=1);
return [
    'host' => $_ENV['DB_HOST'] ?? 'default-host',
    'port' => (int) ($_ENV['DB_PORT'] ?? 3306),
];
PHP);

            // Set environment variables
            $_ENV['DB_HOST'] = 'env-provided-host';
            $_ENV['DB_PORT'] = '5432';

            $container = createContainerWithBindings();
            $provider = $container->get(ConfigServiceProvider::class);
            $repository = $provider->createRepository(
                modulePaths: [],
                rootConfigPath: $tempDir . '/app-config',
            );

            expect($repository->get('database.host'))->toBe('env-provided-host')
                ->and($repository->get('database.port'))->toBe(5432);
        } finally {
            // Cleanup
            unset($_ENV['DB_HOST'], $_ENV['DB_PORT']);
            @unlink($tempDir . '/app-config/database.php');
            @rmdir($tempDir . '/app-config');
            @rmdir($tempDir);
        }
    });

    it('config file uses default when environment variable is not set', function () {
        // Create temporary directory structure
        $tempDir = sys_get_temp_dir() . '/marko-config-env-default-test-' . bin2hex(random_bytes(8));
        mkdir($tempDir . '/app-config', 0755, true);

        try {
            // Ensure env vars are not set
            unset($_ENV['DB_HOST'], $_ENV['DB_PORT']);

            // Create config file that uses $_ENV with defaults
            file_put_contents($tempDir . '/app-config/database.php', <<<'PHP'
<?php
declare(strict_types=1);
return [
    'host' => $_ENV['DB_HOST'] ?? 'default-host',
    'port' => (int) ($_ENV['DB_PORT'] ?? 3306),
];
PHP);

            $container = createContainerWithBindings();
            $provider = $container->get(ConfigServiceProvider::class);
            $repository = $provider->createRepository(
                modulePaths: [],
                rootConfigPath: $tempDir . '/app-config',
            );

            expect($repository->get('database.host'))->toBe('default-host')
                ->and($repository->get('database.port'))->toBe(3306);
        } finally {
            // Cleanup
            @unlink($tempDir . '/app-config/database.php');
            @rmdir($tempDir . '/app-config');
            @rmdir($tempDir);
        }
    });

    it('throws ConfigNotFoundException with helpful message when required config is missing', function () {
        $container = createContainerWithBindings();
        $provider = $container->get(ConfigServiceProvider::class);
        $repository = $provider->createRepository(
            modulePaths: [],
            rootConfigPath: $this->fixturesPath . '/empty-config',
        );

        try {
            $repository->getString('nonexistent.key');
            expect(false)->toBeTrue(); // Should not reach here
        } catch (ConfigNotFoundException $e) {
            expect($e->getMessage())->toContain('nonexistent.key')
                ->and($e->getKey())->toBe('nonexistent.key')
                ->and($e->getSuggestion())->toContain('config')
                ->and($e->getSuggestion())->toContain('default');
        }
    });

    it('throws ConfigLoadException when config file has PHP syntax error', function () {
        // Create temporary directory structure
        $tempDir = sys_get_temp_dir() . '/marko-config-syntax-error-' . bin2hex(random_bytes(8));
        mkdir($tempDir . '/app-config', 0755, true);

        try {
            // Create config file with syntax error
            file_put_contents($tempDir . '/app-config/broken.php', <<<'PHP'
<?php
declare(strict_types=1);
return [
    'key' => 'value'
    // Missing comma before this line causes syntax error
    'another' => 'value',
];
PHP);

            $container = createContainerWithBindings();
            $provider = $container->get(ConfigServiceProvider::class);
            $provider->createRepository(
                modulePaths: [],
                rootConfigPath: $tempDir . '/app-config',
            );

            expect(false)->toBeTrue(); // Should not reach here
        } catch (ConfigLoadException $e) {
            expect($e->getMessage())->toContain($tempDir . '/app-config/broken.php')
                ->and($e->getFilePath())->toBe($tempDir . '/app-config/broken.php');
        } finally {
            // Cleanup
            @unlink($tempDir . '/app-config/broken.php');
            @rmdir($tempDir . '/app-config');
            @rmdir($tempDir);
        }
    });

    it('throws ConfigLoadException when config file returns non-array', function () {
        // Create temporary directory structure
        $tempDir = sys_get_temp_dir() . '/marko-config-non-array-' . bin2hex(random_bytes(8));
        mkdir($tempDir . '/app-config', 0755, true);

        try {
            // Create config file that returns string instead of array
            file_put_contents($tempDir . '/app-config/invalid.php', <<<'PHP'
<?php
declare(strict_types=1);
return "this is not an array";
PHP);

            $container = createContainerWithBindings();
            $provider = $container->get(ConfigServiceProvider::class);
            $provider->createRepository(
                modulePaths: [],
                rootConfigPath: $tempDir . '/app-config',
            );

            expect(false)->toBeTrue(); // Should not reach here
        } catch (ConfigLoadException $e) {
            expect($e->getMessage())->toContain($tempDir . '/app-config/invalid.php')
                ->and($e->getFilePath())->toBe($tempDir . '/app-config/invalid.php');
        } finally {
            // Cleanup
            @unlink($tempDir . '/app-config/invalid.php');
            @rmdir($tempDir . '/app-config');
            @rmdir($tempDir);
        }
    });

    it('full config lifecycle with temporary module structure', function () {
        // Create temporary directory structure
        $tempDir = sys_get_temp_dir() . '/marko-config-test-' . bin2hex(random_bytes(8));
        mkdir($tempDir . '/module-x/config', 0755, true);
        mkdir($tempDir . '/module-y/config', 0755, true);
        mkdir($tempDir . '/app-config', 0755, true);

        try {
            // Create config files
            file_put_contents($tempDir . '/module-x/config/app.php', <<<'PHP'
<?php
declare(strict_types=1);
return [
    'name' => 'Module X App',
    'debug' => false,
    'default' => [
        'locale' => 'en_US',
    ],
    'scopes' => [
        'eu' => [
            'locale' => 'de_DE',
        ],
    ],
];
PHP);

            file_put_contents($tempDir . '/module-y/config/app.php', <<<'PHP'
<?php
declare(strict_types=1);
return [
    'version' => '1.0.0',
    'debug' => true,
];
PHP);

            file_put_contents($tempDir . '/app-config/app.php', <<<'PHP'
<?php
declare(strict_types=1);
return [
    'name' => 'Production App',
];
PHP);

            $container = createContainerWithBindings();
            $provider = $container->get(ConfigServiceProvider::class);
            $repository = $provider->createRepository(
                modulePaths: [$tempDir . '/module-x', $tempDir . '/module-y'],
                rootConfigPath: $tempDir . '/app-config',
            );

            // Verify merged config
            expect($repository->get('app.name'))->toBe('Production App')
                ->and($repository->get('app.version'))->toBe('1.0.0')
                ->and($repository->get('app.debug'))->toBeTrue()
                ->and($repository->has('app.name'))->toBeTrue()
                ->and($repository->has('app.nonexistent'))->toBeFalse()
                ->and($repository->get('app.locale', scope: 'eu'))->toBe('de_DE')
                ->and($repository->get('app.locale', scope: 'us'))->toBe('en_US');

            // Verify dot notation access

            // Verify scoped access
        } finally {
            // Cleanup
            @unlink($tempDir . '/module-x/config/app.php');
            @unlink($tempDir . '/module-y/config/app.php');
            @unlink($tempDir . '/app-config/app.php');
            @rmdir($tempDir . '/module-x/config');
            @rmdir($tempDir . '/module-y/config');
            @rmdir($tempDir . '/app-config');
            @rmdir($tempDir . '/module-x');
            @rmdir($tempDir . '/module-y');
            @rmdir($tempDir);
        }
    });

    it('multi-tenant scenario with default and scoped values', function () {
        // Create temporary directory structure
        $tempDir = sys_get_temp_dir() . '/marko-config-multitenant-' . bin2hex(random_bytes(8));
        mkdir($tempDir . '/app-config', 0755, true);

        try {
            // Create config file with multi-tenant structure
            file_put_contents($tempDir . '/app-config/store.php', <<<'PHP'
<?php
declare(strict_types=1);
return [
    'default' => [
        'currency' => 'USD',
        'locale' => 'en_US',
        'tax_rate' => 0.08,
        'shipping' => [
            'provider' => 'ups',
            'free_threshold' => 50.00,
        ],
    ],
    'scopes' => [
        'tenant-eu' => [
            'currency' => 'EUR',
            'locale' => 'de_DE',
            'tax_rate' => 0.19,
            'shipping' => [
                'provider' => 'dhl',
            ],
        ],
        'tenant-uk' => [
            'currency' => 'GBP',
            'locale' => 'en_GB',
            'tax_rate' => 0.20,
        ],
    ],
];
PHP);

            $container = createContainerWithBindings();
            $provider = $container->get(ConfigServiceProvider::class);
            $repository = $provider->createRepository(
                modulePaths: [],
                rootConfigPath: $tempDir . '/app-config',
            );

            // Verify scope resolution: tenant-eu has scoped value
            expect($repository->get('store.currency', scope: 'tenant-eu'))->toBe('EUR')
                ->and($repository->get('store.locale', scope: 'tenant-eu'))->toBe('de_DE')
                ->and($repository->get('store.tax_rate', scope: 'tenant-eu'))->toBe(0.19)
                ->and($repository->get('store.currency', scope: 'tenant-uk'))->toBe('GBP')
                ->and($repository->get('store.locale', scope: 'tenant-uk'))->toBe('en_GB')
                ->and($repository->get('store.shipping.free_threshold', scope: 'tenant-eu'))->toBe(50.00)
                ->and($repository->get('store.shipping.free_threshold', scope: 'tenant-uk'))->toBe(50.00)
                ->and($repository->get('store.shipping.provider', scope: 'tenant-eu'))->toBe('dhl')
                ->and($repository->get('store.shipping.provider', scope: 'tenant-uk'))->toBe('ups')
                ->and($repository->get('store.currency', scope: 'unknown-tenant'))->toBe('USD');

            // Verify scope resolution: tenant-uk has scoped values

            // Verify fallback to default when scope doesn't have the key

            // Verify nested scoped value overrides nested default

            // Verify fallback to default when scope doesn't exist
        } finally {
            // Cleanup
            @unlink($tempDir . '/app-config/store.php');
            @rmdir($tempDir . '/app-config');
            @rmdir($tempDir);
        }
    });

    it('withScope creates properly scoped instance for multi-tenant access', function () {
        // Create temporary directory structure
        $tempDir = sys_get_temp_dir() . '/marko-config-withscope-' . bin2hex(random_bytes(8));
        mkdir($tempDir . '/app-config', 0755, true);

        try {
            // Create config file with multi-tenant structure
            file_put_contents($tempDir . '/app-config/pricing.php', <<<'PHP'
<?php
declare(strict_types=1);
return [
    'default' => [
        'currency' => 'USD',
        'discount_rate' => 0.0,
    ],
    'scopes' => [
        'premium' => [
            'discount_rate' => 0.15,
        ],
        'enterprise' => [
            'discount_rate' => 0.25,
        ],
    ],
];
PHP);

            $container = createContainerWithBindings();
            $provider = $container->get(ConfigServiceProvider::class);
            $repository = $provider->createRepository(
                modulePaths: [],
                rootConfigPath: $tempDir . '/app-config',
            );

            // Create scoped instances
            $premiumConfig = $repository->withScope('premium');
            $enterpriseConfig = $repository->withScope('enterprise');

            // Verify withScope creates ConfigRepositoryInterface instance
            expect($premiumConfig)->toBeInstanceOf(ConfigRepositoryInterface::class)
                ->and($enterpriseConfig)->toBeInstanceOf(ConfigRepositoryInterface::class)
                ->and($premiumConfig->get('pricing.discount_rate'))->toBe(0.15)
                ->and($enterpriseConfig->get('pricing.discount_rate'))->toBe(0.25)
                ->and($premiumConfig->get('pricing.currency'))->toBe('USD')
                ->and($enterpriseConfig->get('pricing.currency'))->toBe('USD')
                // Original unscoped repository throws because keys don't exist at unscoped level
                ->and(fn () => $repository->get('pricing.currency'))->toThrow(ConfigNotFoundException::class)
                ->and(fn () => $repository->get('pricing.discount_rate'))->toThrow(ConfigNotFoundException::class)
                ->and($premiumConfig->getFloat('pricing.discount_rate'))->toBe(0.15)
                ->and($enterpriseConfig->getString('pricing.currency'))->toBe('USD');

            // Verify scoped instances automatically use their scope

            // Verify scoped instances fall back to default for unscoped keys

            // Verify original repository is unaffected

            // Verify typed accessors work with scoped instances
        } finally {
            // Cleanup
            @unlink($tempDir . '/app-config/pricing.php');
            @rmdir($tempDir . '/app-config');
            @rmdir($tempDir);
        }
    });
});

/**
 * Helper to create container with config bindings.
 */
function createContainerWithBindings(): Container
{
    $container = new Container(new PreferenceRegistry());

    // Register config bindings (same as module.php)
    $container->bind(ConfigLoader::class, ConfigLoader::class);
    $container->bind(ConfigMerger::class, ConfigMerger::class);
    $container->bind(ConfigDiscovery::class, ConfigDiscovery::class);
    $container->bind(ConfigServiceProvider::class, ConfigServiceProvider::class);
    $container->bind(
        ConfigRepositoryInterface::class,
        fn (ContainerInterface $c) => $c->get(ConfigRepository::class),
    );

    return $container;
}

/**
 * Test service that depends on ConfigRepositoryInterface.
 */
readonly class TestServiceWithConfig
{
    public function __construct(
        private ConfigRepositoryInterface $config,
    ) {}

    /** @noinspection PhpUnused */
    public function getDatabaseHost(): string
    {
        return $this->config->getString('database.host');
    }
}
