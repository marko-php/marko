<?php

declare(strict_types=1);

namespace Marko\Core\Exceptions;

class PreferenceConflictException extends MarkoException
{
    /**
     * @param string[] $modules
     */
    public static function multiplePreferences(
        string $original,
        array $modules,
    ): self {
        $moduleList = implode(', ', $modules);

        return new self(
            message: "Multiple modules define a Preference for the same class '$original': $moduleList",
            context: "While discovering Preferences for '$original'",
            suggestion: 'Only one module at the same priority level can define a Preference for a class. Move one Preference to a higher-priority module (app/ overrides modules/ overrides vendor/) or remove the duplicate',
        );
    }

    public static function circularPreference(
        string $original,
        string $cycleTarget,
    ): self {
        return new self(
            message: "Circular preference detected: '$cycleTarget' is part of a cycle starting from '$original'",
            context: "While resolving preference chain for '$original'",
            suggestion: 'Check your #[Preference] attributes for circular references (e.g., A replaces B and B replaces A) and remove the cycle',
        );
    }
}
