<?php

declare(strict_types=1);

namespace Marko\Admin\Exceptions;

use Marko\Core\Exceptions\MarkoException;
use Throwable;

class AdminException extends MarkoException
{
    /**
     * @param string|null $existingClass Class of the section that already holds the id, when known
     * @param string|null $duplicateClass Class of the section that tried to take the id, when known
     */
    public static function duplicateSection(
        string $id,
        ?string $existingClass = null,
        ?string $duplicateClass = null,
    ): self {
        $message = ($existingClass !== null && $duplicateClass !== null)
            ? "Admin section with id '$id' is declared by both '$existingClass' and '$duplicateClass'"
            : "Admin section with id '$id' is already registered";

        return new self(
            message: $message,
            context: "While registering admin section '$id'",
            suggestion: 'Ensure each admin section has a unique id. #[AdminSection] classes are registered '
                . 'automatically at boot, so remove any manual register() call for them',
        );
    }

    public static function sectionIdMismatch(
        string $className,
        string $attributeId,
        string $instanceId,
    ): self {
        return new self(
            message: "Admin section '$className' declares id '$attributeId' in #[AdminSection] but getId() returns '$instanceId'",
            context: "While building admin section '$className' on first use",
            suggestion: "Make getId() return '$attributeId', or change the #[AdminSection] id to '$instanceId'",
        );
    }

    public static function sectionBuildFailed(
        string $id,
        string $className,
        Throwable $previous,
    ): self {
        return new self(
            message: "Admin section '$id' ($className) could not be built: {$previous->getMessage()}",
            context: "While building admin section '$id' on first use",
            suggestion: "Fix the error in '$className' or one of its constructor dependencies. "
                . 'Sections are built on first use, so the error only affects requests that need admin sections',
            previous: $previous,
        );
    }

    public static function sectionClassNotFound(
        string $className,
    ): self {
        return new self(
            message: "Admin section class '$className' does not exist",
            context: "While registering admin section '$className' at boot",
            suggestion: 'The discovery cache names a class that was removed or renamed. '
                . 'Run `marko discovery:cache` to recompile the cache',
        );
    }

    public static function sectionNotFound(
        string $id,
    ): self {
        return new self(
            message: "Admin section '$id' not found",
            context: "While retrieving admin section '$id'",
            suggestion: 'Ensure the admin section is registered before accessing it',
        );
    }

    public static function missingSectionAttribute(
        string $className,
    ): self {
        return new self(
            message: "Class '$className' is not marked with #[AdminSection]",
            context: "While parsing admin section class '$className'",
            suggestion: "Add #[AdminSection(id: ..., label: ...)] to the class, or don't pass it to admin section discovery",
        );
    }

    public static function sectionMustImplementInterface(
        string $className,
    ): self {
        return new self(
            message: "Class '$className' has #[AdminSection] attribute but does not implement AdminSectionInterface",
            context: "While discovering admin sections in class '$className'",
            suggestion: "Ensure '$className' implements Marko\\Admin\\Contracts\\AdminSectionInterface",
        );
    }
}
