<?php

declare(strict_types=1);

namespace Marko\Vite\Contracts;

use Marko\Vite\ValueObjects\ResolvedEntrypointCollection;

interface TagRendererInterface
{
    public function renderTags(ResolvedEntrypointCollection $collection): string;

    public function renderScripts(ResolvedEntrypointCollection $collection): string;

    public function renderStyles(ResolvedEntrypointCollection $collection): string;
}
