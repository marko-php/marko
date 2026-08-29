<?php

declare(strict_types=1);

namespace Marko\Webhook\Tests\Jobs;

use Closure;
use Marko\Config\ConfigRepositoryInterface;
use Marko\Core\Container\ContainerInterface;
use Marko\Http\Contracts\HttpClientInterface;
use Marko\Http\HttpResponse;
use Marko\Queue\QueueInterface;
use Marko\Testing\Fake\FakeConfigRepository;
use Marko\Testing\Fake\FakeQueue;
use Marko\Webhook\Contracts\WebhookAttemptRepositoryInterface;
use Marko\Webhook\Contracts\WebhookDispatcherInterface;
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
            public function request(
                string $method,
                string $url,
                array $options = [],
            ): HttpResponse {
                throw new RuntimeException('Connection timed out');
            }

            public function get(
                string $url,
                array $options = [],
            ): HttpResponse {
                throw new RuntimeException('Connection timed out');
            }

            public function post(
                string $url,
                array $options = [],
            ): HttpResponse {
                throw new RuntimeException('Connection timed out');
            }

            public function put(
                string $url,
                array $options = [],
            ): HttpResponse {
                throw new RuntimeException('Connection timed out');
            }

            public function patch(
                string $url,
                array $options = [],
            ): HttpResponse {
                throw new RuntimeException('Connection timed out');
            }

            public function delete(
                string $url,
                array $options = [],
            ): HttpResponse {
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
        $fakeQueue = new FakeQueue();

        $container = new readonly class ($dispatcher, $deliveryService, $config, $fakeQueue) implements ContainerInterface
        {
            public function __construct(
                private WebhookDispatcher $dispatcher,
                private WebhookDeliveryService $deliveryService,
                private ConfigRepositoryInterface $config,
                private QueueInterface $queue,
            ) {}

            public function get(string $id): object
            {
                return match ($id) {
                    WebhookDispatcherInterface::class => $this->dispatcher,
                    WebhookDeliveryService::class => $this->deliveryService,
                    ConfigRepositoryInterface::class => $this->config,
                    QueueInterface::class => $this->queue,
                    default => throw new RuntimeException("No binding for: $id"),
                };
            }

            public function has(string $id): bool
            {
                return true;
            }

            public function singleton(string $id): void {}

            public function instance(
                string $id,
                object $instance,
            ): void {}

            public function call(Closure $callable): mixed
            {
                return null;
            }

            public function resolvedInstances(?string $interface = null): array
            {
                return [];
            }
        };

        // Attempt 1: first failure should re-queue with delay = 60 * 2^1 = 120
        $job = new DispatchWebhookJob($payload, attemptNumber: 1);
        $job->setContainer($container);
        $job->handle();

        // Should have re-queued with delay
        expect($fakeQueue->pushed)->toHaveCount(1)
            ->and($fakeQueue->pushed[0]['delay'])->toBe(120)
            ->and($fakeQueue->pushed[0]['job'])->toBeInstanceOf(DispatchWebhookJob::class);

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
            public function request(
                string $method,
                string $url,
                array $options = [],
            ): HttpResponse {
                throw new RuntimeException('Connection timed out');
            }

            public function get(
                string $url,
                array $options = [],
            ): HttpResponse {
                throw new RuntimeException('Connection timed out');
            }

            public function post(
                string $url,
                array $options = [],
            ): HttpResponse {
                throw new RuntimeException('Connection timed out');
            }

            public function put(
                string $url,
                array $options = [],
            ): HttpResponse {
                throw new RuntimeException('Connection timed out');
            }

            public function patch(
                string $url,
                array $options = [],
            ): HttpResponse {
                throw new RuntimeException('Connection timed out');
            }

            public function delete(
                string $url,
                array $options = [],
            ): HttpResponse {
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
        $fakeQueue = new FakeQueue();

        $container = new readonly class ($dispatcher, $deliveryService, $config, $fakeQueue) implements ContainerInterface
        {
            public function __construct(
                private WebhookDispatcher $dispatcher,
                private WebhookDeliveryService $deliveryService,
                private ConfigRepositoryInterface $config,
                private QueueInterface $queue,
            ) {}

            public function get(string $id): object
            {
                return match ($id) {
                    WebhookDispatcherInterface::class => $this->dispatcher,
                    WebhookDeliveryService::class => $this->deliveryService,
                    ConfigRepositoryInterface::class => $this->config,
                    QueueInterface::class => $this->queue,
                    default => throw new RuntimeException("No binding for: $id"),
                };
            }

            public function has(string $id): bool
            {
                return true;
            }

            public function singleton(string $id): void {}

            public function instance(
                string $id,
                object $instance,
            ): void {}

            public function call(Closure $callable): mixed
            {
                return null;
            }

            public function resolvedInstances(?string $interface = null): array
            {
                return [];
            }
        };

        // Attempt 3 = max_retries, should NOT re-queue
        $job = new DispatchWebhookJob($payload, attemptNumber: 3);
        $job->setContainer($container);
        $job->handle();

        // Should NOT have re-queued
        expect($fakeQueue->pushed)->toHaveCount(0);

        // Should have recorded the final failure
        expect($savedAttempts)->toHaveCount(1)
            ->and($savedAttempts[0]->errorMessage)->toBe('Connection timed out')
            ->and($savedAttempts[0]->attemptNumber)->toBe(3);
    });
});
