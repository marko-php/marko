<?php

declare(strict_types=1);

namespace Marko\Queue\Rabbitmq\Exchange;

readonly class ExchangeConfig
{
    public function __construct(
        public string $name,
        public ExchangeType $type,
        public bool $durable = true,
        public bool $autoDelete = false,
        /** @var array<string, mixed> */
        public array $arguments = [],
    ) {}
}
