<?php

declare(strict_types=1);

namespace Marko\Forms\Exceptions;

use Marko\Core\Exceptions\MarkoException;

class FormsException extends MarkoException
{
    public static function invalidNeverFlashField(
        mixed $field,
    ): self {
        $type = get_debug_type($field);

        return new self(
            message: "forms.never_flash must list field names, got $type",
            context: 'While reading the fields that are never flashed back into a form',
            suggestion: "List each field as a non-empty string in config/forms.php, for example 'password' or 'user.password'",
        );
    }

    public static function invalidFallbackRedirect(
        string $path,
    ): self {
        return new self(
            message: "forms.fallback_redirect must be a path on this site, got '$path'",
            context: 'RedirectBackOnValidationError redirects there when the request has no same-origin Referer',
            suggestion: "Set forms.fallback_redirect in config/forms.php to a path starting with a single '/', for example '/'",
        );
    }

    public static function readOnlyOldInput(): self
    {
        return new self(
            message: 'Old input is read-only',
            context: 'The $old template variable reads the input flashed by the previous request',
            suggestion: 'Read values with $old[\'field\'] (Latte) or old.field (Twig); flash input with FlashedFormStateWriter::flash()',
        );
    }
}
