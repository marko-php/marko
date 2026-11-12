<?php

declare(strict_types=1);

use Marko\Core\Command\ConfirmationPrompterInterface;
use Marko\Core\Container\Container;
use Marko\Core\Container\ContainerInterface;
use Marko\Core\Environment\AppEnvironment;
use Marko\Core\Path\ProjectPaths;
use Marko\Core\Sleep\SleeperInterface;
use Marko\Core\Sleep\UsleepSleeper;
use Marko\Database\Config\DatabaseConfig;
use Marko\Database\Connection\TransactionBackoff;
use Marko\Database\Diff\DiffCalculator;
use Marko\Database\Exceptions\SeederException;
use Marko\Database\Schema\Column;
use Marko\Database\Schema\Index;
use Marko\Database\Schema\Table;
use Marko\Database\Seed\SeederDiscoveryInterface;
use Marko\Database\Seed\SeederRunner;
use Marko\Testing\Fake\FakeSleeper;

/**
 * @return array<string, mixed>
 */
function databaseModuleConfig(): array
{
    return require dirname(__DIR__, 2) . '/module.php';
}

function databaseModuleContainer(
    AppEnvironment $appEnvironment,
): Container {
    $container = new Container();
    $container->instance(ContainerInterface::class, $container);
    $container->instance(AppEnvironment::class, $appEnvironment);
    $container->instance(ProjectPaths::class, new ProjectPaths(sys_get_temp_dir() . '/marko-missing-project'));
    $container->instance(SeederDiscoveryInterface::class, new class () implements SeederDiscoveryInterface
    {
        public function discoverInVendor(
            string $vendorPath,
        ): array {
            return [];
        }

        public function discoverInModules(
            string $modulesPath,
        ): array {
            return [];
        }

        public function discoverInApp(
            string $appPath,
        ): array {
            return [];
        }
    });

    return $container;
}

it('builds SeederRunner from module.php with the container AppEnvironment', function (): void {
    $binding = databaseModuleConfig()['bindings'][SeederRunner::class];
    $container = databaseModuleContainer(new AppEnvironment(['APP_ENV' => 'production']));

    $runner = $binding($container);

    expect(fn () => $runner->runAll([]))->toThrow(SeederException::class);
});

it('builds DiffCalculator from module.php with the configured ignore list', function (): void {
    $binding = databaseModuleConfig()['bindings'][DiffCalculator::class];
    $container = databaseModuleContainer(new AppEnvironment(['APP_ENV' => 'local']));
    $container->instance(DatabaseConfig::class, DatabaseConfig::fromArray([
        'driver' => 'pgsql',
        'host' => 'localhost',
        'port' => 5432,
        'database' => 'app',
        'username' => 'app',
        'password' => 'secret',
        'migrations' => ['ignore_indexes' => ['*_gin_idx']],
    ]));
    $columns = [new Column(name: 'id', type: 'INT', primaryKey: true)];

    $calculator = $binding($container);
    $diff = $calculator->calculate(
        ['shows' => new Table(name: 'shows', columns: $columns)],
        ['shows' => new Table(name: 'shows', columns: $columns, indexes: [
            new Index(name: 'shows_tags_gin_idx', columns: ['id']),
        ])],
    );

    expect($diff->isEmpty())->toBeTrue();
});

it('does not bind a confirmation prompter in the database module', function (): void {
    // marko/core binds ConfirmationPrompterInterface; a second binding here would shadow it
    expect(databaseModuleConfig()['bindings'])->not->toHaveKey(ConfirmationPrompterInterface::class);
});

it('builds a SeederRunner from module.php that runs outside production', function (): void {
    $binding = databaseModuleConfig()['bindings'][SeederRunner::class];
    $container = databaseModuleContainer(new AppEnvironment(['APP_ENV' => 'local']));

    $runner = $binding($container);
    $runner->runAll([]);

    expect($runner)->toBeInstanceOf(SeederRunner::class);
});

it('leaves the SleeperInterface binding to marko/core', function (): void {
    expect(databaseModuleConfig()['bindings'])->not->toHaveKey(SleeperInterface::class);
});

it('declares only the randomizer argument for TransactionBackoff', function (): void {
    $config = databaseModuleConfig();

    expect($config['bindings'])->not->toHaveKey(TransactionBackoff::class)
        ->and(array_keys($config['arguments'][TransactionBackoff::class] ?? []))->toBe(['randomizer']);
});

it('builds TransactionBackoff with the bound sleeper', function (): void {
    $container = databaseModuleContainer(new AppEnvironment(['APP_ENV' => 'production']));
    // marko/core's default binding
    $container->bind(SleeperInterface::class, UsleepSleeper::class);
    foreach (databaseModuleConfig()['arguments'] as $class => $arguments) {
        $container->setArguments($class, $arguments);
    }
    $sleeper = new FakeSleeper();

    $backoff = $container->get(TransactionBackoff::class);
    $container->instance(SleeperInterface::class, $sleeper);
    $withFake = $container->get(TransactionBackoff::class);

    expect($backoff)->toBeInstanceOf(TransactionBackoff::class)
        ->and(new ReflectionProperty($backoff, 'sleeper')->getValue($backoff))->toBeInstanceOf(UsleepSleeper::class)
        ->and(new ReflectionProperty($withFake, 'sleeper')->getValue($withFake))->toBe($sleeper);
});
