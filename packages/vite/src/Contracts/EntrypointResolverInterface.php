<?php

declare(strict_types=1);

namespace Marko\Vite\Contracts;

use Marko\Vite\ValueObjects\ResolvedEntrypointCollection;

interface EntrypointResolverInterface
{
    public function resolve(string|array|null $entrypoints = null): ResolvedEntrypointCollection;
}
