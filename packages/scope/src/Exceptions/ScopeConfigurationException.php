<?php

declare(strict_types=1);

namespace Marko\Scope\Exceptions;

use Marko\Core\Exceptions\MarkoException;
use ReflectionClass;

/**
 * Exception thrown when scope configuration is malformed or invalid.
 */
class ScopeConfigurationException extends MarkoException
{
    public static function malformedString(string $scope): self
    {
        return new self(
            message: "Malformed scope string \"$scope\": expected \"axis:path\" format",
            context: "Parsing scope string \"$scope\" via Scope::fromString()",
            suggestion: 'Provide a scope string in the format "axisName:path", e.g. "geo:eu.de"',
        );
    }

    public static function malformedConfig(
        string $axis,
        string $reason,
    ): self {
        return new self(
            message: "Scope configuration for axis '$axis' is malformed: $reason",
            context: "Parsing scope configuration for axis '$axis'",
            suggestion: "Review the scope configuration for axis '$axis' and correct the malformed definition",
        );
    }

    public static function duplicatePath(string $path): self
    {
        return new self(
            message: "Duplicate scope path '$path' declared in hierarchy",
            context: "Building ScopeHierarchy with path '$path' that has already been declared",
            suggestion: "Remove the duplicate declaration of '$path' from the scope configuration",
        );
    }

    /**
     * @param array<string, list<string>> $scopedProperties Map of property name => list of axes
     */
    public static function missingOverridesExtender(
        string $parentClass,
        array $scopedProperties,
    ): self {
        $propertyLines = [];
        foreach ($scopedProperties as $property => $axes) {
            $axesStr = count($axes) > 0 ? implode(', ', $axes) : 'none';
            $propertyLines[] = "  - $property (axes: $axesStr)";
        }
        $propertiesContext = implode("\n", $propertyLines);

        $shortName = class_exists($parentClass) ? (new ReflectionClass($parentClass))->getShortName() : $parentClass;
        $suggestion = "#[Table(extends: $parentClass::class)]\nclass {$shortName}Overrides extends ScopedOverridesEntity {}";

        return new self(
            message: "Entity '$parentClass' declares Scoped properties but has no ScopedOverridesEntity extender registered.",
            context: "Validating scoped entity '$parentClass'.\nScoped properties:\n$propertiesContext",
            suggestion: $suggestion,
        );
    }

    public static function wrongOverridesExtenderBase(
        string $parentClass,
        string $extenderClass,
    ): self {
        return new self(
            message: "Extender '$extenderClass' for '$parentClass' does not extend ScopedOverridesEntity.",
            context: "Validating scoped entity '$parentClass': found extender '$extenderClass' but it does not extend ScopedOverridesEntity.",
            suggestion: "Make '$extenderClass' extend ScopedOverridesEntity instead of Entity directly.",
        );
    }

    public static function traitAndCompanionConflict(
        string $parentClass,
        string $extenderClass,
    ): self {
        return new self(
            message: "Entity '$parentClass' uses both the HasScopes trait and has a ScopedOverridesEntity extender '$extenderClass' — they both contribute a `scopes` column.",
            context: "Validating scoped entity '$parentClass': found ScopedOverridesEntity extender '$extenderClass' but the entity already uses the HasScopes trait.",
            suggestion: 'Remove either the `use HasScopes;` trait or the extender class — they both contribute a `scopes` column',
        );
    }
}
