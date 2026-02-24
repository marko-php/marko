<?php

declare(strict_types=1);

namespace Marko\Webhook\Sending;

class WebhookSignature
{
    public static function sign(
        string $payload,
        string $secret,
    ): string {
        return 'sha256=' . hash_hmac('sha256', $payload, $secret);
    }
}
