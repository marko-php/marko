<?php

declare(strict_types=1);

use Marko\Database\Config\DatabaseTimezoneConfig;
use Marko\Database\Sqlite\Tests\Fixtures\SqliteMigrations;
use Marko\Database\Sqlite\Tests\Fixtures\SqliteTestDatabase;
use Marko\Notification\Channel\DatabaseChannel;
use Marko\Notification\Contracts\NotifiableInterface;
use Marko\Notification\Contracts\NotificationInterface;
use Marko\Notification\Database\Entity\DatabaseNotification;
use Marko\Notification\Database\Repository\DatabaseNotificationRepository;
use Marko\Testing\Fake\FakeClock;

/*
 * The notifications table built from the entity by SqliteGenerator, written by DatabaseChannel and read back
 * through DatabaseNotificationRepository on a real SQLite connection.
 */

dataset('notification sqlite kinds', [SqliteTestDatabase::FILE, SqliteTestDatabase::MEMORY]);

function sqliteNotifiable(
    int $id,
): NotifiableInterface {
    return new readonly class ($id) implements NotifiableInterface
    {
        public function __construct(
            private int $id,
        ) {}

        public function routeNotificationFor(
            string $channel,
        ): mixed {
            return null;
        }

        public function getNotifiableId(): string|int
        {
            return $this->id;
        }

        public function getNotifiableType(): string
        {
            return 'App\\Entity\\User';
        }
    };
}

function sqliteNotification(): NotificationInterface
{
    return new class () implements NotificationInterface
    {
        public function channels(
            NotifiableInterface $notifiable,
        ): array {
            return ['database'];
        }

        public function toMail(
            NotifiableInterface $notifiable,
        ): never {
            throw new LogicException('Not a mail notification');
        }

        public function toDatabase(
            NotifiableInterface $notifiable,
        ): array {
            return ['order_id' => 7];
        }
    };
}

it('stores and marks database notifications read on sqlite', function (string $kind): void {
    SqliteTestDatabase::using($kind, function (SqliteTestDatabase $database): void {
        SqliteMigrations::migrate($database, SqliteMigrations::schema(new DatabaseNotification()));

        $clock = new FakeClock('2026-03-01 10:00:00 UTC');
        $timezone = DatabaseTimezoneConfig::fromName('UTC');
        $channel = new DatabaseChannel($database->connection, $clock, $timezone);
        $repository = new DatabaseNotificationRepository($database->connection, $clock, $timezone);
        $alice = sqliteNotifiable(1);
        $bob = sqliteNotifiable(2);

        $channel->send($alice, sqliteNotification());
        $channel->sendMany([$alice, $bob], sqliteNotification());

        $stored = $repository->forNotifiable($alice);

        expect($stored)->toHaveCount(2)
            ->and($stored[0]->data)->toBe('{"order_id":7}')
            ->and($stored[0]->createdAt)->toBe('2026-03-01 10:00:00')
            ->and($repository->unreadCount($alice))->toBe(2)
            ->and($repository->unreadCount($bob))->toBe(1);

        $clock->setNow('2026-03-01 11:00:00 UTC');

        expect($repository->markAsReadFor($alice, $stored[0]->id))->toBeTrue()
            ->and($repository->markAsReadFor($bob, $stored[1]->id))->toBeFalse()
            ->and($repository->unreadCount($alice))->toBe(1)
            ->and($repository->unread($alice))->toHaveCount(1);

        $clock->setNow('2026-03-01 12:00:00 UTC');

        expect($repository->markAsReadFor($alice, $stored[0]->id))->toBeTrue();

        $repository->markAllAsRead($alice);
        $read = $repository->forNotifiable($alice);

        expect($repository->unreadCount($alice))->toBe(0)
            ->and($repository->unreadCount($bob))->toBe(1)
            ->and(array_map(fn (DatabaseNotification $n): ?string => $n->readAt, $read))
            ->toContain('2026-03-01 11:00:00', '2026-03-01 12:00:00');

        expect($repository->deleteFor($alice, $stored[0]->id))->toBeTrue();
        $repository->deleteAll($bob);

        expect($repository->forNotifiable($alice))->toHaveCount(1)
            ->and($repository->forNotifiable($bob))->toBeEmpty();
    });
})->with('notification sqlite kinds');
