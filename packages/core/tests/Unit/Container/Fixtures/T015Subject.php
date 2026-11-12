<?php

declare(strict_types=1);

namespace Marko\Core\Tests\Unit\Container\Fixtures;

use Closure;

readonly class T015Subject
{
    public function __construct(
        public string $name,
        public float $ratio,
        public T015Contract $contract,
        public ?T015Contract $maybe,
        public Closure $factory,
        public T015Impl $impl,
        public T015Color $color,
    ) {}
}
