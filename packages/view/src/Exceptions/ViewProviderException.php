<?php

declare(strict_types=1);

namespace Marko\View\Exceptions;

class ViewProviderException extends ViewException
{
    public static function invalidFunctionName(
        string $name,
    ): self {
        return new self(
            message: "'$name' is not a valid template function name.",
            context: 'Template function names are registered with every view engine, so they must be plain identifiers.',
            suggestion: 'Use letters, digits and underscores only, starting with a letter (e.g. csrf_field).',
        );
    }

    /**
     * @param class-string $attribute
     * @param class-string $interface
     */
    public static function notAProvider(
        string $className,
        string $attribute,
        string $interface,
    ): self {
        $attributeName = substr(strrchr($attribute, '\\') ?: $attribute, 1);

        return new self(
            message: "$className is marked #[$attributeName] and must implement $interface.",
            context: "Template providers are discovered by the #[$attributeName] attribute and called through $interface.",
            suggestion: "Implement $interface on $className, or remove the #[$attributeName] attribute.",
        );
    }

    public static function duplicateFunction(
        string $name,
        string $firstProvider,
        string $secondProvider,
    ): self {
        return new self(
            message: "Template function '$name' is registered by both $firstProvider and $secondProvider.",
            context: 'Template function names are global to every template, so each name may have only one provider.',
            suggestion: 'Rename one of the functions, or replace the provider you want to change with a Preference '
                . 'instead of registering the name a second time.',
        );
    }

    public static function duplicateDataKey(
        string $key,
        string $firstProvider,
        string $secondProvider,
    ): self {
        return new self(
            message: "View data key '$key' is supplied by both $firstProvider and $secondProvider.",
            context: 'Shared view data is merged into every render, so each key may have only one provider.',
            suggestion: 'Rename one of the keys, or replace the provider you want to change with a Preference '
                . 'instead of supplying the key a second time.',
        );
    }
}
