<?php

declare(strict_types=1);

namespace Marko\Webhook\Receiving;

class WebhookVerifier
{
    public function verify(
        string $body,
        string $signature,
        string $secret,
    ): bool {
        $expected = 'sha256=' . hash_hmac('sha256', $body, $secret);

        return hash_equals($expected, $signature);
    }
}
