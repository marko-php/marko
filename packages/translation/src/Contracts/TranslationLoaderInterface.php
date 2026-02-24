<?php

declare(strict_types=1);

namespace Marko\Translation\Contracts;

interface TranslationLoaderInterface
{
    /**
     * Load translations for the given locale and group.
     *
     * @return array<string, mixed>
     */
    public function load(
        string $locale,
        string $group,
        ?string $namespace = null,
    ): array;
}
