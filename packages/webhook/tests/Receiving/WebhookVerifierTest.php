<?php

declare(strict_types=1);

namespace Marko\Webhook\Tests\Receiving;

use Marko\Webhook\Receiving\WebhookVerifier;

describe('WebhookVerifier', function (): void {
    it('verifies incoming webhook signatures using HMAC-SHA256', function (): void {
        $verifier = new WebhookVerifier();
        $body = '{"event":"order.created","data":{"order_id":123}}';
        $secret = 'my-secret';
        $hash = hash_hmac('sha256', $body, $secret);
        $signature = 'sha256=' . $hash;

        expect($verifier->verify($body, $signature, $secret))->toBeTrue();
    });
});
