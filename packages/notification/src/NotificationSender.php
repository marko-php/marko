<?php

declare(strict_types=1);

namespace Marko\Notification;

use Marko\Notification\Contracts\NotifiableInterface;
use Marko\Notification\Contracts\NotificationInterface;
use Marko\Notification\Exceptions\ChannelException;
use Marko\Notification\Exceptions\NotificationException;
use Marko\Notification\Job\SendNotificationJob;
use Marko\Queue\QueueInterface;
use Throwable;

class NotificationSender
{
    public function __construct(
        private NotificationManager $manager,
        private ?QueueInterface $queue = null,
    ) {}

    /**
     * Send a notification to the given notifiable(s).
     *
     * @param NotifiableInterface|array<NotifiableInterface> $notifiables
     * @throws NotificationException|ChannelException
     */
    public function send(
        NotifiableInterface|array $notifiables,
        NotificationInterface $notification,
    ): void {
        $notifiables = is_array($notifiables) ? $notifiables : [$notifiables];

        foreach ($notifiables as $notifiable) {
            $channels = $notification->channels($notifiable);

            foreach ($channels as $channelName) {
                $channel = $this->manager->channel($channelName);

                try {
                    $channel->send($notifiable, $notification);
                } catch (ChannelException $e) {
                    throw $e;
                } catch (Throwable $e) {
                    throw NotificationException::sendFailed($channelName, $e->getMessage());
                }
            }
        }
    }

    /**
     * Queue a notification for later sending.
     *
     * @param NotifiableInterface|array<NotifiableInterface> $notifiables
     * @throws NotificationException When queue is not available
     */
    public function queue(
        NotifiableInterface|array $notifiables,
        NotificationInterface $notification,
    ): void {
        if ($this->queue === null) {
            throw NotificationException::noQueueAvailable();
        }

        $job = new SendNotificationJob($this, $notifiables, $notification);
        $this->queue->push($job);
    }
}
