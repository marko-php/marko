<?php

declare(strict_types=1);

namespace Marko\Webhook\Tests\Sending;

use Marko\Webhook\Sending\WebhookSignature;

describe('WebhookSignature', function (): void {
    it('signs payloads with HMAC-SHA256 via WebhookSignature utility', function (): void {
        $payload = '{"event":"order.created","data":{"order_id":123}}';
        $secret = 'my-secret';

        $signature = WebhookSignature::sign($payload, $secret);

        $expectedHash = hash_hmac('sha256', $payload, $secret);
        $expected = 'sha256=' . $expectedHash;

        expect($signature)->toBe($expected)
            ->and($signature)->toStartWith('sha256=');
    });
});
