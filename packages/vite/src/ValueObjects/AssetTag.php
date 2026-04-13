<?php

declare(strict_types=1);

namespace Marko\Vite\ValueObjects;

readonly class AssetTag
{
    /**
     * @param array<string, string> $attributes
     */
    public function __construct(
        public string $tag,
        public array $attributes,
    ) {}
}
