<?php

declare(strict_types=1);

namespace Marko\Routing\Exceptions;

use Marko\Core\Exceptions\MarkoException;

class CookieException extends MarkoException
{
    public static function invalidName(
        string $name,
    ): self {
        return new self(
            message: "Invalid cookie name '$name'",
            context: 'Cookie names must not contain control characters, whitespace, or separator characters such as ( ) < > @ , ; : \\ " / [ ] ? = { }',
            suggestion: 'Use a token-safe cookie name, e.g. letters, digits, and characters like - _ . ~',
        );
    }

    public static function sameSiteNoneRequiresSecure(
        string $name,
    ): self {
        return new self(
            message: "Cookie '$name' uses SameSite=None but is not marked Secure",
            context: 'Browsers silently drop a SameSite=None cookie that is not sent with the Secure attribute',
            suggestion: "Set secure: true when using sameSite: 'None'",
        );
    }
}
