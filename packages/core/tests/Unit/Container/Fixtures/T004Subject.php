<?php

declare(strict_types=1);

namespace Marko\Core\Tests\Unit\Container\Fixtures;

readonly class T004Subject
{
    public function __construct(
        public string $name,
        public int $port,
        public float $ratio,
        public bool $flag,
        public array $list,
        public T004Contract $contract,
        public ?T004Contract $maybe,
        public string $built,
    ) {}
}
