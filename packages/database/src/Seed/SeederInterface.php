<?php

declare(strict_types=1);

namespace Marko\Database\Seed;

interface SeederInterface
{
    public function run(): void;
}
