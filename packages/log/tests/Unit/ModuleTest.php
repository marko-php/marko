<?php

declare(strict_types=1);

use Marko\Config\ConfigRepositoryInterface;
use Marko\Core\Container\Container;
use Marko\Core\Container\ContainerInterface;
use Marko\Core\Container\PreferenceRegistry;
use Marko\Log\Contracts\LogFormatterInterface;
use Marko\Log\Contracts\LoggerInterface;
use Marko\Log\Formatter\JsonFormatter;
use Marko\Log\Formatter\LineFormatter;
use Marko\Log\Logger;
use Marko\Log\Tests\Fixtures\RecordingHandler;
use Marko\Testing\Fake\FakeClock;
use Marko\Testing\Fake\FakeConfigRepository;
use Psr\Clock\ClockInterface;

function logModuleContainer(
    array $config,
): Container {
    $module = require dirname(__DIR__, 2) . '/module.php';
    $container = new Container(new PreferenceRegistry());
    $container->instance(ContainerInterface::class, $container);
    $container->instance(ConfigRepositoryInterface::class, new FakeConfigRepository([
        'log.channel' => 'app',
        'log.level' => 'debug',
        'log.formatter' => 'line',
        'log.format' => '[{datetime}] {channel}.{level}: {message} {context}',
        'log.date_format' => 'Y-m-d H:i:s',
        'log.escape_newlines' => true,
        'log.redact_keys' => ['ssn'],
        ...$config,
    ]));
    $container->instance(ClockInterface::class, new FakeClock('2026-01-21 09:30:15'));

    foreach ($module['bindings'] as $abstract => $concrete) {
        $container->bind($abstract, $concrete);
    }

    return $container;
}

it('binds LoggerInterface to the shared Logger built from the configured handlers', function (): void {
    $handler = new RecordingHandler();
    $container = logModuleContainer([
        'log.handlers' => ['recording'],
        'log.handler_map' => ['recording' => RecordingHandler::class],
    ]);
    $container->instance(RecordingHandler::class, $handler);

    $logger = $container->get(LoggerInterface::class);
    $logger->info('Wired');

    expect($logger)->toBeInstanceOf(Logger::class)
        ->and($handler->records)->toHaveCount(1)
        ->and($handler->records[0]->message)->toBe('Wired');
});

it('binds LogFormatterInterface to the formatter named in log.formatter', function (): void {
    expect(logModuleContainer(['log.formatter' => 'line'])->get(LogFormatterInterface::class))
        ->toBeInstanceOf(LineFormatter::class)
        ->and(logModuleContainer(['log.formatter' => 'json'])->get(LogFormatterInterface::class))
        ->toBeInstanceOf(JsonFormatter::class);
});
