<?php

declare(strict_types=1);

namespace Marko\Webhook\Jobs;

use Marko\Config\ConfigRepositoryInterface;
use Marko\Config\Exceptions\ConfigNotFoundException;
use Marko\Queue\Job;
use Marko\Queue\QueueInterface;
use Marko\Webhook\Contracts\WebhookDispatcherInterface;
use Marko\Webhook\Sending\WebhookDeliveryService;
use Marko\Webhook\Value\WebhookPayload;
use Throwable;

class DispatchWebhookJob extends Job
{
    public function __construct(
        private readonly WebhookPayload $payload,
        private readonly WebhookDispatcherInterface $dispatcher,
        private readonly WebhookDeliveryService $deliveryService,
        private readonly ConfigRepositoryInterface $config,
        private readonly QueueInterface $queue,
        private readonly int $attemptNumber = 1,
    ) {}

    public function handle(): void
    {
        try {
            $response = $this->dispatcher->dispatch($this->payload);
            $this->deliveryService->recordSuccess($this->payload, $response, $this->attemptNumber);
        } catch (Throwable $e) {
            try {
                $maxRetries = $this->config->getInt('webhook.max_retries');
                $retryDelay = $this->config->getInt('webhook.retry_delay');
            } catch (ConfigNotFoundException) {
                // If config values are missing, we won't retry and just log the failure.
                $this->deliveryService->recordFailure($this->payload, $e->getMessage(), $this->attemptNumber);

                return;
            }

            $this->deliveryService->recordFailure($this->payload, $e->getMessage(), $this->attemptNumber);

            if ($this->attemptNumber < $maxRetries) {
                $delay = $retryDelay * (2 ** $this->attemptNumber);
                $nextJob = new self(
                    $this->payload,
                    $this->dispatcher,
                    $this->deliveryService,
                    $this->config,
                    $this->queue,
                    $this->attemptNumber + 1,
                );
                $this->queue->later($delay, $nextJob);
            }
        }
    }
}
