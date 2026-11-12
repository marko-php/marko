<?php

declare(strict_types=1);

namespace Marko\Core\Tests\Unit\Container\Fixtures;

readonly class T005NoLabel
{
    public function __construct(
        public int $count = 1,
    ) {}
}
