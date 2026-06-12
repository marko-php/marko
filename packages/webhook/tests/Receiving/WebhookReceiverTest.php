<?php

declare(strict_types=1);

namespace Marko\Webhook\Tests\Receiving;

use Marko\Routing\Http\Request;
use Marko\Testing\Fake\FakeConfigRepository;
use Marko\Webhook\Config\WebhookConfig;
use Marko\Webhook\Exceptions\InvalidSignatureException;
use Marko\Webhook\Receiving\WebhookReceiver;
use Marko\Webhook\Receiving\WebhookVerifier;

function makeWebhookReceiver(): WebhookReceiver
{
    $config = new WebhookConfig(new FakeConfigRepository([
        'webhook.timeout' => 30,
        'webhook.max_retries' => 3,
        'webhook.retry_delay' => 60,
        'webhook.timestamp_tolerance' => 300,
    ]));

    return new WebhookReceiver(new WebhookVerifier(), $config);
}

describe('WebhookReceiver', function (): void {
    it('throws InvalidSignatureException for failed signature verification', function (): void {
        $receiver = makeWebhookReceiver();
        $timestamp = (string) time();
        $request = new Request(
            server: [
                'HTTP_X_WEBHOOK_SIGNATURE' => 'sha256=invalidsignature',
                'HTTP_X_WEBHOOK_TIMESTAMP' => $timestamp,
            ],
        );

        expect(fn () => $receiver->receive($request, 'my-secret'))
            ->toThrow(InvalidSignatureException::class);
    });

    it('parses JSON payloads from incoming webhook request bodies', function (): void {
        $secret = 'my-secret';
        $data = ['event' => 'order.created', 'data' => ['order_id' => 123]];
        $body = json_encode($data);
        $timestamp = time();
        $signature = 'sha256=' . hash_hmac('sha256', "$timestamp.$body", $secret);

        $receiver = makeWebhookReceiver();
        $request = new Request(
            server: [
                'HTTP_X_WEBHOOK_SIGNATURE' => $signature,
                'HTTP_X_WEBHOOK_TIMESTAMP' => (string) $timestamp,
            ],
            body: $body,
        );

        $result = $receiver->receive($request, $secret);

        expect($result)->toBe($data);
    });
});
