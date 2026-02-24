<?php

declare(strict_types=1);

namespace Marko\Webhook\Tests\Receiving;

use Marko\Routing\Http\Request;
use Marko\Webhook\Exceptions\InvalidSignatureException;
use Marko\Webhook\Receiving\WebhookReceiver;
use Marko\Webhook\Receiving\WebhookVerifier;

describe('WebhookReceiver', function (): void {
    it('throws InvalidSignatureException for failed signature verification', function (): void {
        $receiver = new WebhookReceiver(new WebhookVerifier());
        $request = new Request(
            server: ['HTTP_X_WEBHOOK_SIGNATURE' => 'sha256=invalidsignature'],
        );

        expect(fn () => $receiver->receive($request, 'my-secret'))
            ->toThrow(InvalidSignatureException::class);
    });

    it('parses JSON payloads from incoming webhook request bodies', function (): void {
        $secret = 'my-secret';
        $data = ['event' => 'order.created', 'data' => ['order_id' => 123]];
        $body = json_encode($data);
        $signature = 'sha256=' . hash_hmac('sha256', $body, $secret);

        $receiver = new WebhookReceiver(new WebhookVerifier());
        $request = new Request(
            server: ['HTTP_X_WEBHOOK_SIGNATURE' => $signature],
            body: $body,
        );

        $result = $receiver->receive($request, $secret);

        expect($result)->toBe($data);
    });
});
