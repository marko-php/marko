<?php

declare(strict_types=1);

namespace Marko\Http;

use JsonException;

readonly class HttpResponse
{
    /**
     * @param array<string, string> $headers One string per header name; repeated values joined with ", ".
     *     When empty, headers() joins $headerValues instead.
     * @param array<string, list<string>> $headerValues Every value of each header, in the order received.
     *     When empty, derived from $headers as one-element lists.
     */
    public function __construct(
        private int $statusCode,
        private string $body,
        private array $headers = [],
        private array $headerValues = [],
    ) {}

    public function statusCode(): int
    {
        return $this->statusCode;
    }

    public function body(): string
    {
        return $this->body;
    }

    /**
     * One string per header name, repeated values joined with ", ". When the
     * response was built with only headerValues, they are joined here.
     *
     * @return array<string, string>
     */
    public function headers(): array
    {
        if (
            $this->headers === []
            && $this->headerValues !== []
        ) {
            return array_map(static fn (array $values): string => implode(', ', $values), $this->headerValues);
        }

        return $this->headers;
    }

    /**
     * Every value of the named header, matched case-insensitively, in the order received.
     * Lossless for repeated headers such as Set-Cookie. Returns [] when the header is absent.
     *
     * @return list<string>
     */
    public function headerValues(
        string $name,
    ): array {
        $all = $this->headerValues !== []
            ? $this->headerValues
            : array_map(static fn (string $value): array => [$value], $this->headers);

        $values = [];

        foreach ($all as $headerName => $headerValues) {
            if (strcasecmp((string) $headerName, $name) === 0) {
                array_push($values, ...$headerValues);
            }
        }

        return $values;
    }

    /**
     * The named header's values joined with ", ", matched case-insensitively.
     * Returns null when the header is absent. Use headerValues() for Set-Cookie.
     */
    public function header(
        string $name,
    ): ?string {
        $values = $this->headerValues($name);

        return $values === [] ? null : implode(', ', $values);
    }

    /**
     * The body, trimmed and capped at $maxBytes for error messages and logs. A longer body is cut
     * on a UTF-8 character boundary and ends with "... [truncated N bytes]".
     */
    public function bodyExcerpt(
        int $maxBytes = 500,
    ): string {
        $body = trim($this->body);
        $length = strlen($body);

        if ($length <= $maxBytes) {
            return $body;
        }

        $cut = max(0, $maxBytes);

        // Back off continuation bytes (10xxxxxx) so a multibyte character is never split.
        while (
            $cut > 0
            && (ord($body[$cut]) & 0xC0) === 0x80
        ) {
            $cut--;
        }

        return substr($body, 0, $cut) . '... [truncated ' . ($length - $cut) . ' bytes]';
    }

    /**
     * @throws JsonException
     */
    public function json(): mixed
    {
        return json_decode($this->body, true, 512, JSON_THROW_ON_ERROR);
    }

    public function isSuccessful(): bool
    {
        return $this->statusCode >= 200
            && $this->statusCode < 300;
    }

    public function isRedirect(): bool
    {
        return $this->statusCode >= 300
            && $this->statusCode < 400;
    }

    public function isClientError(): bool
    {
        return $this->statusCode >= 400
            && $this->statusCode < 500;
    }

    public function isServerError(): bool
    {
        return $this->statusCode >= 500;
    }
}
