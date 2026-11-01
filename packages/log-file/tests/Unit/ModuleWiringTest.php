<?php

declare(strict_types=1);

use Marko\Config\ConfigRepositoryInterface;
use Marko\Core\Container\Container;
use Marko\Core\Container\ContainerInterface;
use Marko\Core\Container\PreferenceRegistry;
use Marko\Log\Contracts\LogFormatterInterface;
use Marko\Log\Contracts\LoggerInterface;
use Marko\Log\File\Handler\FileLogHandler;
use Marko\Log\File\Rotation\DailyRotation;
use Marko\Log\File\Rotation\SizeRotation;
use Marko\Log\Formatter\LineFormatter;
use Marko\Testing\Fake\FakeClock;
use Marko\Testing\Fake\FakeConfigRepository;
use Psr\Clock\ClockInterface;

/**
 * A container wired like a booted app: marko/log's module and config, then marko/log-file's.
 */
function logFileContainer(
    string $dir,
    PreferenceRegistry $preferences = new PreferenceRegistry(),
): Container {
    $packageRoot = dirname(__DIR__, 2);
    $logModule = require $packageRoot . '/../log/module.php';
    $fileModule = require $packageRoot . '/module.php';
    $fileConfig = require $packageRoot . '/config/log.php';
    $container = new Container($preferences);
    $container->instance(ContainerInterface::class, $container);
    $container->instance(ConfigRepositoryInterface::class, new FakeConfigRepository([
        'log.handlers' => ['file'],
        'log.handler_map' => $fileConfig['handler_map'],
        'log.path' => $dir,
        'log.channel' => 'app',
        'log.level' => 'debug',
        'log.file_mode' => 0600,
        'log.dir_mode' => 0700,
    ]));
    $container->instance(LogFormatterInterface::class, new LineFormatter());
    $container->instance(ClockInterface::class, new FakeClock('2026-01-21 09:30:15'));

    foreach ([...$logModule['bindings'], ...$fileModule['bindings']] as $abstract => $concrete) {
        $container->bind($abstract, $concrete);
    }

    return $container;
}

it('contributes the file handler to log.handler_map', function () {
    $config = require dirname(__DIR__, 2) . '/config/log.php';

    expect($config)->toBe(['handler_map' => ['file' => FileLogHandler::class]]);
});

it('does not bind LoggerInterface, so other handler packages can be installed alongside', function () {
    $module = require dirname(__DIR__, 2) . '/module.php';

    expect($module['bindings'])->not->toHaveKey(LoggerInterface::class);
});

it('resolves a logger that stamps and rotates on the bound clock', function () {
    $dir = sys_get_temp_dir() . '/marko-log-module-' . bin2hex(random_bytes(8));

    logFileContainer($dir)->get(LoggerInterface::class)->info('Wired');
    $file = $dir . '/app-2026-01-21.log';
    $content = file_get_contents($file);

    unlink($file);
    rmdir($dir);

    expect($content)->toContain('[2026-01-21 09:30:15] app.INFO: Wired');
});

it('passes the configured file and directory modes to the handler', function () {
    $parent = sys_get_temp_dir() . '/marko-log-module-' . bin2hex(random_bytes(8));
    $dir = $parent . '/logs';
    $container = logFileContainer($dir);
    $container->instance(ConfigRepositoryInterface::class, new FakeConfigRepository([
        'log.handlers' => ['file'],
        'log.handler_map' => ['file' => FileLogHandler::class],
        'log.path' => $dir,
        'log.channel' => 'app',
        'log.level' => 'debug',
        'log.file_mode' => 0640,
        'log.dir_mode' => 0750,
    ]));

    $container->get(LoggerInterface::class)->info('Hello');
    $file = $dir . '/app-2026-01-21.log';
    clearstatcache();
    $fileMode = fileperms($file) & 0777;
    $dirMode = fileperms($dir) & 0777;

    unlink($file);
    rmdir($dir);
    rmdir($parent);

    expect($fileMode)->toBe(0640)
        ->and($dirMode)->toBe(0750);
});

it('applies a Preference that replaces DailyRotation', function () {
    $dir = sys_get_temp_dir() . '/marko-log-module-' . bin2hex(random_bytes(8));
    $preferences = new PreferenceRegistry();
    $preferences->register(DailyRotation::class, SizeRotation::class);

    logFileContainer($dir, $preferences)->get(LoggerInterface::class)->info('Sized');
    $exists = is_file($dir . '/app.log');

    array_map(unlink(...), glob($dir . '/*'));
    rmdir($dir);

    expect($exists)->toBeTrue();
});
