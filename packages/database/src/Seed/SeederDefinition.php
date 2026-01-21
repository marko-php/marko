<?php

declare(strict_types=1);

namespace Marko\Database\Seed;

/**
 * Value object representing a discovered seeder.
 */
readonly class SeederDefinition
{
    public function __construct(
        public string $seederClass,
        public string $name,
        public int $order = 0,
    ) {}
}
