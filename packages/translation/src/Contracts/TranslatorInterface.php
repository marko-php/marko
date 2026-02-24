<?php

declare(strict_types=1);

namespace Marko\Translation\Contracts;

use Marko\Translation\Exceptions\MissingTranslationException;

interface TranslatorInterface
{
    /**
     * Get a translated string for the given key.
     *
     * @param array<string, string> $replacements
     *
     * @throws MissingTranslationException
     */
    public function get(
        string $key,
        array $replacements = [],
        ?string $locale = null,
    ): string;

    /**
     * Get a translated string with pluralization support.
     *
     * @param array<string, string> $replacements
     *
     * @throws MissingTranslationException
     */
    public function choice(
        string $key,
        int $count,
        array $replacements = [],
        ?string $locale = null,
    ): string;

    /**
     * Set the current locale.
     */
    public function setLocale(string $locale): void;

    /**
     * Get the current locale.
     */
    public function getLocale(): string;
}
