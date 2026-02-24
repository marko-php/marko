<?php

declare(strict_types=1);

use Marko\Database\Connection\ConnectionInterface;
use Marko\Notification\Contracts\NotifiableInterface;
use Marko\Notification\Database\Entity\DatabaseNotification;
use Marko\Notification\Database\Repository\DatabaseNotificationRepository;
use Marko\Notification\Database\Repository\NotificationRepositoryInterface;

function makeNotifiable(): NotifiableInterface
{
    return new class () implements NotifiableInterface
    {
        public function routeNotificationFor(
            string $channel,
        ): mixed {
            return match ($channel) {
                'mail' => 'user@example.com',
                'database' => ['type' => 'App\\Entity\\User', 'id' => 42],
                default => null,
            };
        }

        public function getNotifiableId(): string|int
        {
            return 42;
        }

        public function getNotifiableType(): string
        {
            return 'App\\Entity\\User';
        }
    };
}

test('it implements NotificationRepositoryInterface', function (): void {
    $reflection = new ReflectionClass(DatabaseNotificationRepository::class);

    expect($reflection->implementsInterface(NotificationRepositoryInterface::class))->toBeTrue();
});

test('it returns all notifications for a notifiable ordered by created_at desc', function (): void {
    $capturedSql = null;
    $capturedBindings = null;

    $connection = $this->createMock(ConnectionInterface::class);
    $connection->method('query')
        ->willReturnCallback(function (string $sql, array $bindings) use (&$capturedSql, &$capturedBindings) {
            $capturedSql = $sql;
            $capturedBindings = $bindings;

            return [
                [
                    'id' => 'uuid-1',
                    'type' => 'App\\Notification\\OrderShipped',
                    'notifiable_type' => 'App\\Entity\\User',
                    'notifiable_id' => '42',
                    'data' => '{"order_id":1}',
                    'read_at' => null,
                    'created_at' => '2026-02-24 10:00:00',
                ],
                [
                    'id' => 'uuid-2',
                    'type' => 'App\\Notification\\Welcome',
                    'notifiable_type' => 'App\\Entity\\User',
                    'notifiable_id' => '42',
                    'data' => '{"message":"Welcome!"}',
                    'read_at' => '2026-02-24 09:30:00',
                    'created_at' => '2026-02-24 09:00:00',
                ],
            ];
        });

    $notifiable = makeNotifiable();
    $repo = new DatabaseNotificationRepository($connection);
    $result = $repo->forNotifiable($notifiable);

    expect($capturedSql)->toContain('SELECT * FROM notifications')
        ->and($capturedSql)->toContain('notifiable_type = ?')
        ->and($capturedSql)->toContain('notifiable_id = ?')
        ->and($capturedSql)->toContain('ORDER BY created_at DESC')
        ->and($capturedBindings)->toBe(['App\\Entity\\User', '42'])
        ->and($result)->toHaveCount(2)
        ->and($result[0])->toBeInstanceOf(DatabaseNotification::class)
        ->and($result[0]->id)->toBe('uuid-1')
        ->and($result[1]->id)->toBe('uuid-2');
});

test('it returns only unread notifications for a notifiable', function (): void {
    $capturedSql = null;

    $connection = $this->createMock(ConnectionInterface::class);
    $connection->method('query')
        ->willReturnCallback(function (string $sql, array $bindings) use (&$capturedSql) {
            $capturedSql = $sql;

            return [
                [
                    'id' => 'uuid-1',
                    'type' => 'App\\Notification\\OrderShipped',
                    'notifiable_type' => 'App\\Entity\\User',
                    'notifiable_id' => '42',
                    'data' => '{"order_id":1}',
                    'read_at' => null,
                    'created_at' => '2026-02-24 10:00:00',
                ],
            ];
        });

    $notifiable = makeNotifiable();
    $repo = new DatabaseNotificationRepository($connection);
    $result = $repo->unread($notifiable);

    expect($capturedSql)->toContain('read_at IS NULL')
        ->and($capturedSql)->toContain('ORDER BY created_at DESC')
        ->and($result)->toHaveCount(1)
        ->and($result[0]->readAt)->toBeNull();
});

test('it marks a single notification as read', function (): void {
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

    $repo = new DatabaseNotificationRepository($connection);
    $repo->markAsRead('uuid-123');

    expect($capturedSql)->toContain('UPDATE notifications SET read_at')
        ->and($capturedSql)->toContain('WHERE id = ?')
        ->and($capturedBindings[1])->toBe('uuid-123');
});

test('it marks all notifications as read for a notifiable', function (): void {
    $capturedSql = null;
    $capturedBindings = null;

    $connection = $this->createMock(ConnectionInterface::class);
    $connection->expects($this->once())
        ->method('execute')
        ->willReturnCallback(function (string $sql, array $bindings) use (&$capturedSql, &$capturedBindings) {
            $capturedSql = $sql;
            $capturedBindings = $bindings;

            return 3;
        });

    $notifiable = makeNotifiable();
    $repo = new DatabaseNotificationRepository($connection);
    $repo->markAllAsRead($notifiable);

    expect($capturedSql)->toContain('UPDATE notifications SET read_at')
        ->and($capturedSql)->toContain('notifiable_type = ?')
        ->and($capturedSql)->toContain('notifiable_id = ?')
        ->and($capturedSql)->toContain('read_at IS NULL')
        ->and($capturedBindings[1])->toBe('App\\Entity\\User')
        ->and($capturedBindings[2])->toBe('42');
});

test('it deletes a single notification by id', function (): void {
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

    $repo = new DatabaseNotificationRepository($connection);
    $repo->delete('uuid-456');

    expect($capturedSql)->toContain('DELETE FROM notifications WHERE id = ?')
        ->and($capturedBindings)->toBe(['uuid-456']);
});

test('it deletes all notifications for a notifiable', function (): void {
    $capturedSql = null;
    $capturedBindings = null;

    $connection = $this->createMock(ConnectionInterface::class);
    $connection->expects($this->once())
        ->method('execute')
        ->willReturnCallback(function (string $sql, array $bindings) use (&$capturedSql, &$capturedBindings) {
            $capturedSql = $sql;
            $capturedBindings = $bindings;

            return 5;
        });

    $notifiable = makeNotifiable();
    $repo = new DatabaseNotificationRepository($connection);
    $repo->deleteAll($notifiable);

    expect($capturedSql)->toContain('DELETE FROM notifications WHERE notifiable_type = ? AND notifiable_id = ?')
        ->and($capturedBindings)->toBe(['App\\Entity\\User', '42']);
});

test('it counts unread notifications for a notifiable', function (): void {
    $capturedSql = null;

    $connection = $this->createMock(ConnectionInterface::class);
    $connection->method('query')
        ->willReturnCallback(function (string $sql, array $bindings) use (&$capturedSql) {
            $capturedSql = $sql;

            return [['count' => 7]];
        });

    $notifiable = makeNotifiable();
    $repo = new DatabaseNotificationRepository($connection);
    $count = $repo->unreadCount($notifiable);

    expect($capturedSql)->toContain('SELECT COUNT(*)')
        ->and($capturedSql)->toContain('read_at IS NULL')
        ->and($count)->toBe(7);
});
