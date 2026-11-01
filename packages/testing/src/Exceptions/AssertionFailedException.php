<?php

declare(strict_types=1);

namespace Marko\Testing\Exceptions;

use Marko\Core\Exceptions\MarkoException;

class AssertionFailedException extends MarkoException
{
    public static function expectedDispatched(
        string $eventClass,
    ): self {
        return new self(
            message: "Expected [$eventClass] to be dispatched but it was not.",
            context: "Event class: $eventClass",
            suggestion: 'Ensure the event is dispatched before asserting.',
        );
    }

    public static function unexpectedDispatched(
        string $eventClass,
    ): self {
        return new self(
            message: "Expected [$eventClass] NOT to be dispatched but it was.",
            context: "Event class: $eventClass",
            suggestion: 'Remove the dispatch call or update your test expectation.',
        );
    }

    public static function expectedCount(
        string $type,
        int $expected,
        int $actual,
    ): self {
        return new self(
            message: "Expected $expected $type but got $actual.",
            context: "Expected: $expected, Actual: $actual",
            suggestion: "Ensure exactly $expected $type are created before asserting.",
        );
    }

    public static function expectedContains(
        string $type,
        string $needle,
    ): self {
        return new self(
            message: "Expected $type collection to contain $needle but it was not found.",
            context: "Looking for: $needle in $type collection",
            suggestion: "Ensure the $type is created or dispatched before asserting.",
        );
    }

    public static function unexpectedContains(
        string $type,
        string $needle,
    ): self {
        return new self(
            message: "Expected $type collection NOT to contain $needle but it was found.",
            context: "Found: $needle in $type collection",
            suggestion: "Remove the $type or update your test expectation.",
        );
    }

    public static function expectedEmpty(
        string $type,
    ): self {
        return new self(
            message: "Expected no $type but some were found.",
            context: "Type: $type",
            suggestion: "Ensure no $type are created before asserting.",
        );
    }

    public static function unexpectedEmpty(
        string $type,
    ): self {
        return new self(
            message: "Expected at least one $type but none were found.",
            context: "Type: $type",
            suggestion: "Ensure at least one $type is created before asserting.",
        );
    }

    public static function expectedAuthenticated(): self
    {
        return new self(
            message: 'Expected user to be authenticated but no user is set.',
            context: 'Guard has no authenticated user.',
            suggestion: 'Call login() or setUser() before asserting authentication.',
        );
    }

    public static function strayRequest(
        string $method,
        string $url,
    ): self {
        return new self(
            message: "Unexpected HTTP request: $method $url",
            context: 'FakeHttpClient has no stub matching this URL and no queued response left.',
            suggestion: 'Register a response with stub($urlPattern, $response) or queue($response), '
                . 'or call preventStrayRequests(false) to allow unmatched requests.',
        );
    }

    /**
     * A TestResponse assertion failed. The message carries the response status and the
     * start of the body, so the failure explains itself without a debugger.
     */
    public static function responseAssertion(
        string $expectation,
        int $status,
        string $body,
    ): self {
        $excerpt = self::bodyExcerpt($body);

        return new self(
            message: "$expectation\n\nResponse status: $status\nResponse body: $excerpt",
            context: "Response status: $status",
            suggestion: 'Check the route, its middleware and the controller for the request that produced this response.',
        );
    }

    public static function invalidJsonResponse(
        int $status,
        string $body,
        string $error,
    ): self {
        return self::responseAssertion(
            "Expected the response body to be valid JSON but decoding failed: $error.",
            $status,
            $body,
        );
    }

    public static function notStreamedResponse(
        string $responseClass,
    ): self {
        return new self(
            message: "Expected a streamed response but got $responseClass.",
            context: 'TestResponse::streamedContent() runs StreamedResponseInterface::sendBody(); this response holds its body in body().',
            suggestion: 'Read body() for a regular Response, or return a StreamedResponse, FileResponse or other StreamedResponseInterface from the route.',
        );
    }

    public static function unexpectedGuest(): self
    {
        return new self(
            message: 'Expected user to be a guest but a user is authenticated.',
            context: 'Guard has an authenticated user.',
            suggestion: 'Call logout() or setUser(null) before asserting guest state.',
        );
    }

    private static function bodyExcerpt(
        string $body,
    ): string {
        $limit = 500;

        if ($body === '') {
            return '(empty)';
        }

        if (strlen($body) <= $limit) {
            return $body;
        }

        return substr($body, 0, $limit) . '... (' . (strlen($body) - $limit) . ' more bytes)';
    }
}
