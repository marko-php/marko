<?php

declare(strict_types=1);

use Marko\Notification\Contracts\ChannelInterface;
use Marko\Notification\Contracts\NotifiableInterface;
use Marko\Notification\Contracts\NotificationInterface;
use Marko\Notification\Exceptions\ChannelException;
use Marko\Notification\Exceptions\NotificationException;
use Marko\Notification\Job\SendNotificationJob;
use Marko\Notification\NotificationManager;
use Marko\Notification\NotificationSender;
use Marko\Queue\QueueInterface;

test('it sends notification to single notifiable across declared channels', function (): void {
    $notifiable = $this->createMock(NotifiableInterface::class);

    $mailChannel = $this->createMock(ChannelInterface::class);
    $mailChannel->expects($this->once())
        ->method('send')
        ->with($notifiable, $this->isInstanceOf(NotificationInterface::class));

    $dbChannel = $this->createMock(ChannelInterface::class);
    $dbChannel->expects($this->once())
        ->method('send')
        ->with($notifiable, $this->isInstanceOf(NotificationInterface::class));

    $manager = new NotificationManager();
    $manager->register('mail', $mailChannel);
    $manager->register('database', $dbChannel);

    $notification = $this->createMock(NotificationInterface::class);
    $notification->method('channels')->willReturn(['mail', 'database']);

    $sender = new NotificationSender($manager);
    $sender->send($notifiable, $notification);
});

test('it sends notification to multiple notifiables', function (): void {
    $notifiableA = $this->createMock(NotifiableInterface::class);
    $notifiableB = $this->createMock(NotifiableInterface::class);

    $channel = $this->createMock(ChannelInterface::class);
    $channel->expects($this->exactly(2))->method('send');

    $manager = new NotificationManager();
    $manager->register('mail', $channel);

    $notification = $this->createMock(NotificationInterface::class);
    $notification->method('channels')->willReturn(['mail']);

    $sender = new NotificationSender($manager);
    $sender->send([$notifiableA, $notifiableB], $notification);
});

test('it resolves channels from notification for each notifiable', function (): void {
    $notifiableA = $this->createMock(NotifiableInterface::class);
    $notifiableB = $this->createMock(NotifiableInterface::class);

    $mailChannel = $this->createMock(ChannelInterface::class);
    $mailChannel->expects($this->once())->method('send')->with($notifiableA, $this->anything());

    $dbChannel = $this->createMock(ChannelInterface::class);
    $dbChannel->expects($this->once())->method('send')->with($notifiableB, $this->anything());

    $manager = new NotificationManager();
    $manager->register('mail', $mailChannel);
    $manager->register('database', $dbChannel);

    $notification = $this->createMock(NotificationInterface::class);
    $notification->method('channels')
        ->willReturnCallback(fn (NotifiableInterface $n) => match (true) {
            $n === $notifiableA => ['mail'],
            $n === $notifiableB => ['database'],
        });

    $sender = new NotificationSender($manager);
    $sender->send([$notifiableA, $notifiableB], $notification);
});

test('it throws NotificationException when notification declares unknown channel', function (): void {
    $notifiable = $this->createMock(NotifiableInterface::class);

    $manager = new NotificationManager();

    $notification = $this->createMock(NotificationInterface::class);
    $notification->method('channels')->willReturn(['sms']);

    $sender = new NotificationSender($manager);
    $sender->send($notifiable, $notification);
})->throws(NotificationException::class, "Unknown notification channel 'sms'.");

test('it throws NotificationException when queue is not available and queue() is called', function (): void {
    $notifiable = $this->createMock(NotifiableInterface::class);
    $notification = $this->createMock(NotificationInterface::class);

    $manager = new NotificationManager();
    $sender = new NotificationSender($manager);

    $sender->queue($notifiable, $notification);
})->throws(NotificationException::class, 'No queue implementation available');

test('it queues notification via QueueInterface when available', function (): void {
    $notifiable = $this->createMock(NotifiableInterface::class);
    $notification = $this->createMock(NotificationInterface::class);

    $queue = $this->createMock(QueueInterface::class);
    $queue->expects($this->once())
        ->method('push')
        ->with($this->isInstanceOf(SendNotificationJob::class))
        ->willReturn('job-id-123');

    $manager = new NotificationManager();
    $sender = new NotificationSender($manager, $queue);

    $sender->queue($notifiable, $notification);
});

test('it wraps channel delivery failures in ChannelException', function (): void {
    $notifiable = $this->createMock(NotifiableInterface::class);

    $channel = $this->createMock(ChannelInterface::class);
    $channel->method('send')->willThrowException(
        ChannelException::deliveryFailed('mail', 'Connection refused'),
    );

    $manager = new NotificationManager();
    $manager->register('mail', $channel);

    $notification = $this->createMock(NotificationInterface::class);
    $notification->method('channels')->willReturn(['mail']);

    $sender = new NotificationSender($manager);
    $sender->send($notifiable, $notification);
})->throws(ChannelException::class, "Failed to deliver notification via 'mail' channel.");
