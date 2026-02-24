<?php

declare(strict_types=1);

namespace Marko\Webhook\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
readonly class WebhookEndpoint
{
    public function __construct(
        public string $path,
        public string $secret,
    ) {}
}
