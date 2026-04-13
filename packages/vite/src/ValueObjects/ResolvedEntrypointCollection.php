<?php

declare(strict_types=1);

namespace Marko\Vite\ValueObjects;

readonly class ResolvedEntrypointCollection
{
    /**
     * @param array<string> $entrypoints
     * @param array<ResolvedAsset> $preloads
     * @param array<ResolvedAsset> $styles
     * @param array<ResolvedAsset> $scripts
     */
    public function __construct(
        public array $entrypoints,
        public array $preloads,
        public array $styles,
        public array $scripts,
        public bool $development,
    ) {}
}
