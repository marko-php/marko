<?php

declare(strict_types=1);

namespace Marko\Inertia\Tests;

use Marko\Routing\Http\Response;

/**
 * A `Response` subclass carrying extra state, used to prove that middleware
 * decorates the response returned by `$next()` instead of rebuilding a base
 * `Response` and discarding subclass identity (loaded via composer
 * autoload-dev.files).
 */
class TaggedResponse extends Response
{
    public function __construct(
        public readonly string $tag,
        string $body = '',
        int $statusCode = 200,
        array $headers = [],
    ) {
        parent::__construct($body, $statusCode, $headers);
    }
}

function createTaggedResponse(
    string $tag = 'tagged',
    string $body = '',
    int $statusCode = 200,
    array $headers = [],
): TaggedResponse {
    return new TaggedResponse($tag, $body, $statusCode, $headers);
}
