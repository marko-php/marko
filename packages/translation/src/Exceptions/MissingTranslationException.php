<?php

declare(strict_types=1);

namespace Marko\Translation\Exceptions;

class MissingTranslationException extends TranslationException
{
    public static function forKey(
        string $key,
        string $locale,
    ): self {
        $parts = explode('.', $key);
        $group = $parts[0];
        $keyPath = implode('.', array_slice($parts, 1));

        return new self(
            message: "Translation key not found: '$key' for locale '$locale'",
            context: "Key: $key, Locale: $locale, Group: $group, Key path: $keyPath",
            suggestion: "Create the translation file at lang/$locale/$group.php and add the key '$keyPath'",
        );
    }
}
