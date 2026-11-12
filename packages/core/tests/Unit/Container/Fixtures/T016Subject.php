<?php

declare(strict_types=1);

namespace Marko\Core\Tests\Unit\Container\Fixtures;

readonly class T016Subject
{
    public function __construct(
        public T016Dep $dep,
        public string $label = 'default',
        public int $count = 1,
    ) {}
}
