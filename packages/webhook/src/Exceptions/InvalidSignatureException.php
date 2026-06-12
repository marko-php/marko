<?php

declare(strict_types=1);

namespace Marko\Webhook\Exceptions;

use Marko\Core\Exceptions\MarkoException;

class InvalidSignatureException extends MarkoException
{
    public static function forRequest(): self
    {
        return new self(
            message: 'Invalid webhook signature.',
            context: 'While verifying the HMAC-SHA256 signature of the incoming webhook request.',
            suggestion: 'Ensure the signing secret matches and the request body has not been tampered with.',
        );
    }

    public static function missingTimestamp(): self
    {
        return new self(
            message: 'Webhook request is missing the X-Webhook-Timestamp header.',
            context: 'While checking timestamp freshness of the incoming webhook request.',
            suggestion: 'Ensure the sender includes the X-Webhook-Timestamp header with a Unix epoch timestamp.',
        );
    }

    public static function staleTimestamp(
        int $timestamp,
        int $tolerance,
    ): self {
        $age = abs(time() - $timestamp);

        return new self(
            message: "Webhook timestamp is outside the freshness window (age: {$age}s, tolerance: {$tolerance}s).",
            context: 'While checking timestamp freshness of the incoming webhook request.',
            suggestion: 'Ensure the webhook was sent recently and that clocks are synchronized. Replay attacks are rejected.',
        );
    }
}
