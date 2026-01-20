<?php

declare(strict_types=1);

namespace Demo\Custom\Plugins;

use Demo\Greeter\DefaultGreeter;
use Marko\Core\Attributes\After;
use Marko\Core\Attributes\Before;
use Marko\Core\Attributes\Plugin;

#[Plugin(target: DefaultGreeter::class)]
class GreeterPlugin
{
    #[Before(sortOrder: 10)]
    public function beforeGreet(string $name): ?string
    {
        // Log or modify before greeting (returning null continues execution)
        // Return a string to short-circuit
        return null;
    }

    #[After(sortOrder: 10)]
    public function afterGreet(
        string $result,
        string $name,
    ): string {
        // Modify the greeting result
        return $result . ' [plugin enhanced]';
    }
}
