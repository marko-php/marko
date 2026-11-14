<?php

declare(strict_types=1);

namespace Marko\Webhook\Value;

readonly class WebhookResponse
{
    public function __construct(
        public int $statusCode,
        public string $body,
        public bool $successful,
    ) {}

    /**
     * Whether a failed delivery is worth sending again: 408 Request Timeout, 429 Too Many Requests
     * and any 5xx. Other non-2xx statuses (3xx, the remaining 4xx) will not change on a retry.
     */
    public function isRetryable(): bool
    {
        return $this->statusCode === 408
            || $this->statusCode === 429
            || $this->statusCode >= 500;
    }
}
