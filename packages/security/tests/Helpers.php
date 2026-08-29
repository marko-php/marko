<?php

declare(strict_types=1);

namespace Marko\Security\Tests;

use Marko\Routing\Http\Response;
use Marko\Security\Config\SecurityConfig;
use Marko\Testing\Fake\FakeConfigRepository;

/**
 * A `Response` subclass carrying extra state, used to prove that middleware
 * decorates the response returned by `$next()` instead of rebuilding a base
 * `Response` and discarding subclass identity (loaded via composer
 * autoload-dev.files).
 */
class TaggedResponse extends Response
{
    /**
     * @param array<string, string> $headers
     */
    public function __construct(
        public readonly string $tag,
        string $body = '',
        int $statusCode = 200,
        array $headers = [],
    ) {
        parent::__construct($body, $statusCode, $headers);
    }
}

/**
 * A `Response` subclass carrying a simulated streamed payload, used to prove
 * that streaming state survives middleware that used to rebuild a base
 * `Response`.
 */
class StreamingLikeResponse extends Response
{
    /**
     * @param list<string> $chunks
     * @param array<string, string> $headers
     */
    public function __construct(
        private readonly array $chunks,
        int $statusCode = 200,
        array $headers = [],
    ) {
        parent::__construct('', $statusCode, $headers);
    }

    /**
     * @return list<string>
     */
    public function chunks(): array
    {
        return $this->chunks;
    }
}

final class Helpers
{
    /**
     * @param array<string, string> $headers
     */
    public static function createTaggedResponse(
        string $tag = 'tagged',
        string $body = '',
        int $statusCode = 200,
        array $headers = [],
    ): TaggedResponse {
        return new TaggedResponse($tag, $body, $statusCode, $headers);
    }

    /**
     * @param list<string> $chunks
     * @param array<string, string> $headers
     */
    public static function createStreamingLikeResponse(
        array $chunks = ['event: message', 'data: hello'],
        int $statusCode = 200,
        array $headers = [],
    ): StreamingLikeResponse {
        return new StreamingLikeResponse($chunks, $statusCode, $headers);
    }

    /**
     * @param array<string, mixed> $configData
     */
    public static function createSecurityConfig(array $configData = []): SecurityConfig
    {
        return new SecurityConfig(new FakeConfigRepository($configData));
    }

    /**
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    public static function defaultHeadersConfig(array $overrides = []): array
    {
        return array_merge([
            'security.headers.x_content_type_options' => 'nosniff',
            'security.headers.x_frame_options' => 'SAMEORIGIN',
            'security.headers.x_xss_protection' => '1; mode=block',
            'security.headers.strict_transport_security' => 'max-age=31536000; includeSubDomains',
            'security.headers.referrer_policy' => 'strict-origin-when-cross-origin',
            'security.headers.content_security_policy' => "default-src 'self'",
        ], $overrides);
    }

    /**
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    public static function defaultCorsConfig(array $overrides = []): array
    {
        return array_merge([
            'security.cors.allowed_origins' => ['https://example.com'],
            'security.cors.allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],
            'security.cors.allowed_headers' => ['Content-Type', 'X-Requested-With', 'X-CSRF-TOKEN'],
            'security.cors.max_age' => 86400,
        ], $overrides);
    }
}
