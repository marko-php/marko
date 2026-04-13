<?php

declare(strict_types=1);

namespace Marko\Vite;

use Marko\Vite\Contracts\AssetUrlGeneratorInterface;
use Marko\Vite\ValueObjects\ViteConfig;

class AssetUrlGenerator implements AssetUrlGeneratorInterface
{
    public function __construct(
        private readonly ViteConfig $config,
    ) {}

    public function generate(string $assetPath): string
    {
        $base = rtrim($this->config->assetsBaseUrl, '/');
        $directory = trim($this->config->buildDirectory, '/');
        $path = ltrim($assetPath, '/');

        $segments = [];

        if ($base !== '') {
            $segments[] = $base;
        }

        if ($directory !== '') {
            $segments[] = $directory;
        }

        $segments[] = $path;

        $url = implode('/', $segments);

        if ($base === '') {
            return '/' . ltrim($url, '/');
        }

        return $url;
    }
}
