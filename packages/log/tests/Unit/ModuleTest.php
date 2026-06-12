<?php

declare(strict_types=1);

use Marko\Core\Container\ContainerInterface;
use Marko\Log\Config\LogConfig;
use Marko\Log\Contracts\LogFormatterInterface;
use Marko\Log\Formatter\LineFormatter;

it('wires escape_newlines from LogConfig into the LineFormatter via the log module binding', function (): void {
    $modulePath = dirname(__DIR__, 2) . '/module.php';
    $module = require $modulePath;
    $binding = $module['bindings'][LogFormatterInterface::class];

    $logConfig = $this->createMock(LogConfig::class);
    $logConfig->expects($this->once())
        ->method('format')
        ->willReturn('[{datetime}] {channel}.{level}: {message} {context}');
    $logConfig->expects($this->once())
        ->method('dateFormat')
        ->willReturn('Y-m-d H:i:s');
    $logConfig->expects($this->once())
        ->method('escapeNewlines')
        ->willReturn(false);

    $container = $this->createMock(ContainerInterface::class);
    $container->expects($this->once())
        ->method('get')
        ->with(LogConfig::class)
        ->willReturn($logConfig);

    $result = $binding($container);

    expect($result)->toBeInstanceOf(LineFormatter::class)
        ->and($result)->toBeInstanceOf(LogFormatterInterface::class);
});
