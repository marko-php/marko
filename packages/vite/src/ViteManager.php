<?php

declare(strict_types=1);

namespace Marko\Vite;

use Marko\Vite\Contracts\DevServerResolverInterface;
use Marko\Vite\Contracts\EntrypointResolverInterface;
use Marko\Vite\Contracts\TagRendererInterface;
use Marko\Vite\Contracts\ViteManagerInterface;
use Marko\Vite\ValueObjects\ResolvedEntrypointCollection;

class ViteManager implements ViteManagerInterface
{
    public function __construct(
        private readonly DevServerResolverInterface $devServerResolver,
        private readonly EntrypointResolverInterface $entrypointResolver,
        private readonly TagRendererInterface $tagRenderer,
    ) {}

    public function isDevelopment(): bool
    {
        return $this->devServerResolver->isDevelopment();
    }

    public function resolve(string|array|null $entrypoints = null): ResolvedEntrypointCollection
    {
        return $this->entrypointResolver->resolve($entrypoints);
    }

    public function tags(string|array|null $entrypoints = null): string
    {
        return $this->tagRenderer->renderTags($this->resolve($entrypoints));
    }

    public function scripts(string|array|null $entrypoints = null): string
    {
        return $this->tagRenderer->renderScripts($this->resolve($entrypoints));
    }

    public function styles(string|array|null $entrypoints = null): string
    {
        return $this->tagRenderer->renderStyles($this->resolve($entrypoints));
    }
}
