<?php

declare(strict_types=1);

namespace Marko\Database\Seed;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
readonly class Seeder
{
    public function __construct(
        public string $name,
        public int $order = 0,
    ) {}
}
