<?php

declare(strict_types=1);

namespace Marko\Core\Tests\Unit\Container\Fixtures;

readonly class T006Server
{
    public function __construct(
        public string $host = 'localhost',
        public int $port = 80,
    ) {}
}
