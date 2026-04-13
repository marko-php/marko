<?php

declare(strict_types=1);

namespace Marko\Vite\ValueObjects;

readonly class DevServer
{
    public function __construct(
        public string $url,
    ) {}

    public function assetUrl(string $path): string
    {
        return rtrim($this->url, '/') . '/' . ltrim($path, '/');
    }
}
