<?php

declare(strict_types=1);

namespace Marko\Debugbar\Collectors;

use Marko\Debugbar\Debugbar;

class RequestCollector implements CollectorInterface
{
    public function name(): string
    {
        return 'request';
    }

    public function collect(Debugbar $debugbar): array
    {
        $method = $this->stringValue($_SERVER['REQUEST_METHOD'] ?? null, 'CLI');
        $uri = $this->stringValue($_SERVER['REQUEST_URI'] ?? null, '/');

        return [
            'label' => 'Request',
            'badge' => $method,
            'method' => $method,
            'uri' => $uri,
            'query' => $_GET,
            'post' => $this->redact($_POST),
            'headers' => $this->headers(),
        ];
    }

    /**
     * @return array<string, string>
     */
    private function headers(): array
    {
        $headers = [];

        foreach ($_SERVER as $key => $value) {
            if (! is_string($key) || ! str_starts_with($key, 'HTTP_')) {
                continue;
            }

            $header = str_replace('_', '-', substr($key, 5));
            $header = ucwords(strtolower($header), '-');
            $headers[$header] = $this->isSensitiveKey($header) ? '[masked]' : $this->stringValue($value, '');
        }

        return $headers;
    }

    /**
     * @param array<mixed> $values
     * @return array<mixed>
     */
    private function redact(array $values): array
    {
        foreach ($values as $key => $value) {
            $keyString = strtolower((string) $key);

            if ($this->isSensitiveKey($keyString)) {
                $values[$key] = '[masked]';
                continue;
            }

            if (is_array($value)) {
                $values[$key] = $this->redact($value);
            }
        }

        return $values;
    }

    private function isSensitiveKey(string $key): bool
    {
        $key = strtolower($key);

        return str_contains($key, 'authorization')
            || str_contains($key, 'password')
            || str_contains($key, 'token')
            || str_contains($key, 'secret')
            || str_contains($key, 'api-key')
            || str_contains($key, 'api_key');
    }

    private function stringValue(
        mixed $value,
        string $default,
    ): string {
        if (is_string($value)) {
            return $value;
        }

        if (is_int($value) || is_float($value) || is_bool($value)) {
            return (string) $value;
        }

        return $default;
    }
}
