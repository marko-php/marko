<?php

declare(strict_types=1);

namespace Marko\Core\Tests\Unit\Container\Fixtures;

readonly class T016Impl implements T016Contract
{
    public function __construct(
        public T016Dep $dep,
        public string $label = 'default',
    ) {}
}
