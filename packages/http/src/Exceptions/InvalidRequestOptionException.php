<?php

declare(strict_types=1);

namespace Marko\Http\Exceptions;

use Marko\Core\Exceptions\MarkoException;
use Marko\Core\Exceptions\UnsupportedFeature;

class InvalidRequestOptionException extends MarkoException
{
    /**
     * @param array<string> $supported
     */
    public static function unknownOption(
        string $key,
        array $supported,
    ): self {
        return new self(
            message: "Unknown HTTP request option '$key'.",
            context: "The option '$key' was passed to the HTTP client but is not a supported request option.",
            suggestion: 'Use one of the supported options: ' . implode(', ', $supported)
                . '. Check for typos, or use a driver-specific escape hatch for non-portable options.',
        );
    }

    /**
     * @param array<string> $keys
     */
    public static function conflictingBodies(
        array $keys,
    ): self {
        $list = implode("', '", $keys);

        return new self(
            message: "Only one body option may be given per request; got '$list'.",
            context: 'The body options (body, json, form_params, multipart) are mutually exclusive.',
            suggestion: 'Pass exactly one of body, json, form_params or multipart.',
        );
    }

    public static function invalidAuth(): self
    {
        return new self(
            message: "Invalid value for HTTP request option 'auth'.",
            context: "The 'auth' option must be a basic credential pair or a bearer token.",
            suggestion: "Use ['username', 'password'] for basic auth or ['bearer' => 'token'] for a bearer token.",
        );
    }

    public static function invalidType(
        string $key,
        string $expected,
        mixed $value,
    ): self {
        $actual = get_debug_type($value);

        return new self(
            message: "Invalid value for HTTP request option '$key': expected $expected, got $actual.",
            context: "The '$key' option was passed a value of type $actual.",
            suggestion: "Pass $expected for the '$key' option.",
        );
    }

    public static function conflictingOptions(
        string $key,
        string $otherKey,
        string $reason,
    ): self {
        return new self(
            message: "HTTP request options '$key' and '$otherKey' cannot be used together.",
            context: $reason,
            suggestion: "Remove either '$key' or '$otherKey' from the request options.",
        );
    }

    public static function unsupportedByDriver(
        string $key,
        string $driver,
        string $reason,
    ): UnsupportedHttpFeatureException {
        return new UnsupportedHttpFeatureException(new UnsupportedFeature(
            feature: $key,
            driver: $driver,
            suggestion: "Use an HTTP client driver that supports '$key', or remove the option. "
                . 'The option is never silently ignored, because callers rely on it for security.',
            context: $reason,
            message: "HTTP request option '$key' is not supported by $driver.",
        ));
    }

    public static function pinnedRequestFollowsRedirects(): self
    {
        return new self(
            message: "HTTP request option 'resolve_to' requires 'allow_redirects' => false.",
            context: "'resolve_to' pins the connection for the request URL's host only. A redirect to another host"
                . ' would be resolved normally, escaping the pin.',
            suggestion: "Pass 'allow_redirects' => false alongside 'resolve_to', and validate and pin any"
                . ' redirect target yourself before following it.',
        );
    }

    public static function unpinnableUrl(): self
    {
        return new self(
            message: "HTTP request option 'resolve_to' needs an absolute http or https URL with a host.",
            context: "The connection is pinned by mapping the URL's host and port to the given IP address,"
                . ' so the URL must name both a scheme and a host.',
            suggestion: "Pass an absolute URL such as 'https://example.com/path', or remove 'resolve_to'.",
        );
    }
}
