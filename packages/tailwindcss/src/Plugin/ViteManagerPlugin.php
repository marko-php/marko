<?php

declare(strict_types=1);

namespace Marko\TailwindCss\Plugin;

use Marko\Config\ConfigRepositoryInterface;
use Marko\Core\Attributes\Before;
use Marko\Core\Attributes\Plugin;
use Marko\TailwindCss\TailwindAssetRegistry;
use Marko\Vite\Contracts\DefaultEntrypointProviderInterface;
use Marko\Vite\Contracts\ViteManagerInterface;

#[Plugin(target: ViteManagerInterface::class)]
class ViteManagerPlugin
{
    public function __construct(
        private readonly ConfigRepositoryInterface $config,
        private readonly TailwindAssetRegistry $registry,
        private readonly DefaultEntrypointProviderInterface $defaultEntrypointProvider,
    ) {}

    #[Before(method: 'resolve')]
    public function beforeResolve(string|array|null $entrypoints = null): array
    {
        return [$this->mergeEntrypoints($entrypoints)];
    }

    #[Before(method: 'tags')]
    public function beforeTags(string|array|null $entrypoints = null): array
    {
        return [$this->mergeEntrypoints($entrypoints)];
    }

    #[Before(method: 'styles')]
    public function beforeStyles(string|array|null $entrypoints = null): array
    {
        return [$this->mergeEntrypoints($entrypoints)];
    }

    protected function mergeEntrypoints(string|array|null $entrypoints): array
    {
        if (
            !$this->config->getBool('tailwindcss.enabled')
            || !$this->config->getBool('tailwindcss.auto_include_with_vite')
        ) {
            return $this->normalizeEntrypoints($entrypoints);
        }

        return array_values(array_unique([
            ...$this->normalizeEntrypoints($entrypoints),
            ...$this->registry->entrypoints(),
        ]));
    }

    /**
     * @return array<string>
     */
    protected function normalizeEntrypoints(string|array|null $entrypoints): array
    {
        if ($entrypoints === null) {
            return $this->defaultEntrypointProvider->entrypoints();
        }

        if (is_string($entrypoints)) {
            return [$entrypoints];
        }

        return array_values(array_map('strval', $entrypoints));
    }
}
