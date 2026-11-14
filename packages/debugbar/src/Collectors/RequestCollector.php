<?php

declare(strict_types=1);

namespace Marko\Debugbar\Collectors;

use Marko\Debugbar\Debugbar;
use Marko\Debugbar\Support\Redactor;

class RequestCollector implements CollectorInterface
{
    public function __construct(
        private readonly Redactor $redactor = new Redactor(),
    ) {}

    public function name(): string
    {
        return 'request';
    }

    public function collect(
        Debugbar $debugbar,
    ): array {
        $method = $this->stringValue($_SERVER['REQUEST_METHOD'] ?? null, 'CLI');
        $uri = $this->redactor->uri($this->stringValue($_SERVER['REQUEST_URI'] ?? null, '/'));

        return [
            'label' => 'Request',
            'badge' => $method,
            'method' => $method,
            'uri' => $uri,
            'query' => $this->redactor->redact($_GET),
            'post' => $this->redactor->redact($_POST),
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
            if (
                ! is_string($key)
                || ! str_starts_with($key, 'HTTP_')
            ) {
                continue;
            }

            $header = str_replace('_', '-', substr($key, 5));
            $header = ucwords(strtolower($header), '-');
            $headers[$header] = $this->redactor->header($header, $this->stringValue($value, ''));
        }

        return $headers;
    }

    private function stringValue(
        mixed $value,
        string $default,
    ): string {
        if (is_string($value)) {
            return $value;
        }

        if (
            is_int($value)
            || is_float($value)
            || is_bool($value)
        ) {
            return (string) $value;
        }

        return $default;
    }
}
