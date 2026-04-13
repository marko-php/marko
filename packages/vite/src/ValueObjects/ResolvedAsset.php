<?php

declare(strict_types=1);

namespace Marko\Vite\ValueObjects;

readonly class ResolvedAsset
{
    public function __construct(
        public string $entrypoint,
        public string $url,
        public string $kind,
        public bool $development,
    ) {}
}
