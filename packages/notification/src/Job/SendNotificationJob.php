<?php

declare(strict_types=1);

namespace Marko\Notification\Job;

use Marko\Core\Container\ContainerInterface;
use Marko\Notification\Contracts\NotifiableInterface;
use Marko\Notification\Contracts\NotificationInterface;
use Marko\Notification\NotificationSender;
use Marko\Queue\ContainerAwareJobInterface;
use Marko\Queue\Job;
use Marko\Queue\JobEnvelope;
use RuntimeException;

class SendNotificationJob extends Job implements ContainerAwareJobInterface
{
    private ?ContainerInterface $container = null;

    public function __construct(
        /** @var NotifiableInterface|array<NotifiableInterface> */
        public readonly NotifiableInterface|array $notifiables,
        public readonly NotificationInterface $notification,
    ) {}

    public function setContainer(ContainerInterface $container): void
    {
        $this->container = $container;
    }

    public function setJobEnvelope(JobEnvelope $jobEnvelope): void
    {
        // Not needed for this job — HMAC envelope is only for AsyncObserverJob event data
    }

    /**
     * @throws RuntimeException
     */
    public function handle(): void
    {
        if ($this->container === null) {
            throw new RuntimeException(
                'SendNotificationJob::handle() was called without a container. '
                . 'Call setContainer() before handle() to provide the DI container for service resolution.',
            );
        }

        $sender = $this->container->get(NotificationSender::class);
        $sender->send($this->notifiables, $this->notification);
    }
}
