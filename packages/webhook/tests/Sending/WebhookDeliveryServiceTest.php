<?php

declare(strict_types=1);

namespace Marko\Webhook\Tests\Sending;

use Marko\Webhook\Contracts\WebhookAttemptRepositoryInterface;
use Marko\Webhook\Entity\WebhookAttempt;
use Marko\Webhook\Sending\WebhookDeliveryService;
use Marko\Webhook\Value\WebhookPayload;
use Marko\Webhook\Value\WebhookResponse;

describe('WebhookDeliveryService', function (): void {
    it('records successful delivery attempts with status code and response', function (): void {
        $savedAttempts = [];

        $repository = new class ($savedAttempts) implements WebhookAttemptRepositoryInterface
        {
            public function __construct(
                private array &$savedAttempts,
            ) {}

            public function save(
                WebhookAttempt $attempt,
            ): WebhookAttempt {
                $this->savedAttempts[] = $attempt;

                return $attempt;
            }
        };

        $service = new WebhookDeliveryService($repository);

        $payload = new WebhookPayload(
            url: 'https://example.com/webhook',
            event: 'order.created',
            data: ['order_id' => 123],
            secret: 'my-secret',
        );

        $response = new WebhookResponse(
            statusCode: 200,
            body: 'OK',
            successful: true,
        );

        $service->recordSuccess($payload, $response, 1);

        expect($savedAttempts)->toHaveCount(1);

        $attempt = $savedAttempts[0];
        expect($attempt)->toBeInstanceOf(WebhookAttempt::class)
            ->and($attempt->webhookUrl)->toBe('https://example.com/webhook')
            ->and($attempt->event)->toBe('order.created')
            ->and($attempt->statusCode)->toBe(200)
            ->and($attempt->responseBody)->toBe('OK')
            ->and($attempt->errorMessage)->toBeNull()
            ->and($attempt->attemptNumber)->toBe(1)
            ->and($attempt->attemptedAt)->not->toBeNull();
    });

    it('records failed delivery attempts with error details', function (): void {
        $savedAttempts = [];

        $repository = new class ($savedAttempts) implements WebhookAttemptRepositoryInterface
        {
            public function __construct(
                private array &$savedAttempts,
            ) {}

            public function save(
                WebhookAttempt $attempt,
            ): WebhookAttempt {
                $this->savedAttempts[] = $attempt;

                return $attempt;
            }
        };

        $service = new WebhookDeliveryService($repository);

        $payload = new WebhookPayload(
            url: 'https://example.com/webhook',
            event: 'order.created',
            data: ['order_id' => 123],
            secret: 'my-secret',
        );

        $service->recordFailure($payload, 'Connection timed out', 2);

        expect($savedAttempts)->toHaveCount(1);

        $attempt = $savedAttempts[0];
        expect($attempt)->toBeInstanceOf(WebhookAttempt::class)
            ->and($attempt->webhookUrl)->toBe('https://example.com/webhook')
            ->and($attempt->event)->toBe('order.created')
            ->and($attempt->statusCode)->toBeNull()
            ->and($attempt->responseBody)->toBeNull()
            ->and($attempt->errorMessage)->toBe('Connection timed out')
            ->and($attempt->attemptNumber)->toBe(2)
            ->and($attempt->attemptedAt)->not->toBeNull();
    });
});
