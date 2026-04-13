<?php

declare(strict_types=1);

namespace Marko\Vite\Contracts;

use Marko\Vite\ValueObjects\ResolvedEntrypointCollection;

interface ViteManagerInterface
{
    public function isDevelopment(): bool;

    public function resolve(string|array|null $entrypoints = null): ResolvedEntrypointCollection;

    public function tags(string|array|null $entrypoints = null): string;

    public function scripts(string|array|null $entrypoints = null): string;

    public function styles(string|array|null $entrypoints = null): string;
}
