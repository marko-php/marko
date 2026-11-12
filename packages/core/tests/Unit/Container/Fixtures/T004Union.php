<?php

declare(strict_types=1);

namespace Marko\Core\Tests\Unit\Container\Fixtures;

readonly class T004Union
{
    public function __construct(
        public int|string $value,
    ) {}
}
