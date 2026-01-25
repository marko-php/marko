<?php

declare(strict_types=1);

namespace Marko\ErrorsAdvanced;

class RequestDataCollector
{
    private const array SENSITIVE_FIELD_PATTERNS = [
        'password',
        'api_key',
        'apikey',
        'token',
        'secret',
        'session',
    ];

    private const array SENSITIVE_HEADERS = [
        'authorization',
    ];

    private const string MASK = '********';

    /** @var array<string, mixed> */
    private array $server;

    /** @var array<string, mixed> */
    private array $get;

    /** @var array<string, mixed> */
    private array $post;

    /** @var array<string, mixed> */
    private array $cookie;

    /**
     * @param array<string, mixed>|null $server
     * @param array<string, mixed>|null $get
     * @param array<string, mixed>|null $post
     * @param array<string, mixed>|null $cookie
     */
    public function __construct(
        ?array $server = null,
        ?array $get = null,
        ?array $post = null,
        ?array $cookie = null,
    ) {
        $this->server = $server ?? $_SERVER;
        $this->get = $get ?? $_GET;
        $this->post = $post ?? $_POST;
        $this->cookie = $cookie ?? $_COOKIE;
    }

    /**
     * @return array<string, mixed>
     */
    public function collect(): array
    {
        return [
            'method' => $this->server['REQUEST_METHOD'] ?? 'CLI',
            'uri' => $this->server['REQUEST_URI'] ?? '',
            'headers' => $this->maskSensitiveHeaders($this->collectHeaders()),
            'query' => $this->maskSensitiveData($this->get),
            'post' => $this->maskSensitiveData($this->post),
            'cookies' => $this->maskSensitiveData($this->cookie),
            'server' => $this->collectServerInfo(),
        ];
    }

    /**
     * @return array<string, string>
     */
    private function collectHeaders(): array
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

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function maskSensitiveData(
        array $data,
    ): array {
        foreach ($data as $key => $value) {
            if ($this->isSensitiveField($key)) {
                $data[$key] = self::MASK;
            } elseif (is_array($value)) {
                $data[$key] = $this->maskSensitiveData($value);
            }
        }

        return $data;
    }

    private function isSensitiveField(
        string $fieldName,
    ): bool {
        $normalizedName = strtolower(str_replace('_', '', $fieldName));

        foreach (self::SENSITIVE_FIELD_PATTERNS as $pattern) {
            $normalizedPattern = str_replace('_', '', $pattern);
            if ($normalizedName === $normalizedPattern || str_contains($normalizedName, $normalizedPattern)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, string> $headers
     * @return array<string, string>
     */
    private function maskSensitiveHeaders(
        array $headers,
    ): array {
        foreach ($headers as $key => $value) {
            if (in_array(strtolower($key), self::SENSITIVE_HEADERS, true)) {
                $headers[$key] = self::MASK;
            }
        }

        return $headers;
    }

    /**
     * @return array<string, string>
     */
    private function collectServerInfo(): array
    {
        return [
            'php_version' => PHP_VERSION,
            'software' => $this->server['SERVER_SOFTWARE'] ?? '',
            'name' => $this->server['SERVER_NAME'] ?? '',
        ];
    }
}
