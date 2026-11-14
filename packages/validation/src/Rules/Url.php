<?php

declare(strict_types=1);

namespace Marko\Validation\Rules;

use InvalidArgumentException;
use Marko\Validation\Contracts\RuleInterface;

/**
 * A well-formed URL with a host, whose scheme is in the allow-list (http and https by default).
 *
 * Schemes compare case-insensitively. This is not an SSRF guard: `http://127.0.0.1/` and
 * `http://169.254.169.254/` pass, so check the resolved address before the server fetches a URL.
 */
class Url implements RuleInterface
{
    private const array DEFAULT_SCHEMES = ['http', 'https'];

    /**
     * @var array<string>
     */
    private readonly array $schemes;

    /**
     * @throws InvalidArgumentException when schemes are given but all are blank
     */
    public function __construct(
        string ...$schemes,
    ) {
        if ($schemes === []) {
            $this->schemes = self::DEFAULT_SCHEMES;

            return;
        }

        $normalized = array_values(array_unique(array_filter(
            array_map(static fn (string $scheme): string => strtolower(trim($scheme)), $schemes),
            static fn (string $scheme): bool => $scheme !== '',
        )));

        if ($normalized === []) {
            throw new InvalidArgumentException(
                'The url rule needs at least one scheme when a list is given, e.g. url:http,https,ftp',
            );
        }

        $this->schemes = $normalized;
    }

    public function passes(
        string $field,
        mixed $value,
        array $data,
    ): bool {
        if (
            $value === null
            || $value === ''
        ) {
            return true;
        }

        if (
            !is_string($value)
            || filter_var($value, FILTER_VALIDATE_URL) === false
        ) {
            return false;
        }

        $scheme = parse_url($value, PHP_URL_SCHEME);
        $host = parse_url($value, PHP_URL_HOST);

        return is_string($scheme)
            && in_array(strtolower($scheme), $this->schemes, true)
            && is_string($host)
            && $host !== '';
    }

    public function message(
        string $field,
        mixed $value,
    ): string {
        $schemes = implode(', ', $this->schemes);

        return "The $field field must be a valid URL using one of these schemes: $schemes.";
    }
}
