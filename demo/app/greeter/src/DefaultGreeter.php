<?php

declare(strict_types=1);

namespace Demo\Greeter;

use Demo\Greeter\Contracts\GreeterInterface;

class DefaultGreeter implements GreeterInterface
{
    public function greet(string $name): string
    {
        return "Hello, $name!";
    }
}
