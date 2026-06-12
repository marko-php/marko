<?php

declare(strict_types=1);

namespace Marko\Webhook\Receiving;

class WebhookVerifier
{
    public function verify(
        string $body,
        string $timestamp,
        string $signature,
        string $secret,
        int $tolerance,
    ): bool {
        if (abs(time() - (int) $timestamp) > $tolerance) {
            return false;
        }

        $expected = 'sha256=' . hash_hmac('sha256', "$timestamp.$body", $secret);

        return hash_equals($expected, $signature);
    }
}
