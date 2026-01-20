<?php

declare(strict_types=1);

namespace Demo\Custom;

use Demo\Greeter\Contracts\GreeterInterface;
use Demo\Greeter\DefaultGreeter;
use Marko\Core\Attributes\Preference;

#[Preference(replaces: DefaultGreeter::class)]
class CustomGreeter extends DefaultGreeter implements GreeterInterface
{
    public function greet(string $name): string
    {
        return "Greetings, $name! (customized)";
    }
}
