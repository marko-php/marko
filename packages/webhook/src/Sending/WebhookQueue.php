<?php

declare(strict_types=1);

namespace Marko\Webhook\Sending;

use Marko\Encryption\Contracts\EncryptorInterface;
use Marko\Encryption\Exceptions\EncryptionException;
use Marko\Queue\PushResult;
use Marko\Queue\QueueInterface;
use Marko\Webhook\Exceptions\InvalidWebhookSecretException;
use Marko\Webhook\Jobs\DispatchWebhookJob;
use Marko\Webhook\Value\SealedWebhookPayload;
use Marko\Webhook\Value\WebhookPayload;

/**
 * Queues webhook deliveries for background sending with retry. The payload's signing secret is
 * encrypted with the bound EncryptorInterface before the job is serialized, so the queue backend
 * never stores it in plain text.
 */
readonly class WebhookQueue
{
    public function __construct(
        private QueueInterface $queue,
        private EncryptorInterface $encryptor,
    ) {}

    /**
     * Push a DispatchWebhookJob for $payload and return the queue's PushResult (the queued job's id, or why it was skipped).
     *
     * @throws InvalidWebhookSecretException|EncryptionException
     */
    public function push(
        WebhookPayload $payload,
        ?string $queue = null,
    ): PushResult {
        return $this->queue->push(
            new DispatchWebhookJob(SealedWebhookPayload::seal($payload, $this->encryptor)),
            $queue,
        );
    }
}
