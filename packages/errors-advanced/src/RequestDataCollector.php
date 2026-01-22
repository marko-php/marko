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

    /**
     * @return array<string, mixed>
     */
    public function collect(): array
    {
        return [
            'method' => $_SERVER['REQUEST_METHOD'] ?? 'CLI',
            'uri' => $_SERVER['REQUEST_URI'] ?? '',
            'headers' => $this->maskSensitiveHeaders($this->collectHeaders()),
            'query' => $this->maskSensitiveData($_GET),
            'post' => $this->maskSensitiveData($_POST),
            'cookies' => $this->maskSensitiveData($_COOKIE),
            'server' => $this->collectServerInfo(),
        ];
    }

    /**
     * @return array<string, string>
     */
    private function collectHeaders(): array
    {
        $headers = [];

        foreach ($_SERVER as $key => $value) {
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
            'software' => $_SERVER['SERVER_SOFTWARE'] ?? '',
            'name' => $_SERVER['SERVER_NAME'] ?? '',
        ];
    }
}
