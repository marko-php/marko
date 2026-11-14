<?php

declare(strict_types=1);

namespace Marko\Broadcasting\Mercure\Jwt;

use JsonException;
use Marko\Broadcasting\Mercure\Exceptions\MercureException;

/**
 * Minimal HS256 JSON Web Token encoder for Mercure publisher and subscriber tokens.
 */
readonly class MercureJwt
{
    /**
     * @param array<string, mixed> $claims
     * @throws MercureException|JsonException
     */
    public function encode(
        array $claims,
        string $key,
    ): string {
        if ($key === '') {
            throw MercureException::emptySigningKey();
        }

        $header = $this->base64UrlEncode($this->json(['alg' => 'HS256', 'typ' => 'JWT']));
        $payload = $this->base64UrlEncode($this->json($claims));
        $signature = $this->base64UrlEncode(hash_hmac('sha256', "$header.$payload", $key, true));

        return "$header.$payload.$signature";
    }

    /**
     * @param array<string, mixed> $value
     * @throws JsonException
     */
    private function json(
        array $value,
    ): string {
        return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    private function base64UrlEncode(
        string $value,
    ): string {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
