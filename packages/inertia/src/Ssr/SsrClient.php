<?php

declare(strict_types=1);

namespace Marko\Inertia\Ssr;

use JsonException;
use Marko\Config\ConfigRepositoryInterface;
use Marko\Config\Exceptions\ConfigException;
use Marko\Inertia\Exceptions\InertiaConfigurationException;

readonly class SsrClient
{
    public function __construct(
        private ConfigRepositoryInterface $config,
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

        $response = $this->transport->post($this->ssrUrl(), $json);

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

    private function ssrUrl(): string
    {
        try {
            $url = trim($this->config->getString('inertia.ssr.url'));
        } catch (ConfigException $exception) {
            throw InertiaConfigurationException::missingOrInvalid('inertia.ssr.url', $exception);
        }

        if ($url === '') {
            throw InertiaConfigurationException::empty(
                'inertia.ssr.url',
                'Set inertia.ssr.url in config/inertia.php when inertia.ssr.enabled is true.',
            );
        }

        return $url;
    }
}
