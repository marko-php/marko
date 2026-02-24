<?php

declare(strict_types=1);

use Marko\Config\ConfigRepositoryInterface;
use Marko\Notification\Config\NotificationConfig;

test('it loads default channels from config', function (): void {
    $configRepo = $this->createMock(ConfigRepositoryInterface::class);
    $configRepo->method('getArray')
        ->with('notification.channels')
        ->willReturn(['mail', 'database']);

    $config = new NotificationConfig($configRepo);

    expect($config->channels())->toBe(['mail', 'database']);
});

test('it returns empty array when no channels configured', function (): void {
    $configRepo = $this->createMock(ConfigRepositoryInterface::class);
    $configRepo->method('getArray')
        ->with('notification.channels')
        ->willReturn([]);

    $config = new NotificationConfig($configRepo);

    expect($config->channels())->toBe([]);
});
