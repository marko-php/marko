<?php

declare(strict_types=1);

namespace Marko\Testing\Fake;

use Marko\Notification\ChannelResolver;
use Marko\Notification\Contracts\NotifiableInterface;
use Marko\Notification\Contracts\NotificationInterface;
use Marko\Notification\Contracts\NotificationSenderInterface;
use Marko\Testing\Exceptions\AssertionFailedException;

/**
 * Records notifications instead of delivering them.
 *
 * Bind it to NotificationSenderInterface in a test. Each notifiable gets one record holding the
 * notification, the channels a real send would use (resolved by the same ChannelResolver the real
 * NotificationSender uses) and whether it was sent or queued.
 *
 * Notifiables are matched by getNotifiableType() + getNotifiableId(), not object identity, so a
 * reloaded entity still matches the one the code under test notified.
 */
class FakeNotificationSender implements NotificationSenderInterface
{
    /** @var list<array{notifiable: NotifiableInterface, notification: NotificationInterface, channels: list<string>, queued: bool}> */
    public private(set) array $notifications = [];

    public function __construct(
        private readonly ChannelResolver $channelResolver = new ChannelResolver(),
    ) {}

    public function send(
        NotifiableInterface|array $notifiables,
        NotificationInterface $notification,
    ): void {
        $this->record($notifiables, $notification, false);
    }

    public function queue(
        NotifiableInterface|array $notifiables,
        NotificationInterface $notification,
    ): void {
        $this->record($notifiables, $notification, true);
    }

    /**
     * Records sent (not queued) to the notifiable, optionally narrowed by class and a filter.
     *
     * @param callable(NotificationInterface, list<string>): bool|null $filter
     * @return list<array{notifiable: NotifiableInterface, notification: NotificationInterface, channels: list<string>, queued: bool}>
     */
    public function sentTo(
        NotifiableInterface $notifiable,
        string $notificationClass,
        ?callable $filter = null,
    ): array {
        return $this->matching($notifiable, $notificationClass, $filter, false);
    }

    /**
     * Records queued to the notifiable, optionally narrowed by class and a filter.
     *
     * @param callable(NotificationInterface, list<string>): bool|null $filter
     * @return list<array{notifiable: NotifiableInterface, notification: NotificationInterface, channels: list<string>, queued: bool}>
     */
    public function queuedTo(
        NotifiableInterface $notifiable,
        string $notificationClass,
        ?callable $filter = null,
    ): array {
        return $this->matching($notifiable, $notificationClass, $filter, true);
    }

    /**
     * @param callable(NotificationInterface, list<string>): bool|null $filter
     * @throws AssertionFailedException
     */
    public function assertSentTo(
        NotifiableInterface $notifiable,
        string $notificationClass,
        ?callable $filter = null,
    ): void {
        if ($this->sentTo($notifiable, $notificationClass, $filter) === []) {
            throw AssertionFailedException::expectedContains(
                'sent notifications',
                self::describe($notifiable, $notificationClass, $filter),
            );
        }
    }

    /**
     * Fails when the notification was sent OR queued to the notifiable: a queued notification
     * is still delivered once the queue runs.
     *
     * @param callable(NotificationInterface, list<string>): bool|null $filter
     * @throws AssertionFailedException
     */
    public function assertNotSentTo(
        NotifiableInterface $notifiable,
        string $notificationClass,
        ?callable $filter = null,
    ): void {
        if ($this->sentTo($notifiable, $notificationClass, $filter) !== []) {
            throw AssertionFailedException::unexpectedContains(
                'sent notifications',
                self::describe($notifiable, $notificationClass, $filter),
            );
        }

        if ($this->queuedTo($notifiable, $notificationClass, $filter) !== []) {
            throw AssertionFailedException::unexpectedContains(
                'queued notifications',
                self::describe($notifiable, $notificationClass, $filter),
            );
        }
    }

    /**
     * @param callable(NotificationInterface, list<string>): bool|null $filter
     * @throws AssertionFailedException
     */
    public function assertQueuedTo(
        NotifiableInterface $notifiable,
        string $notificationClass,
        ?callable $filter = null,
    ): void {
        if ($this->queuedTo($notifiable, $notificationClass, $filter) === []) {
            throw AssertionFailedException::expectedContains(
                'queued notifications',
                self::describe($notifiable, $notificationClass, $filter),
            );
        }
    }

    /**
     * Fails when anything was sent or queued.
     *
     * @throws AssertionFailedException
     */
    public function assertNothingSent(): void
    {
        if ($this->notifications !== []) {
            throw AssertionFailedException::expectedEmpty('sent or queued notifications');
        }
    }

    /**
     * Asserts how many notifiables were sent (not queued) a notification of the given class.
     *
     * @throws AssertionFailedException
     */
    public function assertSentTimes(
        string $notificationClass,
        int $expected,
    ): void {
        $actual = count(array_filter(
            $this->notifications,
            fn (array $record): bool => !$record['queued'] && $record['notification'] instanceof $notificationClass,
        ));

        if ($actual !== $expected) {
            throw AssertionFailedException::expectedCount("sent $notificationClass notifications", $expected, $actual);
        }
    }

    public function clear(): void
    {
        $this->notifications = [];
    }

    /**
     * @param NotifiableInterface|array<NotifiableInterface> $notifiables
     */
    private function record(
        NotifiableInterface|array $notifiables,
        NotificationInterface $notification,
        bool $queued,
    ): void {
        $notifiables = is_array($notifiables) ? $notifiables : [$notifiables];

        foreach ($notifiables as $notifiable) {
            $this->notifications[] = [
                'notifiable' => $notifiable,
                'notification' => $notification,
                'channels' => array_keys($this->channelResolver->resolve([$notifiable], $notification)),
                'queued' => $queued,
            ];
        }
    }

    /**
     * @param callable(NotificationInterface, list<string>): bool|null $filter
     * @return list<array{notifiable: NotifiableInterface, notification: NotificationInterface, channels: list<string>, queued: bool}>
     */
    private function matching(
        NotifiableInterface $notifiable,
        string $notificationClass,
        ?callable $filter,
        bool $queued,
    ): array {
        return array_values(array_filter(
            $this->notifications,
            fn (array $record): bool => $record['queued'] === $queued
                && $record['notification'] instanceof $notificationClass
                && self::sameNotifiable($record['notifiable'], $notifiable)
                && ($filter === null || $filter($record['notification'], $record['channels'])),
        ));
    }

    /**
     * Type + id identify a notifiable. Ids compare as strings, so an int id and the same id read
     * back from storage as a string still match.
     */
    private static function sameNotifiable(
        NotifiableInterface $a,
        NotifiableInterface $b,
    ): bool {
        return $a->getNotifiableType() === $b->getNotifiableType()
            && (string) $a->getNotifiableId() === (string) $b->getNotifiableId();
    }

    private static function describe(
        NotifiableInterface $notifiable,
        string $notificationClass,
        ?callable $filter,
    ): string {
        $description = "[$notificationClass] for [{$notifiable->getNotifiableType()}#{$notifiable->getNotifiableId()}]";

        return $filter === null ? $description : "$description matching the filter";
    }
}
