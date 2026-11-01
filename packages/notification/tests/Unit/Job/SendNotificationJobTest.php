<?php

declare(strict_types=1);

use Marko\Core\Container\Container;
use Marko\Mail\Message;
use Marko\Notification\Contracts\ChannelInterface;
use Marko\Notification\Contracts\NotifiableInterface;
use Marko\Notification\Contracts\NotificationInterface;
use Marko\Notification\Contracts\NotificationSenderInterface;
use Marko\Notification\Job\SendNotificationJob;
use Marko\Notification\NotificationManager;
use Marko\Notification\NotificationSender;
use Marko\Testing\Fake\FakeNotificationSender;

it('delivers through the concrete NotificationSender even when the interface is bound to a fake', function (): void {
    $delivered = new ArrayObject();
    $channel = new readonly class ($delivered) implements ChannelInterface
    {
        public function __construct(
            private ArrayObject $delivered,
        ) {}

        public function send(
            NotifiableInterface $notifiable,
            NotificationInterface $notification,
        ): void {
            $this->delivered->append($notifiable);
        }
    };

    $manager = new NotificationManager();
    $manager->register('mail', $channel);

    $fake = new FakeNotificationSender();
    $container = new Container();
    $container->instance(NotificationSender::class, new NotificationSender($manager));
    $container->instance(NotificationSenderInterface::class, $fake);

    $notifiable = new readonly class () implements NotifiableInterface
    {
        public function routeNotificationFor(
            string $channel,
        ): mixed {
            return 'user@example.com';
        }

        public function getNotifiableId(): string|int
        {
            return 1;
        }

        public function getNotifiableType(): string
        {
            return 'App\\User';
        }
    };

    $notification = new readonly class () implements NotificationInterface
    {
        public function channels(
            NotifiableInterface $notifiable,
        ): array {
            return ['mail'];
        }

        public function toMail(
            NotifiableInterface $notifiable,
        ): Message {
            return Message::create();
        }

        public function toDatabase(
            NotifiableInterface $notifiable,
        ): array {
            return [];
        }
    };

    $job = new SendNotificationJob($notifiable, $notification);
    $job->setContainer($container);
    $job->handle();

    expect($delivered->getArrayCopy())->toBe([$notifiable]);
    $fake->assertNothingSent();
});
