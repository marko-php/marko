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

    private bool $bodyOmitted = false;

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

    /**
     * Drop the body but keep status, headers, cookies and the concrete class.
     *
     * The router applies this to every HEAD response. send() then emits the
     * headers only, and never calls sendBody() on a StreamedResponseInterface.
     */
    #[NoDiscard]
    public function withoutBody(): static
    {
        $clone = clone $this;
        $clone->body = '';
        $clone->bodyOmitted = true;

        return $clone;
    }

    public function isBodyOmitted(): bool
    {
        return $this->bodyOmitted;
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

    /**
     * Emit the status line, headers and body.
     *
     * A StreamedResponseInterface writes its own body through sendBody(),
     * which is skipped when the body was omitted (HEAD).
     */
    public function send(): void
    {
        $this->sendHeaders();

        if ($this instanceof StreamedResponseInterface) {
            if (!$this->isBodyOmitted()) {
                $this->sendBody();
            }

            return;
        }

        echo $this->body;
    }

    /**
     * Emit the status code, then each header, then one Set-Cookie per cookie.
     *
     * Regular headers replace any earlier header of the same name. Set-Cookie
     * lines never replace one another, so every cookie reaches the client.
     */
    protected function sendHeaders(): void
    {
        if ($this->headersAlreadySent()) {
            return;
        }

        http_response_code($this->statusCode);

        foreach ($this->headers as $name => $value) {
            $this->emitHeader("$name: $value", replace: true);
        }

        foreach ($this->cookies as $cookie) {
            $this->emitHeader('Set-Cookie: ' . $cookie->toSetCookieString(), replace: false);
        }
    }

    protected function headersAlreadySent(): bool
    {
        return headers_sent();
    }

    protected function emitHeader(
        string $line,
        bool $replace,
    ): void {
        header($line, $replace);
    }
}
