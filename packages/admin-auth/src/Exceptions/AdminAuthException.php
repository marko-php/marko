<?php

declare(strict_types=1);

namespace Marko\AdminAuth\Exceptions;

use Marko\Core\Exceptions\MarkoException;
use Throwable;

class AdminAuthException extends MarkoException
{
    /**
     * @param string|null $existingClass Section class that declared the key first, when known
     * @param string|null $duplicateClass Section class that declared it again, when known
     */
    public static function duplicatePermission(
        string $key,
        ?string $existingClass = null,
        ?string $duplicateClass = null,
    ): self {
        $message = ($existingClass !== null && $duplicateClass !== null)
            ? "Permission with key '$key' is declared by both '$existingClass' and '$duplicateClass'"
            : "Permission with key '$key' is already registered";

        return new self(
            message: $message,
            context: "While registering permission '$key'",
            suggestion: 'Ensure each permission has a unique key',
        );
    }

    /**
     * A permission key is outside IdentifierFormat::PERMISSION_KEY_PATTERN.
     *
     * @param string|null $declaringClass Section class whose #[AdminPermission] declares the key, when known
     */
    public static function invalidPermissionKey(
        string $key,
        ?string $declaringClass = null,
    ): self {
        $message = $declaringClass !== null
            ? "Permission key '$key' declared by #[AdminPermission] on '$declaringClass' is not a valid permission key"
            : "Permission key '$key' is not a valid permission key";
        $example = strtolower(trim($key));

        return new self(
            message: $message,
            context: "While registering or saving permission '$key'",
            suggestion: 'Permission keys are lowercase segments of a-z, 0-9, "_" and "-" separated by dots, such as '
                . "'$example'; segments after the first may contain the \"*\" wildcard, and \"*\" "
                . 'alone grants everything',
        );
    }

    /**
     * A role slug is outside IdentifierFormat::ROLE_SLUG_PATTERN.
     */
    public static function invalidRoleSlug(
        string $slug,
    ): self {
        return new self(
            message: "Role slug '$slug' is not a valid role slug",
            context: "While saving or checking role slug '$slug'",
            suggestion: 'Role slugs are lowercase segments of a-z, 0-9, "_" and "-" separated by dots, such as '
                . "'content-editor'",
        );
    }

    /**
     * A section's #[AdminPermission] key was already registered by hand before boot registered it.
     */
    public static function permissionAlreadyRegistered(
        string $key,
        string $sectionClass,
        ?Throwable $previous = null,
    ): self {
        return new self(
            message: "Permission with key '$key' declared by #[AdminPermission] on '$sectionClass' is already registered",
            context: "While registering the #[AdminPermission] entries of admin section '$sectionClass' at boot",
            suggestion: '#[AdminPermission] entries are registered automatically at boot. Remove the manual '
                . "PermissionRegistryInterface::register() call for '$key', or give one of them a different key",
            previous: $previous,
        );
    }
}
