<?php

declare(strict_types=1);

namespace Marko\Core\Tests\Unit;

readonly class T007Mailer
{
    public function __construct(
        public string $host = 'localhost',
        public int $port = 25,
    ) {}
}
