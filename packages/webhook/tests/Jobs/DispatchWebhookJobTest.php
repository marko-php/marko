<?php

declare(strict_types=1);

namespace Marko\Webhook\Tests\Jobs;

use Closure;
use Marko\Config\ConfigRepositoryInterface;
use Marko\Core\Container\ContainerInterface;
use Marko\Http\Contracts\HttpClientInterface;
use Marko\Http\HttpResponse;
use Marko\Queue\JobInterface;
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

describe('DispatchWebhookJob', function (): void {
    it('queues webhook delivery via DispatchWebhookJob for async dispatch', function (): void {
        $payload = new WebhookPayload(
            url: 'https://example.com/webhook',
            event: 'order.created',
            data: ['order_id' => 123],
            secret: 'my-secret',
        );

        $httpClient = new class () implements HttpClientInterface
        {
            public bool $postCalled = false;

            public function request(
                string $method,
                string $url,
                array $options = [],
            ): HttpResponse {
                return new HttpResponse(200, 'OK');
            }

            public function get(
                string $url,
                array $options = [],
            ): HttpResponse {
                return new HttpResponse(200, 'OK');
            }

            public function post(
                string $url,
                array $options = [],
            ): HttpResponse {
                $this->postCalled = true;

                return new HttpResponse(200, 'OK');
            }

            public function put(
                string $url,
                array $options = [],
            ): HttpResponse {
                return new HttpResponse(200, 'OK');
            }

            public function patch(
                string $url,
                array $options = [],
            ): HttpResponse {
                return new HttpResponse(200, 'OK');
            }

            public function delete(
                string $url,
                array $options = [],
            ): HttpResponse {
                return new HttpResponse(200, 'OK');
            }
        };

        $attemptRepository = new class () implements WebhookAttemptRepositoryInterface
        {
            public function save(
                WebhookAttempt $attempt,
            ): WebhookAttempt {
                return $attempt;
            }
        };

        $config = new FakeConfigRepository([
            'webhook.timeout' => 30,
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

        $job = new DispatchWebhookJob($payload);
        $job->setContainer($container);

        expect($job)->toBeInstanceOf(JobInterface::class);

        $fakeQueue->push($job);
        $fakeQueue->assertPushed(DispatchWebhookJob::class);

        // Verify the job can handle (dispatch the webhook)
        $job->handle();

        expect($httpClient->postCalled)->toBeTrue();
    });
});
