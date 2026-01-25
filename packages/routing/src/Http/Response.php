<?php

declare(strict_types=1);

namespace Marko\Routing\Http;

readonly class Response
{
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

    public function send(): void
    {
        if (!headers_sent()) {
            http_response_code($this->statusCode);

            foreach ($this->headers as $name => $value) {
                header("$name: $value");
            }
        }

        echo $this->body;
    }
}
