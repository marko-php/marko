<?php

declare(strict_types=1);

use Marko\Notification\Contracts\ChannelInterface;
use Marko\Notification\Exceptions\NotificationException;
use Marko\Notification\NotificationManager;

test('it registers a channel by name', function (): void {
    $manager = new NotificationManager();
    $channel = $this->createMock(ChannelInterface::class);

    $manager->register('mail', $channel);

    expect($manager->hasChannel('mail'))->toBeTrue();
});

test('it resolves a registered channel by name', function (): void {
    $manager = new NotificationManager();
    $channel = $this->createMock(ChannelInterface::class);

    $manager->register('mail', $channel);

    expect($manager->channel('mail'))->toBe($channel);
});

test('it throws NotificationException for unknown channel name', function (): void {
    $manager = new NotificationManager();

    $manager->channel('sms');
})->throws(NotificationException::class, "Unknown notification channel 'sms'.");

test('it reports whether a channel is registered via hasChannel', function (): void {
    $manager = new NotificationManager();
    $channel = $this->createMock(ChannelInterface::class);

    expect($manager->hasChannel('mail'))->toBeFalse();

    $manager->register('mail', $channel);

    expect($manager->hasChannel('mail'))->toBeTrue()
        ->and($manager->hasChannel('database'))->toBeFalse();
});

test('it returns all registered channel names', function (): void {
    $manager = new NotificationManager();
    $mailChannel = $this->createMock(ChannelInterface::class);
    $dbChannel = $this->createMock(ChannelInterface::class);

    expect($manager->getRegisteredChannels())->toBe([]);

    $manager->register('mail', $mailChannel);
    $manager->register('database', $dbChannel);

    expect($manager->getRegisteredChannels())->toBe(['mail', 'database']);
});

test('it replaces channel when registering same name twice', function (): void {
    $manager = new NotificationManager();
    $channelA = $this->createMock(ChannelInterface::class);
    $channelB = $this->createMock(ChannelInterface::class);

    $manager->register('mail', $channelA);
    $manager->register('mail', $channelB);

    expect($manager->channel('mail'))->toBe($channelB)
        ->and($manager->getRegisteredChannels())->toBe(['mail']);
});
