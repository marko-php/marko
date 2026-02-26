<?php

declare(strict_types=1);

namespace Marko\PubSub;

readonly class Message
{
    public function __construct(
        public string $channel,
        public string $payload,
        public ?string $pattern = null,
    ) {}
}
