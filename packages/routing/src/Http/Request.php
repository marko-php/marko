<?php

declare(strict_types=1);

namespace Marko\Routing\Http;

class Request
{
    /**
     * @param array<string, mixed> $server
     * @param array<string, mixed> $query
     * @param array<string, mixed> $post
     */
    public function __construct(
        private readonly array $server = [],
        private readonly array $query = [],
        private readonly array $post = [],
    ) {}

    public static function fromGlobals(): self
    {
        return new self(
            server: $_SERVER,
            query: $_GET,
            post: $_POST,
        );
    }

    public function method(): string
    {
        return $this->server['REQUEST_METHOD'] ?? 'GET';
    }

    public function path(): string
    {
        $uri = $this->server['REQUEST_URI'] ?? '/';
        $position = strpos($uri, '?');

        return $position === false ? $uri : substr($uri, 0, $position);
    }

    /**
     * @return ($key is null ? array<string, mixed> : mixed)
     */
    public function query(
        ?string $key = null,
        mixed $default = null,
    ): mixed
    {
        if ($key === null) {
            return $this->query;
        }

        return $this->query[$key] ?? $default;
    }

    /**
     * @return ($key is null ? array<string, mixed> : mixed)
     */
    public function post(
        ?string $key = null,
        mixed $default = null,
    ): mixed
    {
        if ($key === null) {
            return $this->post;
        }

        return $this->post[$key] ?? $default;
    }

    public function header(
        string $name,
        ?string $default = null,
    ): ?string
    {
        $serverKey = 'HTTP_' . strtoupper(str_replace('-', '_', $name));

        return $this->server[$serverKey] ?? $default;
    }

    /**
     * @return array<string, string>
     */
    public function headers(): array
    {
        $headers = [];

        foreach ($this->server as $key => $value) {
            if (str_starts_with($key, 'HTTP_')) {
                $headerName = str_replace('_', '-', substr($key, 5));
                $headerName = ucwords(strtolower($headerName), '-');
                $headers[$headerName] = $value;
            }
        }

        return $headers;
    }
}
