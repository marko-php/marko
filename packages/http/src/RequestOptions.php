<?php

declare(strict_types=1);

namespace Marko\Http;

use Marko\Http\Exceptions\InvalidRequestOptionException;

/**
 * The portable request options accepted by every HttpClientInterface driver.
 *
 * Drivers and fakes call validate() before sending so that an unknown or
 * malformed option fails loudly at the call site instead of being dropped.
 */
class RequestOptions
{
    /** Request headers: array<string, string|array<string>> */
    public const string HEADERS = 'headers';

    /** Raw request body: string */
    public const string BODY = 'body';

    /** JSON-encoded request body: mixed */
    public const string JSON = 'json';

    /** URL-encoded form body: array<string, mixed> */
    public const string FORM_PARAMS = 'form_params';

    /** Multipart form body: array<array{name: string, contents: mixed, filename?: string, headers?: array<string, string>}> */
    public const string MULTIPART = 'multipart';

    /** Query string parameters: array<string, mixed>|string */
    public const string QUERY = 'query';

    /** Total request timeout in seconds: int|float */
    public const string TIMEOUT = 'timeout';

    /** Connection timeout in seconds: int|float */
    public const string CONNECT_TIMEOUT = 'connect_timeout';

    /** Credentials: ['username', 'password'] (basic) or ['bearer' => 'token'] */
    public const string AUTH = 'auth';

    /** TLS verification: bool, or a path to a CA bundle */
    public const string VERIFY = 'verify';

    /** Redirect handling: bool, or the maximum number of redirects as int */
    public const string ALLOW_REDIRECTS = 'allow_redirects';

    /** Proxy URL: string */
    public const string PROXY = 'proxy';

    /** Throw HttpException on 4xx/5xx responses: bool (default true) */
    public const string HTTP_ERRORS = 'http_errors';

    /**
     * Connect to this pre-resolved IP address instead of resolving the URL's host,
     * while keeping the host in the Host header and TLS SNI/certificate checks: string
     * (an IPv4 or IPv6 literal, IPv6 without brackets). Pins only the request URL's
     * host and port, so it cannot be combined with a proxy and requires
     * 'allow_redirects' => false (a redirect could lead to a host the pin does not
     * cover). A driver that cannot pin
     * the connection must throw InvalidRequestOptionException rather than ignore it.
     */
    public const string RESOLVE_TO = 'resolve_to';

    /** @var array<string> */
    public const array SUPPORTED = [
        self::HEADERS,
        self::BODY,
        self::JSON,
        self::FORM_PARAMS,
        self::MULTIPART,
        self::QUERY,
        self::TIMEOUT,
        self::CONNECT_TIMEOUT,
        self::AUTH,
        self::VERIFY,
        self::ALLOW_REDIRECTS,
        self::PROXY,
        self::HTTP_ERRORS,
        self::RESOLVE_TO,
    ];

    /** @var array<string> */
    public const array BODY_OPTIONS = [
        self::BODY,
        self::JSON,
        self::FORM_PARAMS,
        self::MULTIPART,
    ];

    /**
     * Validate request options against the portable set.
     *
     * @param array<string, mixed> $options
     * @param array<string> $driverKeys Additional, driver-specific keys the caller accepts
     *
     * @throws InvalidRequestOptionException
     */
    public static function validate(
        array $options,
        array $driverKeys = [],
    ): void {
        $supported = [...self::SUPPORTED, ...$driverKeys];

        foreach (array_keys($options) as $key) {
            if (!in_array($key, $supported, true)) {
                throw InvalidRequestOptionException::unknownOption((string) $key, $supported);
            }
        }

        $bodies = array_values(array_filter(
            self::BODY_OPTIONS,
            fn (string $key): bool => array_key_exists($key, $options),
        ));

        if (count($bodies) > 1) {
            throw InvalidRequestOptionException::conflictingBodies($bodies);
        }

        if (
            array_key_exists(self::AUTH, $options)
            && !self::isValidAuth($options[self::AUTH])
        ) {
            throw InvalidRequestOptionException::invalidAuth();
        }

        if (
            array_key_exists(self::HTTP_ERRORS, $options)
            && !is_bool($options[self::HTTP_ERRORS])
        ) {
            throw InvalidRequestOptionException::invalidType(self::HTTP_ERRORS, 'bool', $options[self::HTTP_ERRORS]);
        }

        if (array_key_exists(self::ALLOW_REDIRECTS, $options)) {
            $value = $options[self::ALLOW_REDIRECTS];

            if (
                !is_bool($value)
                && !(
                    is_int($value)
                    && $value >= 0
                )
            ) {
                throw InvalidRequestOptionException::invalidType(
                    self::ALLOW_REDIRECTS,
                    'bool or a non-negative int',
                    $value,
                );
            }
        }

        if (array_key_exists(self::VERIFY, $options)) {
            $value = $options[self::VERIFY];

            if (
                !is_bool($value)
                && !is_string($value)
            ) {
                throw InvalidRequestOptionException::invalidType(self::VERIFY, 'bool or a CA bundle path', $value);
            }
        }

        if (array_key_exists(self::RESOLVE_TO, $options)) {
            $value = $options[self::RESOLVE_TO];

            if (
                !is_string($value)
                || filter_var($value, FILTER_VALIDATE_IP) === false
            ) {
                throw InvalidRequestOptionException::invalidType(
                    self::RESOLVE_TO,
                    'an IPv4 or IPv6 address string',
                    $value,
                );
            }

            if (array_key_exists(self::PROXY, $options)) {
                throw InvalidRequestOptionException::conflictingOptions(
                    self::RESOLVE_TO,
                    self::PROXY,
                    'A proxy resolves the destination host itself, so the connection cannot be pinned to an IP.',
                );
            }

            if (($options[self::ALLOW_REDIRECTS] ?? true) !== false) {
                throw InvalidRequestOptionException::pinnedRequestFollowsRedirects();
            }
        }
    }

    /**
     * Whether a 4xx/5xx response should be raised as an HttpException.
     *
     * @param array<string, mixed> $options
     */
    public static function throwsOnHttpError(
        array $options,
    ): bool {
        return ($options[self::HTTP_ERRORS] ?? true) !== false;
    }

    /**
     * The bearer token from a validated 'auth' option, or null for basic auth or no auth.
     *
     * @param array<string, mixed> $options
     */
    public static function bearerToken(
        array $options,
    ): ?string {
        $auth = $options[self::AUTH] ?? null;

        if (
            is_array($auth)
            && isset($auth['bearer'])
            && is_string($auth['bearer'])
        ) {
            return $auth['bearer'];
        }

        return null;
    }

    private static function isValidAuth(
        mixed $auth,
    ): bool {
        if (!is_array($auth)) {
            return false;
        }

        if (array_keys($auth) === ['bearer']) {
            return is_string($auth['bearer'])
                && $auth['bearer'] !== '';
        }

        return array_is_list($auth)
            && count($auth) === 2
            && is_string($auth[0])
            && is_string($auth[1]);
    }
}
