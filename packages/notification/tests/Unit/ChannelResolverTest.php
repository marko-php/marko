<?php

declare(strict_types=1);

use Marko\Notification\ChannelResolver;
use Marko\Notification\Contracts\NotifiableInterface;
use Marko\Notification\Contracts\NotificationInterface;

it('maps each declared channel to the notifiables that declared it', function (): void {
    $alice = $this->createStub(NotifiableInterface::class);
    $bob = $this->createStub(NotifiableInterface::class);

    $notification = $this->createStub(NotificationInterface::class);
    $notification->method('channels')->willReturnCallback(
        fn (NotifiableInterface $notifiable): array => $notifiable === $alice ? ['mail', 'database'] : ['database'],
    );

    $resolved = new ChannelResolver()->resolve([$alice, $bob], $notification);

    expect($resolved)->toBe([
        'mail' => [$alice],
        'database' => [$alice, $bob],
    ]);
});

it('returns an empty map when no notifiable declares a channel', function (): void {
    $notifiable = $this->createStub(NotifiableInterface::class);

    $notification = $this->createStub(NotificationInterface::class);
    $notification->method('channels')->willReturn([]);

    expect(new ChannelResolver()->resolve([$notifiable], $notification))->toBe([])
        ->and(new ChannelResolver()->resolve([], $notification))->toBe([]);
});
