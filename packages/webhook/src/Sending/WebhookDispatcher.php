<?php

declare(strict_types=1);

namespace Marko\Webhook\Sending;

use Marko\Http\Contracts\HttpClientInterface;
use Marko\Webhook\Contracts\WebhookDispatcherInterface;
use Marko\Webhook\Value\WebhookPayload;
use Marko\Webhook\Value\WebhookResponse;

class WebhookDispatcher implements WebhookDispatcherInterface
{
    public function __construct(
        private HttpClientInterface $httpClient,
    ) {}

    public function dispatch(
        WebhookPayload $payload,
    ): WebhookResponse {
        $body = json_encode(['event' => $payload->event, 'data' => $payload->data]);
        $signature = WebhookSignature::sign($body, $payload->secret);

        $httpResponse = $this->httpClient->post($payload->url, [
            'headers' => [
                'Content-Type' => 'application/json',
                'X-Webhook-Signature' => $signature,
            ],
            'body' => $body,
        ]);

        return new WebhookResponse(
            statusCode: $httpResponse->statusCode(),
            body: $httpResponse->body(),
            successful: $httpResponse->isSuccessful(),
        );
    }
}
