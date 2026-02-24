<?php

declare(strict_types=1);

use Marko\Database\Connection\ConnectionInterface;
use Marko\Notification\Channel\DatabaseChannel;
use Marko\Notification\Contracts\ChannelInterface;
use Marko\Notification\Contracts\NotifiableInterface;
use Marko\Notification\Contracts\NotificationInterface;
use Marko\Notification\Exceptions\ChannelException;

test('it implements ChannelInterface', function (): void {
    $reflection = new ReflectionClass(DatabaseChannel::class);

    expect($reflection->implementsInterface(ChannelInterface::class))->toBeTrue();
});

test('it inserts notification record into database', function (): void {
    $capturedSql = null;
    $capturedBindings = null;

    $connection = $this->createMock(ConnectionInterface::class);
    $connection->expects($this->once())
        ->method('execute')
        ->willReturnCallback(function (string $sql, array $bindings) use (&$capturedSql, &$capturedBindings) {
            $capturedSql = $sql;
            $capturedBindings = $bindings;

            return 1;
        });

    $notifiable = $this->createMock(NotifiableInterface::class);
    $notifiable->method('getNotifiableType')->willReturn('App\\Entity\\User');
    $notifiable->method('getNotifiableId')->willReturn(42);

    $notification = $this->createMock(NotificationInterface::class);
    $notification->method('toDatabase')->willReturn(['message' => 'Hello']);

    $channel = new DatabaseChannel($connection);
    $channel->send($notifiable, $notification);

    expect($capturedSql)->toContain('INSERT INTO notifications')
        ->and($capturedBindings)->toBeArray()
        ->and($capturedBindings)->toHaveCount(7);
});

test('it stores notification type as class name', function (): void {
    $capturedBindings = null;

    $connection = $this->createMock(ConnectionInterface::class);
    $connection->method('execute')
        ->willReturnCallback(function (string $sql, array $bindings) use (&$capturedBindings) {
            $capturedBindings = $bindings;

            return 1;
        });

    $notifiable = $this->createMock(NotifiableInterface::class);
    $notifiable->method('getNotifiableType')->willReturn('App\\Entity\\User');
    $notifiable->method('getNotifiableId')->willReturn(1);

    $notification = $this->createMock(NotificationInterface::class);
    $notification->method('toDatabase')->willReturn(['key' => 'value']);

    $channel = new DatabaseChannel($connection);
    $channel->send($notifiable, $notification);

    // Index 1 is the type column - it should contain the notification class name
    expect($capturedBindings[1])->toBeString()
        ->and($capturedBindings[1])->toContain('NotificationInterface');
});

test('it stores notifiable type and id from notifiable interface', function (): void {
    $capturedBindings = null;

    $connection = $this->createMock(ConnectionInterface::class);
    $connection->method('execute')
        ->willReturnCallback(function (string $sql, array $bindings) use (&$capturedBindings) {
            $capturedBindings = $bindings;

            return 1;
        });

    $notifiable = $this->createMock(NotifiableInterface::class);
    $notifiable->method('getNotifiableType')->willReturn('App\\Entity\\User');
    $notifiable->method('getNotifiableId')->willReturn(42);

    $notification = $this->createMock(NotificationInterface::class);
    $notification->method('toDatabase')->willReturn(['key' => 'value']);

    $channel = new DatabaseChannel($connection);
    $channel->send($notifiable, $notification);

    // Index 2 = notifiable_type, Index 3 = notifiable_id
    expect($capturedBindings[2])->toBe('App\\Entity\\User')
        ->and($capturedBindings[3])->toBe('42');
});

test('it JSON-encodes notification data from toDatabase()', function (): void {
    $capturedBindings = null;

    $connection = $this->createMock(ConnectionInterface::class);
    $connection->method('execute')
        ->willReturnCallback(function (string $sql, array $bindings) use (&$capturedBindings) {
            $capturedBindings = $bindings;

            return 1;
        });

    $notifiable = $this->createMock(NotifiableInterface::class);
    $notifiable->method('getNotifiableType')->willReturn('App\\Entity\\User');
    $notifiable->method('getNotifiableId')->willReturn(1);

    $notification = $this->createMock(NotificationInterface::class);
    $notification->method('toDatabase')->willReturn([
        'message' => 'You have a new order',
        'order_id' => 123,
    ]);

    $channel = new DatabaseChannel($connection);
    $channel->send($notifiable, $notification);

    // Index 4 = data (JSON)
    $decoded = json_decode($capturedBindings[4], true);
    expect($decoded)->toBe(['message' => 'You have a new order', 'order_id' => 123]);
});

test('it sets read_at to null for new notifications', function (): void {
    $capturedBindings = null;

    $connection = $this->createMock(ConnectionInterface::class);
    $connection->method('execute')
        ->willReturnCallback(function (string $sql, array $bindings) use (&$capturedBindings) {
            $capturedBindings = $bindings;

            return 1;
        });

    $notifiable = $this->createMock(NotifiableInterface::class);
    $notifiable->method('getNotifiableType')->willReturn('App\\Entity\\User');
    $notifiable->method('getNotifiableId')->willReturn(1);

    $notification = $this->createMock(NotificationInterface::class);
    $notification->method('toDatabase')->willReturn(['key' => 'value']);

    $channel = new DatabaseChannel($connection);
    $channel->send($notifiable, $notification);

    // Index 5 = read_at
    expect($capturedBindings[5])->toBeNull();
});

test('it throws ChannelException when database insert fails', function (): void {
    $connection = $this->createMock(ConnectionInterface::class);
    $connection->method('execute')
        ->willThrowException(new RuntimeException('Connection lost'));

    $notifiable = $this->createMock(NotifiableInterface::class);
    $notifiable->method('getNotifiableType')->willReturn('App\\Entity\\User');
    $notifiable->method('getNotifiableId')->willReturn(1);

    $notification = $this->createMock(NotificationInterface::class);
    $notification->method('toDatabase')->willReturn(['key' => 'value']);

    $channel = new DatabaseChannel($connection);
    $channel->send($notifiable, $notification);
})->throws(ChannelException::class, "Failed to deliver notification via 'database' channel.");
