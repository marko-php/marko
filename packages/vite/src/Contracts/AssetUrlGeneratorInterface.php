<?php

declare(strict_types=1);

namespace Marko\Vite\Contracts;

interface AssetUrlGeneratorInterface
{
    public function generate(string $assetPath): string;
}
