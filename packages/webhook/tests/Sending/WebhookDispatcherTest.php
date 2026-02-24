<?php

declare(strict_types=1);

namespace Marko\Webhook\Tests\Sending;

use Marko\Http\Contracts\HttpClientInterface;
use Marko\Http\HttpResponse;
use Marko\Webhook\Sending\WebhookDispatcher;
use Marko\Webhook\Sending\WebhookSignature;
use Marko\Webhook\Value\WebhookPayload;
use Marko\Webhook\Value\WebhookResponse;

describe('WebhookDispatcher', function (): void {
    it('dispatches webhooks synchronously via HTTP client', function (): void {
        $payload = new WebhookPayload(
            url: 'https://example.com/webhook',
            event: 'order.created',
            data: ['order_id' => 123],
            secret: 'my-secret',
        );

        $jsonBody = json_encode(['event' => $payload->event, 'data' => $payload->data]);
        $expectedSignature = WebhookSignature::sign($jsonBody, $payload->secret);

        $capturedUrl = null;
        $capturedOptions = null;

        $httpClient = new class ($capturedUrl, $capturedOptions) implements HttpClientInterface
        {
            public function __construct(
                private ?string &$capturedUrl,
                private ?array &$capturedOptions,
            ) {}

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
                $this->capturedUrl = $url;
                $this->capturedOptions = $options;

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

        $dispatcher = new WebhookDispatcher($httpClient);
        $response = $dispatcher->dispatch($payload);

        expect($response)->toBeInstanceOf(WebhookResponse::class)
            ->and($response->statusCode)->toBe(200)
            ->and($response->body)->toBe('OK')
            ->and($response->successful)->toBeTrue()
            ->and($capturedUrl)->toBe($payload->url)
            ->and($capturedOptions['headers']['X-Webhook-Signature'])->toBe($expectedSignature)
            ->and($capturedOptions['headers']['Content-Type'])->toBe('application/json')
            ->and($capturedOptions['body'])->toBe($jsonBody);
    });
});
