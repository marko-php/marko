<?php

declare(strict_types=1);

namespace Marko\Routing\Http;

use JsonException;
use NoDiscard;

/**
 * Not `readonly class`, and its properties are not individually `readonly`,
 * because the decoration API (`with*()` methods) relies on `clone $this`
 * followed by assignment on the clone. On PHP 8.5.1, modifying a readonly
 * property on a clone fails both by direct assignment and via
 * `ReflectionProperty::setValue()`, and `clone $this with { ... }` is not
 * valid syntax. `clone` is required (rather than `new static(...)`) because
 * subclasses such as `StreamingResponse` have constructors with a different
 * signature than the parent. Immutability is enforced by API design instead:
 * properties stay `private` and no setters are exposed.
 */
class Response
{
    /**
     * @var list<Cookie>
     */
    private array $cookies = [];

    /**
     * @param array<string, string> $headers
     */
    public function __construct(
        private string $body = '',
        private int $statusCode = 200,
        private array $headers = [],
    ) {}

    public function body(): string
    {
        return $this->body;
    }

    public function statusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * @return array<string, string>
     */
    public function headers(): array
    {
        return $this->headers;
    }

    /**
     * @return list<Cookie>
     */
    public function cookies(): array
    {
        return $this->cookies;
    }

    #[NoDiscard]
    public function withHeader(
        string $name,
        string $value,
    ): static {
        $clone = clone $this;
        $clone->headers[$name] = $value;

        return $clone;
    }

    /**
     * @param array<string, string> $headers
     */
    #[NoDiscard]
    public function withHeaders(array $headers): static
    {
        $clone = clone $this;
        $clone->headers = [...$clone->headers, ...$headers];

        return $clone;
    }

    #[NoDiscard]
    public function withStatus(int $statusCode): static
    {
        $clone = clone $this;
        $clone->statusCode = $statusCode;

        return $clone;
    }

    #[NoDiscard]
    public function withCookie(Cookie $cookie): static
    {
        $clone = clone $this;

        $index = array_find_key(
            $clone->cookies,
            fn (Cookie $existing): bool => $existing->name() === $cookie->name()
                && $existing->path() === $cookie->path()
                && $existing->domain() === $cookie->domain(),
        );

        if ($index === null) {
            $clone->cookies[] = $cookie;
        } else {
            $clone->cookies[$index] = $cookie;
        }

        return $clone;
    }

    /**
     * @throws JsonException
     */
    public static function json(
        mixed $data,
        int $statusCode = 200,
    ): self {
        return new self(
            body: json_encode($data, JSON_THROW_ON_ERROR),
            statusCode: $statusCode,
            headers: ['Content-Type' => 'application/json'],
        );
    }

    public static function html(
        string $html,
        int $statusCode = 200,
    ): self {
        return new self(
            body: $html,
            statusCode: $statusCode,
            headers: ['Content-Type' => 'text/html; charset=utf-8'],
        );
    }

    public static function redirect(
        string $url,
        int $statusCode = 302,
    ): self {
        return new self(
            body: '',
            statusCode: $statusCode,
            headers: ['Location' => $url],
        );
    }

    /**
     * @return list<string>
     */
    public function headerLines(): array
    {
        $lines = [];

        foreach ($this->headers as $name => $value) {
            $lines[] = "$name: $value";
        }

        foreach ($this->cookies as $cookie) {
            $lines[] = 'Set-Cookie: ' . $cookie->toSetCookieString();
        }

        return $lines;
    }

    public function send(): void
    {
        if (!headers_sent()) {
            http_response_code($this->statusCode);

            foreach ($this->headerLines() as $line) {
                header($line);
            }
        }

        echo $this->body;
    }
}
