<?php

declare(strict_types=1);

namespace Marko\Webhook\Value;

readonly class WebhookPayload
{
    /**
     * @param array<string, mixed> $data
     */
    public function __construct(
        public string $url,
        public string $event,
        public array $data,
        public string $secret,
    ) {}
}
