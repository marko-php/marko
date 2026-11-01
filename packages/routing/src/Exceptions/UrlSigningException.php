<?php

declare(strict_types=1);

namespace Marko\Routing\Exceptions;

use Marko\Core\Exceptions\MarkoException;

/**
 * URL signing is misconfigured, or a URL cannot be signed as given.
 */
class UrlSigningException extends MarkoException
{
    private const string KEY_SUGGESTION = 'Generate a key with: php -r "echo bin2hex(random_bytes(32));" '
        . 'and set it as URL_SIGNING_KEY in your .env file. Do not reuse ENCRYPTION_KEY.';

    public static function missingKey(): self
    {
        return new self(
            message: 'URL signing key is not configured.',
            context: 'routing.signing.key (URL_SIGNING_KEY) is empty, so URLs cannot be signed or validated.',
            suggestion: self::KEY_SUGGESTION,
        );
    }

    public static function keyTooShort(
        string $configKey,
        int $length,
        int $minLength,
    ): self {
        return new self(
            message: "URL signing key in $configKey must be at least $minLength bytes, got $length.",
            context: 'A short key makes signed URLs forgeable.',
            suggestion: self::KEY_SUGGESTION,
        );
    }

    public static function invalidPreviousKey(): self
    {
        return new self(
            message: 'Every entry in routing.signing.previous_keys must be a non-empty string.',
            context: 'Previous keys still validate links signed before a key rotation.',
            suggestion: 'Remove the empty entry, or list the retired URL_SIGNING_KEY values.',
        );
    }

    public static function reservedParameter(
        string $url,
        string $parameter,
    ): self {
        return new self(
            message: "Cannot sign '$url': it already has a '$parameter' query parameter.",
            context: "The signer adds '$parameter' itself.",
            suggestion: "Rename the '$parameter' parameter, or sign the URL without it.",
        );
    }

    public static function fragment(
        string $url,
    ): self {
        return new self(
            message: "Cannot sign '$url': it has a fragment.",
            context: 'Browsers never send the fragment, so it cannot be covered by the signature.',
            suggestion: 'Sign the URL without the fragment.',
        );
    }

    public static function unparseableUrl(
        string $url,
    ): self {
        return new self(
            message: "Cannot sign '$url': it is not a valid URL.",
            suggestion: 'Pass an absolute URL (https://example.com/path) or a path starting with /.',
        );
    }

    public static function missingBaseUrl(
        string $url,
    ): self {
        return new self(
            message: "Cannot sign relative URL '$url': no base URL is configured.",
            context: 'A relative URL is signed for the host of routing.url, so a link cannot be moved to another host.',
            suggestion: 'Set APP_URL (routing.url) to your application URL, e.g. https://example.com.',
        );
    }
}
