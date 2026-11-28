<?php

declare(strict_types=1);

use Marko\Config\ConfigRepository;
use Marko\Core\Container\Container;
use Marko\Core\Container\ContainerInterface;
use Marko\Core\Environment\AppEnvironment;
use Marko\Core\Path\ProjectPaths;
use Marko\Core\Plugin\InterceptorClassGenerator;
use Marko\Core\Plugin\PluginDiscovery;
use Marko\Core\Plugin\PluginInterceptedInterface;
use Marko\Core\Plugin\PluginInterceptor;
use Marko\Core\Plugin\PluginRegistry;
use Marko\Database\Config\DatabaseConfig;
use Marko\Database\Connection\ConnectionInterface;
use Marko\Database\Connection\TransactionInterface;
use Marko\Database\Sqlite\Connection\SqliteConnection;
use Marko\Debugbar\Access\AccessGate;
use Marko\Debugbar\Data\QueryRecord;
use Marko\Debugbar\Debugbar;
use Marko\Debugbar\Plugins\DatabaseConnectionPlugin;
use Marko\Debugbar\Storage\DebugbarStorage;
use RuntimeException;

beforeEach(function (): void {
    Debugbar::forgetCurrent();
});

/**
 * Builds a container wired like debugbar's and the sqlite driver's module.php, with the real
 * DatabaseConnectionPlugin intercepting ConnectionInterface.
 *
 * @return array{container: Container, debugbar: Debugbar, basePath: string}
 */
function makeDebugbarTransactionContainer(): array
{
    $basePath = sys_get_temp_dir() . '/marko-debugbar-tx-' . bin2hex(random_bytes(8));
    $config = new ConfigRepository([
        'debugbar' => [
            'enabled' => true,
            'capture_cli' => true,
            'collectors' => ['database' => true],
            'options' => ['database' => ['with_bindings' => true, 'slow_threshold_ms' => 100]],
            'storage' => ['enabled' => true, 'path' => 'storage/debugbar', 'max_files' => 100],
        ],
    ]);
    $debugbar = new Debugbar(
        $config,
        new DebugbarStorage($config, new ProjectPaths($basePath)),
        access: new AccessGate($config, new AppEnvironment(['APP_ENV' => 'local'])),
    );

    $driverBindings = (require dirname(__DIR__, 2) . '/database-sqlite/module.php')['bindings'];

    $container = new Container();
    $container->instance(ContainerInterface::class, $container);
    $container->bind(
        ConnectionInterface::class,
        fn (): ConnectionInterface => new SqliteConnection(
            DatabaseConfig::fromArray(['driver' => 'sqlite', 'database' => ':memory:']),
        ),
    );
    $container->singleton(ConnectionInterface::class);
    $container->bind(TransactionInterface::class, $driverBindings[TransactionInterface::class]);
    $container->instance(Debugbar::class, $debugbar);
    $container->singleton(DatabaseConnectionPlugin::class);

    $registry = new PluginRegistry();
    $registry->register((new PluginDiscovery())->parsePluginClass(DatabaseConnectionPlugin::class));
    $container->setPluginInterceptor(new PluginInterceptor($container, $registry, new InterceptorClassGenerator()));

    return ['container' => $container, 'debugbar' => $debugbar, 'basePath' => $basePath];
}

function removeDebugbarTransactionDirectory(
    string $directory,
): void {
    if (!is_dir($directory)) {
        return;
    }

    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST,
    );

    foreach ($files as $file) {
        $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
    }

    rmdir($directory);
}

it(
    'resolves TransactionInterface from the driver binding with the debugbar connection plugin active',
    function (): void {
        ['container' => $container, 'basePath' => $basePath] = makeDebugbarTransactionContainer();

        $transactions = $container->get(TransactionInterface::class);

        expect($transactions)->toBeInstanceOf(TransactionInterface::class)
            ->toBeInstanceOf(PluginInterceptedInterface::class)
            ->toBe($container->get(ConnectionInterface::class));

        removeDebugbarTransactionDirectory($basePath);
    },
);

it('commits a transaction through the proxied connection', function (): void {
    ['container' => $container, 'basePath' => $basePath] = makeDebugbarTransactionContainer();
    $connection = $container->get(ConnectionInterface::class);
    $connection->execute('CREATE TABLE notes (id INTEGER PRIMARY KEY, body TEXT)');

    $transactions = $container->get(TransactionInterface::class);

    $transactions->transaction(
        fn (): int => $connection->execute('INSERT INTO notes (body) VALUES (?)', ['committed']),
    );

    try {
        $transactions->transaction(function () use ($connection): never {
            $connection->execute('INSERT INTO notes (body) VALUES (?)', ['rolled back']);

            throw new RuntimeException('abort');
        });
    } catch (RuntimeException) {
        // The second transaction is rolled back; only the first row may remain.
    }

    expect($connection->query('SELECT body FROM notes'))->toBe([['body' => 'committed']]);

    removeDebugbarTransactionDirectory($basePath);
});

it('records in the debugbar the queries a transaction runs through the proxied connection', function (): void {
    ['container' => $container, 'debugbar' => $debugbar, 'basePath' => $basePath] = makeDebugbarTransactionContainer();
    $connection = $container->get(ConnectionInterface::class);
    $connection->execute('CREATE TABLE notes (id INTEGER PRIMARY KEY, body TEXT)');

    $container->get(TransactionInterface::class)->transaction(function () use ($connection): void {
        $connection->execute('INSERT INTO notes (body) VALUES (?)', ['recorded']);
        $connection->query('SELECT body FROM notes');
    });

    $sql = array_map(fn (QueryRecord $query): string => $query->sql, $debugbar->queries());

    expect($sql)->toContain('INSERT INTO notes (body) VALUES (?)')
        ->toContain('SELECT body FROM notes');

    removeDebugbarTransactionDirectory($basePath);
});
