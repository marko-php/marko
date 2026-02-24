<?php

declare(strict_types=1);

namespace Marko\Translation;

use Marko\Translation\Contracts\TranslationLoaderInterface;
use Marko\Translation\Contracts\TranslatorInterface;
use Marko\Translation\Exceptions\MissingTranslationException;
use Marko\Translation\Exceptions\TranslationException;

class Translator implements TranslatorInterface
{
    /** @var array<string, array<string, mixed>> */
    private array $loaded = [];

    public function __construct(
        private readonly TranslationLoaderInterface $loader,
        private string $locale,
        private readonly string $fallbackLocale,
    ) {}

    public function get(
        string $key,
        array $replacements = [],
        ?string $locale = null,
    ): string {
        $locale ??= $this->locale;

        $parsed = $this->parseKey($key);
        $namespace = $parsed['namespace'];
        $group = $parsed['group'];
        $keyPath = $parsed['key'];

        $value = $this->resolve($group, $keyPath, $locale, $namespace);

        if ($value === null && $locale !== $this->fallbackLocale) {
            $value = $this->resolve($group, $keyPath, $this->fallbackLocale, $namespace);
        }

        if ($value === null) {
            throw MissingTranslationException::forKey($key, $locale);
        }

        return $this->applyReplacements($value, $replacements);
    }

    public function choice(
        string $key,
        int $count,
        array $replacements = [],
        ?string $locale = null,
    ): string {
        $locale ??= $this->locale;

        $parsed = $this->parseKey($key);
        $namespace = $parsed['namespace'];
        $group = $parsed['group'];
        $keyPath = $parsed['key'];

        $value = $this->resolve($group, $keyPath, $locale, $namespace);

        if ($value === null && $locale !== $this->fallbackLocale) {
            $value = $this->resolve($group, $keyPath, $this->fallbackLocale, $namespace);
        }

        if ($value === null) {
            throw MissingTranslationException::forKey($key, $locale);
        }

        $selected = $this->selectPluralForm($value, $count);

        return $this->applyReplacements($selected, $replacements);
    }

    public function setLocale(
        string $locale,
    ): void {
        $this->locale = $locale;
    }

    public function getLocale(): string
    {
        return $this->locale;
    }

    /**
     * Parse a translation key into namespace, group, and key path.
     *
     * @return array{namespace: ?string, group: string, key: string}
     */
    private function parseKey(
        string $key,
    ): array {
        $namespace = null;

        if (str_contains($key, '::')) {
            [$namespace, $key] = explode('::', $key, 2);
        }

        $segments = explode('.', $key);
        $group = $segments[0];
        $keyPath = implode('.', array_slice($segments, 1));

        return [
            'namespace' => $namespace,
            'group' => $group,
            'key' => $keyPath,
        ];
    }

    /**
     * Resolve a key path from a loaded translation group.
     */
    private function resolve(
        string $group,
        string $keyPath,
        string $locale,
        ?string $namespace,
    ): ?string {
        $translations = $this->loadGroup($group, $locale, $namespace);

        $segments = explode('.', $keyPath);
        $current = $translations;

        foreach ($segments as $segment) {
            if (!is_array($current) || !array_key_exists($segment, $current)) {
                return null;
            }

            $current = $current[$segment];
        }

        if (!is_string($current)) {
            return null;
        }

        return $current;
    }

    /**
     * Load a translation group, caching the result.
     *
     * @return array<string, mixed>
     */
    private function loadGroup(
        string $group,
        string $locale,
        ?string $namespace,
    ): array {
        $cacheKey = $namespace !== null ? "$namespace::$locale.$group" : "$locale.$group";

        if (!isset($this->loaded[$cacheKey])) {
            $this->loaded[$cacheKey] = $this->loader->load($locale, $group, $namespace);
        }

        return $this->loaded[$cacheKey];
    }

    /**
     * Apply :placeholder replacements to a translation string.
     *
     * @param array<string, string> $replacements
     */
    private function applyReplacements(
        string $value,
        array $replacements,
    ): string {
        foreach ($replacements as $placeholder => $replacement) {
            $value = str_replace(":$placeholder", $replacement, $value);
        }

        return $value;
    }

    /**
     * Select the correct plural form from a pipe-separated string.
     *
     * Format: "zero:No items|one:One item|other::count items"
     *
     * @throws TranslationException
     */
    private function selectPluralForm(
        string $value,
        int $count,
    ): string {
        $segments = explode('|', $value);
        $forms = [];

        foreach ($segments as $segment) {
            $colonPos = strpos($segment, ':');

            if ($colonPos === false) {
                throw new TranslationException(
                    message: "Invalid pluralization format: '$segment'",
                    context: "Full plural string: $value",
                    suggestion: 'Use the format "zero:No items|one:One item|other::count items" with pipe-separated labeled variants',
                );
            }

            $label = substr($segment, 0, $colonPos);
            $text = substr($segment, $colonPos + 1);
            $forms[$label] = $text;
        }

        $form = match (true) {
            $count === 0 && isset($forms['zero']) => 'zero',
            $count === 1 && isset($forms['one']) => 'one',
            $count >= 2 && $count <= 4 && isset($forms['few']) => 'few',
            $count >= 5 && isset($forms['many']) => 'many',
            default => 'other',
        };

        if (!isset($forms[$form])) {
            throw new TranslationException(
                message: "Plural form '$form' not found in translation string",
                context: "Full plural string: $value, Count: $count",
                suggestion: "Add a '$form' variant to the plural string, e.g., '$form:Your text here'",
            );
        }

        return $forms[$form];
    }
}
