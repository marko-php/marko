<?php

declare(strict_types=1);

namespace Marko\Vite;

readonly class ViteInitSelections
{
    public function __construct(
        public ?string $inertiaPreset,
        public bool $tailwind,
    ) {}

    public function requiresAddonScaffolding(): bool
    {
        return $this->inertiaPreset !== null || $this->tailwind;
    }

    /**
     * @return list<string>
     */
    public function composerPackages(): array
    {
        $packages = [];

        if ($this->inertiaPreset !== null) {
            $packages[] = match ($this->inertiaPreset) {
                'vue' => 'marko/inertia-vue',
                'react' => 'marko/inertia-react',
                'svelte' => 'marko/inertia-svelte',
                default => throw new \InvalidArgumentException(sprintf('Unknown Inertia preset `%s`', $this->inertiaPreset)),
            };
        }

        if ($this->tailwind) {
            $packages[] = 'marko/tailwindcss';
        }

        return array_values(array_unique($packages));
    }
}
