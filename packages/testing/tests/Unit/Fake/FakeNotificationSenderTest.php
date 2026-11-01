<?php

declare(strict_types=1);

use Marko\Notification\Contracts\NotificationInterface;
use Marko\Notification\Contracts\NotificationSenderInterface;
use Marko\Testing\Exceptions\AssertionFailedException;
use Marko\Testing\Fake\FakeNotificationSender;
use Marko\Testing\Tests\Unit\Fake\NotificationFixtures\FixtureInvoicePaid;
use Marko\Testing\Tests\Unit\Fake\NotificationFixtures\FixtureNotifiable;
use Marko\Testing\Tests\Unit\Fake\NotificationFixtures\FixtureOrderShipped;

it('implements NotificationSenderInterface', function (): void {
    expect(new FakeNotificationSender())->toBeInstanceOf(NotificationSenderInterface::class);
});

it('records one entry per notifiable with the channels a real send would use', function (): void {
    $fake = new FakeNotificationSender();
    $alice = new FixtureNotifiable(1);
    $bob = new FixtureNotifiable(2);
    $notification = new FixtureOrderShipped();

    $fake->send([$alice, $bob], $notification);

    expect($fake->notifications)->toBe([
        ['notifiable' => $alice, 'notification' => $notification, 'channels' => ['mail', 'database'], 'queued' => false],
        ['notifiable' => $bob, 'notification' => $notification, 'channels' => ['database'], 'queued' => false],
    ]);
});

it('records queued notifications as queued', function (): void {
    $fake = new FakeNotificationSender();
    $alice = new FixtureNotifiable(1);

    $fake->queue($alice, new FixtureOrderShipped());

    expect($fake->notifications)->toHaveCount(1)
        ->and($fake->notifications[0]['queued'])->toBeTrue()
        ->and($fake->notifications[0]['channels'])->toBe(['mail', 'database']);
});

it('passes assertSentTo when the notification was sent to the notifiable', function (): void {
    $fake = new FakeNotificationSender();
    $notification = new FixtureOrderShipped();
    $fake->send(new FixtureNotifiable(1), $notification);

    $fake->assertSentTo(new FixtureNotifiable(1), FixtureOrderShipped::class);
})->throwsNoExceptions();

it('fails assertSentTo when nothing was sent to the notifiable', function (): void {
    $fake = new FakeNotificationSender();
    $notification = new FixtureOrderShipped();
    $fake->send(new FixtureNotifiable(2), $notification);

    expect(fn () => $fake->assertSentTo(new FixtureNotifiable(1), FixtureOrderShipped::class))
        ->toThrow(AssertionFailedException::class, 'App\\User#1');
});

it('fails assertSentTo when a different notification class was sent', function (): void {
    $fake = new FakeNotificationSender();
    $other = new FixtureInvoicePaid();
    $fake->send(new FixtureNotifiable(1), $other);

    expect(fn () => $fake->assertSentTo(new FixtureNotifiable(1), FixtureOrderShipped::class))
        ->toThrow(AssertionFailedException::class);
});

it('matches notifiables by type and id rather than object identity', function (): void {
    $fake = new FakeNotificationSender();
    $notification = new FixtureOrderShipped();
    $fake->send(new FixtureNotifiable(5), $notification);

    $fake->assertSentTo(new FixtureNotifiable('5'), FixtureOrderShipped::class);

    expect(fn () => $fake->assertSentTo(new FixtureNotifiable(5, 'App\\Team'), FixtureOrderShipped::class))
        ->toThrow(AssertionFailedException::class)
        ->and(fn () => $fake->assertSentTo(new FixtureNotifiable(6), FixtureOrderShipped::class))
        ->toThrow(AssertionFailedException::class);
});

it('passes the notification and its channels to the assertion filter', function (): void {
    $fake = new FakeNotificationSender();
    $notification = new FixtureOrderShipped('TRK-42');
    $fake->send(new FixtureNotifiable(1), $notification);

    $received = [];
    $fake->assertSentTo(
        new FixtureNotifiable(1),
        FixtureOrderShipped::class,
        function (NotificationInterface $sent, array $channels) use (&$received): bool {
            $received = [$sent, $channels];

            return true;
        },
    );

    expect($received)->toBe([$notification, ['mail', 'database']])
        ->and(fn () => $fake->assertSentTo(
            new FixtureNotifiable(1),
            FixtureOrderShipped::class,
            fn (FixtureOrderShipped $sent): bool => $sent->trackingNumber === 'TRK-99',
        ))->toThrow(AssertionFailedException::class, 'matching the filter')
        ->and(fn () => $fake->assertSentTo(
            new FixtureNotifiable(2),
            FixtureOrderShipped::class,
            fn (NotificationInterface $sent, array $channels): bool => in_array('mail', $channels, true),
        ))->toThrow(AssertionFailedException::class);
});

it('passes assertNotSentTo when the notification went to someone else', function (): void {
    $fake = new FakeNotificationSender();
    $notification = new FixtureOrderShipped();
    $fake->send(new FixtureNotifiable(2), $notification);

    $fake->assertNotSentTo(new FixtureNotifiable(1), FixtureOrderShipped::class);
})->throwsNoExceptions();

