<?php

declare(strict_types=1);

namespace Marko\TailwindCss;

use Marko\Config\ConfigRepositoryInterface;
use Marko\TailwindCss\Contracts\TailwindEntrypointProviderInterface;

class DefaultTailwindEntrypointProvider implements TailwindEntrypointProviderInterface
{
    public function __construct(
        private readonly ConfigRepositoryInterface $config,
    ) {}

    public function entrypoints(): array
    {
        if (!$this->config->getBool('tailwindcss.enabled')) {
            return [];
        }

        return [$this->config->getString('tailwindcss.entrypoints.css')];
    }
}
