<?php

declare(strict_types=1);

namespace Marko\Core\Container;

class PreferenceRegistry
{
    /**
     * @var array<class-string, class-string>
     */
    private array $preferences = [];

    /**
     * Register a class preference.
     *
     * @param class-string $original The class to be replaced
     * @param class-string $replacement The replacement class
     */
    public function register(
        string $original,
        string $replacement,
    ): void {
        $this->preferences[$original] = $replacement;
    }

    /**
     * Get the final preference for a class (following the chain), or null if none registered.
     *
     * @param class-string $original
     * @return class-string|null
     */
    public function getPreference(
        string $original,
    ): ?string {
        if (!isset($this->preferences[$original])) {
            return null;
        }

        // Follow the preference chain to find the final replacement
        $current = $this->preferences[$original];
        while (isset($this->preferences[$current])) {
            $current = $this->preferences[$current];
        }

        return $current;
    }
}
