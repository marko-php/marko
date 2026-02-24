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
}
