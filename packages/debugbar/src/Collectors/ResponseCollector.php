<?php

declare(strict_types=1);

namespace Marko\Debugbar\Collectors;

class ResponseCollector
{
    /**
     * @return array<string, mixed>
     */
    public function collect(string $body): array
    {
        $headers = headers_list();
        $statusCode = http_response_code();

        return [
            'label' => 'Response',
            'badge' => $this->bodyType($body),
            'status' => is_int($statusCode) ? $statusCode : null,
            'body_type' => $this->bodyType($body),
            'body_size' => strlen($body),
            'headers' => $headers,
            'preview' => $this->preview($body),
        ];
    }

    private function bodyType(string $body): string
    {
        $trimmed = ltrim($body);

        if ($trimmed === '') {
            return 'empty';
        }

        if (str_starts_with($trimmed, '{') || str_starts_with($trimmed, '[')) {
            return 'json';
        }

        if (stripos($body, '<html') !== false || stripos($body, '<body') !== false || stripos(
            $body,
            '<!doctype html',
        ) !== false) {
            return 'html';
        }

        return 'text';
    }

    private function preview(string $body): string
    {
        $body = trim($body);

        if (strlen($body) <= 4000) {
            return $body;
        }

        return substr($body, 0, 4000)."\n...";
    }
}
