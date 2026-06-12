<?php

declare(strict_types=1);

namespace Marko\Webhook\Jobs;

use Marko\Config\ConfigRepositoryInterface;
use Marko\Config\Exceptions\ConfigNotFoundException;
use Marko\Core\Container\ContainerInterface;
use Marko\Queue\ContainerAwareJobInterface;
use Marko\Queue\Job;
use Marko\Queue\JobEnvelope;
use Marko\Queue\QueueInterface;
use Marko\Webhook\Contracts\WebhookDispatcherInterface;
use Marko\Webhook\Sending\WebhookDeliveryService;
use Marko\Webhook\Value\WebhookPayload;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use RuntimeException;
use Throwable;

class DispatchWebhookJob extends Job implements ContainerAwareJobInterface
{
    private ?ContainerInterface $container = null;

    public function __construct(
        public readonly WebhookPayload $payload,
        public readonly int $attemptNumber = 1,
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
     * @throws RuntimeException|ContainerExceptionInterface|NotFoundExceptionInterface
     */
    public function handle(): void
    {
        if ($this->container === null) {
            throw new RuntimeException(
                'DispatchWebhookJob::handle() was called without a container. '
                . 'Call setContainer() before handle() to provide the DI container for service resolution.',
            );
        }

        $dispatcher = $this->container->get(WebhookDispatcherInterface::class);
        $deliveryService = $this->container->get(WebhookDeliveryService::class);

        try {
            $response = $dispatcher->dispatch($this->payload);
            $deliveryService->recordSuccess($this->payload, $response, $this->attemptNumber);
        } catch (Throwable $e) {
            try {
                /** @var ConfigRepositoryInterface $config */
                $config = $this->container->get(ConfigRepositoryInterface::class);
                $maxRetries = $config->getInt('webhook.max_retries');
                $retryDelay = $config->getInt('webhook.retry_delay');
            } catch (ConfigNotFoundException) {
                // If config values are missing, we won't retry and just log the failure.
                $deliveryService->recordFailure($this->payload, $e->getMessage(), $this->attemptNumber);

                return;
            }

            $deliveryService->recordFailure($this->payload, $e->getMessage(), $this->attemptNumber);

            if ($this->attemptNumber < $maxRetries) {
                $delay = $retryDelay * (2 ** $this->attemptNumber);
                $nextJob = new self($this->payload, $this->attemptNumber + 1);

                /** @var QueueInterface $queue */
                $queue = $this->container->get(QueueInterface::class);
                $queue->later($delay, $nextJob);
            }
        }
    }
}
