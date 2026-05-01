<?php

declare(strict_types=1);

namespace Marko\Core\Container;

/**
 * Represents a discovered preference: a class annotated with #[Preference]
 * that replaces another class.
 */
readonly class PreferenceRecord
{
    /**
     * @param class-string $replacement The class carrying the #[Preference] attribute
     * @param class-string $replaces The class being replaced
     */
    public function __construct(
        public string $replacement,
        public string $replaces,
    ) {}
}
