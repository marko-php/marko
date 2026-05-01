<?php

declare(strict_types=1);

namespace Marko\Core\Container;

use Marko\Core\Exceptions\PreferenceConflictException;

class PreferenceRegistry
{
    private const array SOURCE_PRIORITY = [
        'vendor' => 0,
        'modules' => 1,
        'app' => 2,
    ];

    /**
     * @var array<class-string, class-string>
     */
    private array $preferences = [];

    /**
     * @var array<class-string, array{module: string, source: string}>
     */
    private array $sources = [];

    /**
     * Register a class preference.
     *
     * @param class-string $original The class to be replaced
     * @param class-string $replacement The replacement class
     * @param string $moduleName The module registering this preference
     * @param string $moduleSource The source directory (vendor, modules, or app)
     * @throws PreferenceConflictException When same-priority modules prefer the same class
     */
    public function register(
        string $original,
        string $replacement,
        string $moduleName = '',
        string $moduleSource = '',
    ): void {
        if (isset($this->sources[$original]) && $moduleName !== '') {
            $existing = $this->sources[$original];
            $existingPriority = self::SOURCE_PRIORITY[$existing['source']] ?? 0;
            $newPriority = self::SOURCE_PRIORITY[$moduleSource] ?? 0;

            // Same priority = conflict
            if ($newPriority === $existingPriority) {
                throw PreferenceConflictException::multiplePreferences(
                    $original,
                    [$existing['module'], $moduleName],
                );
            }

            // Lower priority cannot override higher priority
            if ($newPriority < $existingPriority) {
                return;
            }
        }

        $this->preferences[$original] = $replacement;

        if ($moduleName !== '') {
            $this->sources[$original] = [
                'module' => $moduleName,
                'source' => $moduleSource,
            ];
        }
    }

    /**
     * Get the final preference for a class (following the chain), or null if none registered.
     *
     * @param class-string $original
     * @return class-string|null
     * @throws PreferenceConflictException When a circular preference chain is detected
     */
    public function getPreference(
        string $original,
    ): ?string {
        if (!isset($this->preferences[$original])) {
            return null;
        }

        // Follow the preference chain to find the final replacement
        $visited = [$original => true];
        $current = $this->preferences[$original];

        while (isset($this->preferences[$current])) {
            if (isset($visited[$current])) {
                throw PreferenceConflictException::circularPreference($original, $current);
            }
            $visited[$current] = true;
            $current = $this->preferences[$current];
        }

        return $current;
    }
}
