<?php

declare(strict_types=1);

namespace Marko\Webhook\Tests\Jobs;

use Marko\Http\Contracts\HttpClientInterface;
use Marko\Http\HttpResponse;
use Marko\Testing\Fake\FakeConfigRepository;
use Marko\Testing\Fake\FakeQueue;
use Marko\Webhook\Contracts\WebhookAttemptRepositoryInterface;
use Marko\Webhook\Entity\WebhookAttempt;
use Marko\Webhook\Jobs\DispatchWebhookJob;
use Marko\Webhook\Sending\WebhookDeliveryService;
use Marko\Webhook\Sending\WebhookDispatcher;
use Marko\Webhook\Value\WebhookPayload;
use RuntimeException;

describe('DispatchWebhookJob retry', function (): void {
    it('retries failed deliveries with exponential backoff', function (): void {
        $payload = new WebhookPayload(
            url: 'https://example.com/webhook',
            event: 'order.created',
            data: ['order_id' => 123],
            secret: 'my-secret',
        );

        $httpClient = new class () implements HttpClientInterface
        {
            public function request(string $method, string $url, array $options = []): HttpResponse
            {
                throw new RuntimeException('Connection timed out');
            }

            public function get(string $url, array $options = []): HttpResponse
            {
                throw new RuntimeException('Connection timed out');
            }

            public function post(string $url, array $options = []): HttpResponse
            {
                throw new RuntimeException('Connection timed out');
            }

            public function put(string $url, array $options = []): HttpResponse
            {
                throw new RuntimeException('Connection timed out');
            }

            public function patch(string $url, array $options = []): HttpResponse
            {
                throw new RuntimeException('Connection timed out');
            }

            public function delete(string $url, array $options = []): HttpResponse
            {
                throw new RuntimeException('Connection timed out');
            }
        };

        $savedAttempts = [];
        $attemptRepository = new class ($savedAttempts) implements WebhookAttemptRepositoryInterface
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

        $config = new FakeConfigRepository([
            'webhook.max_retries' => 3,
            'webhook.retry_delay' => 60,
        ]);

        $dispatcher = new WebhookDispatcher($httpClient);
        $deliveryService = new WebhookDeliveryService($attemptRepository);
        $queue = new FakeQueue();

        // Attempt 1: first failure should re-queue with delay = 60 * 2^1 = 120
        $job = new DispatchWebhookJob($payload, $dispatcher, $deliveryService, $config, $queue, 1);
        $job->handle();

        // Should have re-queued with delay
        expect($queue->pushed)->toHaveCount(1)
            ->and($queue->pushed[0]['delay'])->toBe(120)
            ->and($queue->pushed[0]['job'])->toBeInstanceOf(DispatchWebhookJob::class);

        // Failure should have been recorded
        expect($savedAttempts)->toHaveCount(1)
            ->and($savedAttempts[0]->errorMessage)->toBe('Connection timed out')
            ->and($savedAttempts[0]->attemptNumber)->toBe(1);
    });

    it('stops retrying after reaching maximum retry limit from config', function (): void {
        $payload = new WebhookPayload(
            url: 'https://example.com/webhook',
            event: 'order.created',
            data: ['order_id' => 123],
            secret: 'my-secret',
        );

        $httpClient = new class () implements HttpClientInterface
        {
            public function request(string $method, string $url, array $options = []): HttpResponse
            {
                throw new RuntimeException('Connection timed out');
            }

            public function get(string $url, array $options = []): HttpResponse
            {
                throw new RuntimeException('Connection timed out');
            }

            public function post(string $url, array $options = []): HttpResponse
            {
                throw new RuntimeException('Connection timed out');
            }

            public function put(string $url, array $options = []): HttpResponse
            {
                throw new RuntimeException('Connection timed out');
            }

            public function patch(string $url, array $options = []): HttpResponse
            {
                throw new RuntimeException('Connection timed out');
            }

            public function delete(string $url, array $options = []): HttpResponse
            {
                throw new RuntimeException('Connection timed out');
            }
        };

        $savedAttempts = [];
        $attemptRepository = new class ($savedAttempts) implements WebhookAttemptRepositoryInterface
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

        $config = new FakeConfigRepository([
            'webhook.max_retries' => 3,
            'webhook.retry_delay' => 60,
        ]);

        $dispatcher = new WebhookDispatcher($httpClient);
        $deliveryService = new WebhookDeliveryService($attemptRepository);
        $queue = new FakeQueue();

        // Attempt 3 = max_retries, should NOT re-queue
        $job = new DispatchWebhookJob($payload, $dispatcher, $deliveryService, $config, $queue, 3);
        $job->handle();

        // Should NOT have re-queued
        expect($queue->pushed)->toHaveCount(0);

        // Should have recorded the final failure
        expect($savedAttempts)->toHaveCount(1)
            ->and($savedAttempts[0]->errorMessage)->toBe('Connection timed out')
            ->and($savedAttempts[0]->attemptNumber)->toBe(3);
    });
});
