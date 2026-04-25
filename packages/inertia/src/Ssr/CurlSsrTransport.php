<?php

declare(strict_types=1);

namespace Marko\Inertia\Ssr;

readonly class CurlSsrTransport implements SsrTransportInterface
{
    public function __construct(
        private int $timeoutSeconds = 5,
        private int $connectTimeoutSeconds = 1,
    ) {}

    public function post(string $url, string $body): ?string
    {
        if (! function_exists('curl_init')) {
            return null;
        }

        $handle = curl_init($url);

        if ($handle === false) {
            return null;
        }

        try {
            curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($handle, CURLOPT_POST, true);
            curl_setopt($handle, CURLOPT_POSTFIELDS, $body);
            curl_setopt($handle, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'Accept: application/json',
            ]);
            curl_setopt($handle, CURLOPT_TIMEOUT, $this->timeoutSeconds);
            curl_setopt($handle, CURLOPT_CONNECTTIMEOUT, $this->connectTimeoutSeconds);

            $response = curl_exec($handle);
            $httpCode = (int) curl_getinfo($handle, CURLINFO_HTTP_CODE);

            if ($response === false || $httpCode !== 200) {
                return null;
            }

            return (string) $response;
        } finally {
            curl_close($handle);
        }
    }
}
