<?php

declare(strict_types=1);

namespace Marko\Core\Exceptions;

use Error;
use Exception;
use Throwable;

class MarkoException extends Exception
{
    public function __construct(
        string $message,
        private readonly string $context = '',
        private readonly string $suggestion = '',
        int $code = 0,
        ?Throwable $previous = null,
    ) {
        parent::__construct(
            $message,
            $code,
            $previous,
        );
    }

    public function getContext(): string
    {
        return $this->context;
    }

    public function getSuggestion(): string
    {
        return $this->suggestion;
    }

    /**
     * Infer a Composer package name from a fully-qualified class name.
     *
     * Maps Marko namespaces to package names:
     *   Marko\Core\*           → marko/core
     *   Marko\Admin\*          → marko/admin
     *   Marko\AdminAuth\*      → marko/admin-auth
     *   Marko\Database\MySql\* → marko/database-mysql
     */
    public static function inferPackageName(
        string $className,
    ): ?string {
        $parts = explode('\\', ltrim($className, '\\'));

        if (count($parts) < 2 || $parts[0] !== 'Marko') {
            return null;
        }

        $segment = $parts[1];
        $structural = ['Contracts', 'Attributes', 'Exceptions', 'Events', 'Commands'];

        // For nested namespaces like Marko\Database\MySql\*, check if the third
        // segment is a sub-package (not a structural directory like Contracts/)
        if (count($parts) >= 4 && !in_array($parts[2], $structural, true)) {
            return 'marko/' . self::camelToKebab($segment) . '-' . self::camelToKebab($parts[2]);
        }

        return 'marko/' . self::camelToKebab($segment);
    }

    /**
     * Convert a CamelCase string to kebab-case.
     */
    private static function camelToKebab(
        string $value,
    ): string {
        return strtolower((string) preg_replace('/([a-z])([A-Z])/', '$1-$2', $value));
    }

    /**
     * Check if an Error message matches a class/interface/trait not found pattern.
     *
     * @return string|null The missing class name, or null if not a class-not-found error
     */
    public static function extractMissingClass(
        Error $error,
    ): ?string {
        if (preg_match(
            '/(?:Attribute class|Class|Interface|Trait|Enum)\s+"([^"]+)"\s+not found/',
            $error->getMessage(),
            $matches,
        )) {
            return $matches[1];
        }

        return null;
    }
}
