<?php

declare(strict_types=1);

namespace Marko\TailwindCss;

use Marko\Config\ConfigRepositoryInterface;
use Marko\Core\Container\ContainerInterface;
use Marko\TailwindCss\Contracts\ContentPathProviderInterface;
use RuntimeException;

class ContentPathCollector
{
    public function __construct(
        private readonly ContentPathProviderInterface $defaultProvider,
        private readonly ConfigRepositoryInterface $config,
        private readonly ContainerInterface $container,
    ) {}

    /**
     * @return array<string>
     */
    public function collect(): array
    {
        if (!$this->config->getBool('tailwindcss.enabled')) {
            return [];
        }

        $paths = [
            ...$this->defaultProvider->contentPaths(),
            ...$this->extraContentPaths(),
        ];

        foreach ($this->providerClasses() as $providerClass) {
            $paths = [...$paths, ...$this->provider($providerClass)->contentPaths()];
        }

        return $this->normalize($paths);
    }

    /**
     * @return array<string>
     */
    private function extraContentPaths(): array
    {
        if (!$this->config->has('tailwindcss.extra_content_paths')) {
            return [];
        }

        return array_values(array_map(
            'strval',
            $this->config->getArray('tailwindcss.extra_content_paths'),
        ));
    }

    /**
     * @return array<class-string<ContentPathProviderInterface>>
     */
    private function providerClasses(): array
    {
        if (!$this->config->has('tailwindcss.content_path_providers')) {
            return [];
        }

        $configured = $this->config->getArray('tailwindcss.content_path_providers');
        $classes = [];

        foreach ($configured as $providerClass) {
            if (!is_string($providerClass) || $providerClass === '') {
                continue;
            }

            if ($providerClass === $this->defaultProvider::class) {
                continue;
            }

            $classes[] = $providerClass;
        }

        return array_values(array_unique($classes));
    }

    /**
     * @param class-string<ContentPathProviderInterface> $providerClass
     */
    private function provider(string $providerClass): ContentPathProviderInterface
    {
        $provider = $this->container->get($providerClass);

        if (!$provider instanceof ContentPathProviderInterface) {
            throw new RuntimeException(sprintf(
                'Tailwind content path provider "%s" must implement %s.',
                $providerClass,
                ContentPathProviderInterface::class,
            ));
        }

        return $provider;
    }

    /**
     * @param array<string> $paths
     * @return array<string>
     */
    private function normalize(array $paths): array
    {
        $normalized = [];

        foreach ($paths as $path) {
            $path = str_replace('\\', '/', trim($path));

            if (str_starts_with($path, './')) {
                $path = substr($path, 2);
            }

            if ($path === '') {
                continue;
            }

            if (!in_array($path, $normalized, true)) {
                $normalized[] = $path;
            }
        }

        return $normalized;
    }
}
