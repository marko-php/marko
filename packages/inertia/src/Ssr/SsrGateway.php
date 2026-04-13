<?php

declare(strict_types=1);

namespace Marko\Inertia\Ssr;

use Marko\Inertia\InertiaConfig;
use Marko\Inertia\Interfaces\SsrGatewayInterface;
use RuntimeException;
use Throwable;

class SsrGateway implements SsrGatewayInterface
{
    public function __construct(
        private readonly InertiaConfig $config,
    ) {}

    public function render(array $page): ?SsrPage
    {
        if (! $this->config->ssrEnabled()) {
            return null;
        }

        if ($this->config->shouldEnsureSsrBundleExists() && ! $this->config->ssrBundleExists()) {
            return $this->handleFailure('Inertia SSR bundle is missing.');
        }

        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => implode("\r\n", [
                    'Content-Type: application/json',
                    'Accept: application/json',
                ]),
                'content' => json_encode($page, JSON_THROW_ON_ERROR),
                'ignore_errors' => true,
                'timeout' => 2,
            ],
        ]);

        try {
            $body = @file_get_contents($this->config->ssrUrl(), false, $context);
        } catch (Throwable $e) {
            return $this->handleFailure($e->getMessage(), $e);
        }

        if (! is_string($body) || trim($body) === '') {
            return $this->handleFailure('Inertia SSR server returned an empty response.');
        }

        try {
            /** @var mixed $payload */
            $payload = json_decode($body, true, flags: JSON_THROW_ON_ERROR);
        } catch (Throwable $e) {
            return $this->handleFailure('Inertia SSR server returned invalid JSON.', $e);
        }

        if (! is_array($payload) || ! isset($payload['body']) || ! is_string($payload['body'])) {
            return $this->handleFailure('Inertia SSR response did not contain a valid body.');
        }

        $head = $payload['head'] ?? [];
        if (is_string($head)) {
            $head = [$head];
        }

        return new SsrPage(
            body: $payload['body'],
            head: is_array($head) ? array_values(array_filter($head, 'is_string')) : [],
        );
    }

    private function handleFailure(
        string $message,
        ?Throwable $previous = null,
    ): ?SsrPage {
        if ($this->config->shouldThrowOnSsrError()) {
            throw new RuntimeException($message, previous: $previous);
        }

        return null;
    }
}
