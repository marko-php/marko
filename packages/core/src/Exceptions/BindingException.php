<?php

declare(strict_types=1);

namespace Marko\Core\Exceptions;

class BindingException extends MarkoException
{
    public static function noImplementation(string $interface): self
    {
        return new self(
            message: "No implementation bound for interface: $interface",
            context: "Attempted to resolve '$interface' from the container",
            suggestion: "Register a binding in your module.php: 'bindings' => ['$interface' => YourImplementation::class]",
        );
    }
}
