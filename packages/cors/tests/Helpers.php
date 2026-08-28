<?php

declare(strict_types=1);

namespace Marko\Cors\Tests;

use Marko\Cors\Config\CorsConfig;
use Marko\Routing\Http\Response;
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
     * @param list<string> $allowedOrigins
     * @param list<string> $allowedMethods
     * @param list<string> $allowedHeaders
     * @param list<string> $exposeHeaders
     */
    public static function createCorsConfig(
        array $allowedOrigins = ['https://example.com'],
        array $allowedMethods = ['GET', 'POST'],
        array $allowedHeaders = ['Content-Type'],
        array $exposeHeaders = [],
        bool $supportsCredentials = false,
        int $maxAge = 0,
    ): CorsConfig {
        return new CorsConfig(new FakeConfigRepository([
            'cors.allowed_origins' => $allowedOrigins,
            'cors.allowed_methods' => $allowedMethods,
            'cors.allowed_headers' => $allowedHeaders,
            'cors.expose_headers' => $exposeHeaders,
            'cors.supports_credentials' => $supportsCredentials,
            'cors.max_age' => $maxAge,
        ]));
    }
}
