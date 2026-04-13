<?php

declare(strict_types=1);

namespace Marko\Vite;

use Marko\Vite\Contracts\DefaultEntrypointProviderInterface;
use Marko\Vite\ValueObjects\ViteConfig;

class DefaultEntrypointProvider implements DefaultEntrypointProviderInterface
{
    public function __construct(
        private readonly ViteConfig $config,
    ) {}

    public function entrypoints(): array
    {
        if ($this->config->defaultEntrypoints !== []) {
            return $this->config->defaultEntrypoints;
        }

        return [$this->resolveDefaultEntrypoint()];
    }

    protected function resolveDefaultEntrypoint(): string
    {
        return $this->config->rootEntrypointPath;
    }
}
