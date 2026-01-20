<?php

declare(strict_types=1);

namespace Demo\Greeter\Contracts;

interface GreeterInterface
{
    public function greet(string $name): string;
}
