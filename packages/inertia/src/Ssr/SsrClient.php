<?php

declare(strict_types=1);

namespace Marko\Inertia\Ssr;

use JsonException;

readonly class SsrClient
{
    public function __construct(
        private string $url,
        private SsrTransportInterface $transport,
    ) {}

    /**
     * Render a page via the Inertia SSR server.
     *
     * @param array<string, mixed> $page
     * @return array{head: string, body: string}|null
     */
    public function render(array $page): ?array
    {
        try {
            $json = json_encode($page, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }

        $response = $this->transport->post($this->url, $json);

        if ($response === null) {
            return null;
        }

        try {
            $data = json_decode($response, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }

        if (! is_array($data) || isset($data['error'])) {
            return null;
        }

        $head = $data['head'] ?? '';
        $body = $data['body'] ?? null;

        if (! is_string($body) || $body === '') {
            return null;
        }

        return [
            'head' => is_string($head) ? $head : '',
            'body' => $body,
        ];
    }
}
