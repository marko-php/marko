<?php

declare(strict_types=1);

use Marko\Config\ConfigDiscovery;
use Marko\Config\ConfigLoader;
use Marko\Config\ConfigMerger;
use Marko\Config\ConfigRepository;
use Marko\Config\ConfigRepositoryInterface;
use Marko\Core\Container\BindingRegistry;
use Marko\Core\Container\Container;
use Marko\Core\Container\ContainerInterface;
use Marko\Core\Container\PreferenceRegistry;
use Marko\Core\Module\ModuleManifest;
use Marko\Log\Contracts\LoggerInterface;
use Marko\Log\File\Handler\FileLogHandler;
use Marko\Log\Formatter\LineFormatter;
use Marko\Log\Stream\Handler\StreamLogHandler;
use Marko\Testing\Fake\FakeClock;
use Psr\Clock\ClockInterface;

/**
 * @return array<string, string> Package name => package path
 */
function logPackagePaths(): array
{
    $packages = dirname(__DIR__, 3);

    return [
        'marko/log' => $packages . '/log',
        'marko/log-file' => $packages . '/log-file',
        'marko/log-stream' => $packages . '/log-stream',
    ];
}

/**
 * Boots the three log packages the way the app does: merged module configs, then module bindings
 * registered at the same (vendor) priority.
 *
 * @param list<string> $order Package names in config discovery order
 */
function bootLogPackages(
    array $order,
): Container {
    $paths = logPackagePaths();
    $config = new ConfigDiscovery(new ConfigLoader(), new ConfigMerger())->discover(
        array_map(fn (string $name): string => $paths[$name], $order),
        sys_get_temp_dir() . '/marko-log-stream-no-root-config',
    );
    $container = new Container(new PreferenceRegistry());
    $container->instance(ContainerInterface::class, $container);
    $container->instance(ConfigRepositoryInterface::class, new ConfigRepository($config));
    $container->instance(ClockInterface::class, new FakeClock('2026-01-21 09:30:15'));
    $registry = new BindingRegistry($container);

    foreach ($order as $name) {
        $module = require $paths[$name] . '/module.php';
        $registry->registerModule(new ModuleManifest(
            name: $name,
            version: '1.0.0',
            bindings: $module['bindings'],
            arguments: $module['arguments'] ?? [],
            path: $paths[$name],
            source: 'vendor',
        ));
    }

    return $container;
}

beforeEach(function (): void {
    $this->env = ['LOG_HANDLERS' => getenv('LOG_HANDLERS'), 'LOG_PATH' => getenv('LOG_PATH')];
    $this->dir = sys_get_temp_dir() . '/marko-log-stream-' . bin2hex(random_bytes(8));
    putenv('LOG_HANDLERS=file,stream');
    putenv('LOG_PATH=' . $this->dir);
});

afterEach(function (): void {
    foreach ($this->env as $name => $value) {
        putenv($value === false ? $name : "$name=$value");
    }

    array_map(unlink(...), glob($this->dir . '/*') ?: []);

    if (is_dir($this->dir)) {
        rmdir($this->dir);
    }
});

it('contributes the stream handler and its default target to the log config', function (): void {
    $config = require dirname(__DIR__, 2) . '/config/log.php';

    expect($config['handler_map'])->toBe(['stream' => StreamLogHandler::class])
        ->and($config['stream']['target'])->toBe('php://stderr');
});

it(
    'boots alongside marko/log-file without a binding conflict and stacks both handlers',
    function (array $order): void {
        $container = bootLogPackages($order);
        $memory = fopen('php://memory', 'w+');
        // Swap the real php://stderr stream for memory so the test can read what the handler wrote.
        $container->instance(
            StreamLogHandler::class,
            new StreamLogHandler($memory, 'php://memory', new LineFormatter()),
        );

        $container->get(LoggerInterface::class)->warning('Both handlers');
        rewind($memory);
        $streamed = stream_get_contents($memory);
        $filed = file_get_contents($this->dir . '/app-2026-01-21.log');

        expect($streamed)->toBe("[2026-01-21 09:30:15] app.WARNING: Both handlers\n")
            ->and($filed)->toBe("[2026-01-21 09:30:15] app.WARNING: Both handlers\n");
    },
)->with([
    'base package config first' => [['marko/log', 'marko/log-file', 'marko/log-stream']],
    'base package config last' => [['marko/log-stream', 'marko/log-file', 'marko/log']],
]);

it('merges both handler names into log.handler_map', function (): void {
    $config = bootLogPackages(['marko/log', 'marko/log-file', 'marko/log-stream'])
        ->get(ConfigRepositoryInterface::class);

    expect($config->getArray('log.handler_map'))->toBe([
        'file' => FileLogHandler::class,
        'stream' => StreamLogHandler::class,
    ]);
});

it('resolves the stream handler from the configured target', function (): void {
    expect(bootLogPackages(['marko/log', 'marko/log-stream'])->get(StreamLogHandler::class))
        ->toBeInstanceOf(StreamLogHandler::class);
});
