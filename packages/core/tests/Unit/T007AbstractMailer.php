<?php

declare(strict_types=1);

namespace Marko\Core\Tests\Unit;

abstract readonly class T007AbstractMailer
{
    public function __construct(
        public string $host = 'localhost',
    ) {}
}
