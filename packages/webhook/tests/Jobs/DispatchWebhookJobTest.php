<?php

declare(strict_types=1);

namespace Marko\Webhook\Tests\Jobs;

use Marko\Http\Contracts\HttpClientInterface;
use Marko\Http\HttpResponse;
use Marko\Queue\JobInterface;
use Marko\Testing\Fake\FakeConfigRepository;
use Marko\Testing\Fake\FakeQueue;
use Marko\Webhook\Contracts\WebhookAttemptRepositoryInterface;
use Marko\Webhook\Entity\WebhookAttempt;
use Marko\Webhook\Jobs\DispatchWebhookJob;
use Marko\Webhook\Sending\WebhookDeliveryService;
use Marko\Webhook\Sending\WebhookDispatcher;
use Marko\Webhook\Value\WebhookPayload;

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

            public function request(string $method, string $url, array $options = []): HttpResponse
            {
                return new HttpResponse(200, 'OK');
            }

            public function get(string $url, array $options = []): HttpResponse
            {
                return new HttpResponse(200, 'OK');
            }

            public function post(string $url, array $options = []): HttpResponse
            {
                $this->postCalled = true;

                return new HttpResponse(200, 'OK');
            }

            public function put(string $url, array $options = []): HttpResponse
            {
                return new HttpResponse(200, 'OK');
            }

            public function patch(string $url, array $options = []): HttpResponse
            {
                return new HttpResponse(200, 'OK');
            }

            public function delete(string $url, array $options = []): HttpResponse
            {
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

        $queue = new FakeQueue();
        $job = new DispatchWebhookJob($payload, $dispatcher, $deliveryService, $config, $queue);

        expect($job)->toBeInstanceOf(JobInterface::class);

        $queue->push($job);
        $queue->assertPushed(DispatchWebhookJob::class);

        // Verify the job can handle (dispatch the webhook)
        $job->handle();

        expect($httpClient->postCalled)->toBeTrue();
    });
});
