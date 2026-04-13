<?php

declare(strict_types=1);

namespace Marko\Vite;

use Marko\Vite\Contracts\ViteManagerInterface;

class ViteViewHelper
{
    public function __construct(
        private readonly ViteManagerInterface $vite,
    ) {}

    public function tags(string|array|null $entrypoints = null): string
    {
        return $this->vite->tags($entrypoints);
    }

    public function scripts(string|array|null $entrypoints = null): string
    {
        return $this->vite->scripts($entrypoints);
    }

    public function styles(string|array|null $entrypoints = null): string
    {
        return $this->vite->styles($entrypoints);
    }
}
