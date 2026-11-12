<?php

declare(strict_types=1);

namespace Marko\Core\Tests\Unit\Container\Fixtures;

readonly class T005Replacement
{
    public function __construct(
        public string $label = 'default',
        public int $count = 1,
    ) {}
}