it('fails assertNotSentTo when the notification was sent to the notifiable', function (): void {
    $fake = new FakeNotificationSender();
    $notification = new FixtureOrderShipped();
    $fake->send(new FixtureNotifiable(1), $notification);

    expect(fn () => $fake->assertNotSentTo(new FixtureNotifiable(1), FixtureOrderShipped::class))
        ->toThrow(AssertionFailedException::class, 'sent notifications');
});

it('fails assertNotSentTo when the notification was queued to the notifiable', function (): void {
    $fake = new FakeNotificationSender();
    $notification = new FixtureOrderShipped();
    $fake->queue(new FixtureNotifiable(1), $notification);

    expect(fn () => $fake->assertNotSentTo(new FixtureNotifiable(1), FixtureOrderShipped::class))
        ->toThrow(AssertionFailedException::class, 'queued notifications');
});

it('applies the filter in assertNotSentTo', function (): void {
    $fake = new FakeNotificationSender();
    $notification = new FixtureOrderShipped('TRK-1');
    $fake->send(new FixtureNotifiable(1), $notification);

    $fake->assertNotSentTo(
        new FixtureNotifiable(1),
        FixtureOrderShipped::class,
        fn (FixtureOrderShipped $sent): bool => $sent->trackingNumber === 'TRK-2',
    );

    expect(fn () => $fake->assertNotSentTo(
        new FixtureNotifiable(1),
        FixtureOrderShipped::class,
        fn (FixtureOrderShipped $sent): bool => $sent->trackingNumber === 'TRK-1',
    ))->toThrow(AssertionFailedException::class);
});

it('keeps sent and queued apart', function (): void {
    $fake = new FakeNotificationSender();
    $notification = new FixtureOrderShipped();
    $fake->queue(new FixtureNotifiable(1), $notification);
    $fake->send(new FixtureNotifiable(2), $notification);

    $fake->assertQueuedTo(new FixtureNotifiable(1), FixtureOrderShipped::class);
    $fake->assertSentTo(new FixtureNotifiable(2), FixtureOrderShipped::class);

    expect(fn () => $fake->assertSentTo(new FixtureNotifiable(1), FixtureOrderShipped::class))
        ->toThrow(AssertionFailedException::class)
        ->and(fn () => $fake->assertQueuedTo(new FixtureNotifiable(2), FixtureOrderShipped::class))
        ->toThrow(AssertionFailedException::class, 'queued notifications');
});

it('applies the filter in assertQueuedTo', function (): void {
    $fake = new FakeNotificationSender();
    $notification = new FixtureOrderShipped('TRK-7');
    $fake->queue(new FixtureNotifiable(2), $notification);

    $fake->assertQueuedTo(
        new FixtureNotifiable(2),
        FixtureOrderShipped::class,
        fn (NotificationInterface $sent, array $channels): bool => $channels === ['database'],
    );

    expect(fn () => $fake->assertQueuedTo(
        new FixtureNotifiable(2),
        FixtureOrderShipped::class,
        fn (NotificationInterface $sent, array $channels): bool => in_array('mail', $channels, true),
    ))->toThrow(AssertionFailedException::class);
});

it('passes assertNothingSent when nothing was sent or queued', function (): void {
    new FakeNotificationSender()->assertNothingSent();
})->throwsNoExceptions();

it('fails assertNothingSent when a notification was sent', function (): void {
    $fake = new FakeNotificationSender();
    $fake->send(new FixtureNotifiable(1), new FixtureOrderShipped());

    expect(fn () => $fake->assertNothingSent())->toThrow(AssertionFailedException::class);
});

it('fails assertNothingSent when a notification was queued', function (): void {
    $fake = new FakeNotificationSender();
    $fake->queue(new FixtureNotifiable(1), new FixtureOrderShipped());

    expect(fn () => $fake->assertNothingSent())->toThrow(AssertionFailedException::class);
});

it('counts sent notifications per notifiable in assertSentTimes', function (): void {
    $fake = new FakeNotificationSender();
    $notification = new FixtureOrderShipped();
    $fake->send([new FixtureNotifiable(1), new FixtureNotifiable(2)], $notification);
    $fake->send(new FixtureNotifiable(3), $notification);
    $fake->queue(new FixtureNotifiable(4), $notification);

    $fake->assertSentTimes(FixtureOrderShipped::class, 3);

    expect(fn () => $fake->assertSentTimes(FixtureOrderShipped::class, 4))
        ->toThrow(AssertionFailedException::class, 'Expected 4')
        ->and(fn () => $fake->assertSentTimes(FixtureOrderShipped::class, 2))
        ->toThrow(AssertionFailedException::class);
});

it('passes assertSentTimes with zero when the class was never sent', function (): void {
    new FakeNotificationSender()->assertSentTimes(FixtureOrderShipped::class, 0);
})->throwsNoExceptions();

it('clears every recorded notification', function (): void {
    $fake = new FakeNotificationSender();
    $fake->send(new FixtureNotifiable(1), new FixtureOrderShipped());
    $fake->queue(new FixtureNotifiable(2), new FixtureOrderShipped());

    $fake->clear();

    expect($fake->notifications)->toBe([]);
    $fake->assertNothingSent();
});
