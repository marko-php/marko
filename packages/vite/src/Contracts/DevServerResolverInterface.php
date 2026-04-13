<?php

declare(strict_types=1);

namespace Marko\Vite\Contracts;

use Marko\Vite\ValueObjects\DevServer;

interface DevServerResolverInterface
{
    public function isDevelopment(): bool;

    public function resolve(): DevServer;
}
