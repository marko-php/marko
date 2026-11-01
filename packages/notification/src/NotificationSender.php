<?php

declare(strict_types=1);

namespace Marko\Notification;

use Marko\Notification\Contracts\BatchChannelInterface;
use Marko\Notification\Contracts\NotifiableInterface;
use Marko\Notification\Contracts\NotificationInterface;
use Marko\Notification\Contracts\NotificationSenderInterface;
use Marko\Notification\Exceptions\ChannelException;
use Marko\Notification\Exceptions\NotificationException;
use Marko\Notification\Job\SendNotificationJob;
use Marko\Queue\QueueInterface;
use Throwable;

readonly class NotificationSender implements NotificationSenderInterface
{
    public function __construct(
        private NotificationManager $manager,
        private ?QueueInterface $queue = null,
        private ChannelResolver $channelResolver = new ChannelResolver(),
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

        $channelRecipients = $this->channelResolver->resolve($notifiables, $notification);

        // For each channel, use batch path when supported and multiple recipients; otherwise per-recipient.
        foreach ($channelRecipients as $channelName => $recipients) {
            $channel = $this->manager->channel($channelName);

            if ($channel instanceof BatchChannelInterface && count($recipients) > 1) {
                try {
                    $channel->sendMany($recipients, $notification);
                } catch (ChannelException $e) {
                    throw $e;
                } catch (Throwable $e) {
                    throw NotificationException::sendFailed($channelName, $e->getMessage());
                }
            } else {
                foreach ($recipients as $notifiable) {
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

        $job = new SendNotificationJob($notifiables, $notification);
        $this->queue->push($job);
    }
}
