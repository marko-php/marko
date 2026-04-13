<?php

declare(strict_types=1);

namespace Marko\Inertia;

use Marko\Config\ConfigRepositoryInterface;

readonly class InertiaConfig
{
    public function __construct(
        private ConfigRepositoryInterface $config,
    ) {}

    public function version(): ?string
    {
        $version = $this->config->get('inertia.version');

        return is_string($version) && $version !== '' ? $version : null;
    }

    public function rootElementId(): string
    {
        return $this->config->getString($this->firstDefinedKey([
            'inertia.root.id',
            'inertia.root_view.id',
        ]));
    }

    public function rootTitle(): string
    {
        return $this->config->getString($this->firstDefinedKey([
            'inertia.root.title',
            'inertia.root_view.title',
        ]));
    }

    /** @deprecated Use rootTitle() instead. */
    public function rootTittle(): string
    {
        return $this->rootTitle();
    }

    /**
     * @return array<string>
     */
    public function pagePaths(): array
    {
        return array_values(array_filter(
            array_map(
                static fn (mixed $path): string => trim((string) $path, " \t\n\r\0\x0B/"),
                $this->config->getArray($this->firstDefinedKey([
                    'inertia.page.paths',
                    'inertia.pages.paths',
                ])),
            ),
            static fn (string $path): bool => $path !== '',
        ));
    }

    /**
     * @return array<string>
     */
    public function pageExtensions(): array
    {
        return array_values(array_filter(
            array_map(
                static fn (mixed $extension): string => ltrim(strtolower(trim((string) $extension)), '.'),
                $this->config->getArray($this->firstDefinedKey([
                    'inertia.page.extensions',
                    'inertia.pages.extensions',
                ])),
            ),
            static fn (string $extension): bool => $extension !== '',
        ));
    }

    public function shouldEnsurePagesExist(): bool
    {
        if ($this->isTestingEnvironment() && $this->config->has('inertia.testing.ensure_pages_exist')) {
            return $this->config->getBool('inertia.testing.ensure_pages_exist');
        }

        return $this->config->getBool($this->firstDefinedKey([
            'inertia.page.ensure_pages_exist',
            'inertia.pages.ensure_pages_exist',
        ]));
    }

    public function encryptHistory(): bool
    {
        return $this->config->getBool('inertia.history.encrypt');
    }

    public function ssrEnabled(): bool
    {
        return $this->config->getBool('inertia.ssr.enabled');
    }

    public function ssrUrl(): string
    {
        return $this->config->getString('inertia.ssr.url');
    }

    public function ssrBundle(): ?string
    {
        $bundle = trim((string) $this->config->get('inertia.ssr.bundle', ''));

        return $bundle !== '' ? $bundle : null;
    }

    public function shouldEnsureSsrBundleExists(): bool
    {
        return $this->config->getBool('inertia.ssr.ensure_bundle_exists');
    }

    public function shouldThrowOnSsrError(): bool
    {
        return $this->config->getBool('inertia.ssr.throw_on_error');
    }

    public function ssrBundleExists(): bool
    {
        $bundle = $this->ssrBundle();

        return $bundle === null || is_file($bundle);
    }

    /**
     * @param array<string> $keys
     */
    private function firstDefinedKey(array $keys): string
    {
        foreach ($keys as $key) {
            if ($this->config->has($key)) {
                return $key;
            }
        }

        return $keys[0];
    }

    private function isTestingEnvironment(): bool
    {
        if (! $this->config->has('app.env')) {
            return false;
        }

        return in_array($this->config->getString('app.env'), ['test', 'testing'], true);
    }
}
