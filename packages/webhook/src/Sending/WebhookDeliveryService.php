<?php

declare(strict_types=1);

namespace Marko\Webhook\Sending;

use DateTimeImmutable;
use Marko\Webhook\Contracts\WebhookAttemptRepositoryInterface;
use Marko\Webhook\Entity\WebhookAttempt;
use Marko\Webhook\Value\WebhookPayload;
use Marko\Webhook\Value\WebhookResponse;

class WebhookDeliveryService
{
    public function __construct(
        private WebhookAttemptRepositoryInterface $repository,
    ) {}

    public function recordSuccess(
        WebhookPayload $payload,
        WebhookResponse $response,
        int $attempt,
    ): void {
        $webhookAttempt = new WebhookAttempt(
            webhookUrl: $payload->url,
            event: $payload->event,
            attemptNumber: $attempt,
        );

        $webhookAttempt->statusCode = $response->statusCode;
        $webhookAttempt->responseBody = $response->body;
        $webhookAttempt->attemptedAt = (new DateTimeImmutable())->format('Y-m-d H:i:s');

        $this->repository->save($webhookAttempt);
    }

    public function recordFailure(
        WebhookPayload $payload,
        string $error,
        int $attempt,
    ): void {
        $webhookAttempt = new WebhookAttempt(
            webhookUrl: $payload->url,
            event: $payload->event,
            attemptNumber: $attempt,
        );

        $webhookAttempt->errorMessage = $error;
        $webhookAttempt->attemptedAt = (new DateTimeImmutable())->format('Y-m-d H:i:s');

        $this->repository->save($webhookAttempt);
    }
}
