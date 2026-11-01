<?php

declare(strict_types=1);

namespace Marko\Testing\Exceptions;

use Marko\Core\Exceptions\MarkoException;

class TestClientException extends MarkoException
{
    public static function unreadableUpload(
        string $path,
    ): self {
        return new self(
            message: "Cannot upload [$path]: the file does not exist or is not readable.",
            context: "withFile() was given the path $path",
            suggestion: 'Pass the path of an existing, readable file, e.g. a fixture under your tests directory.',
        );
    }

    public static function invalidUploadField(
        string $field,
    ): self {
        return new self(
            message: "[$field] is not a valid upload field name.",
            context: "withFile() was given the field $field",
            suggestion: 'Use a form field name: avatar, photos[] (one more file in a list) '
                . 'or documents[passport] (a nested field). Bracketed segments must be closed and nothing may follow them.',
        );
    }

    public static function duplicateUpload(
        string $field,
    ): self {
        return new self(
            message: "Cannot upload to [$field]: the field already has a file.",
            context: "withFile() was called twice for the single-file field $field",
            suggestion: "To send several files under one field, use a list field: withFile('" . $field . "[]', ...) "
                . "for each file, or withFiles('$field', [...]).",
        );
    }

    public static function conflictingUpload(
        string $field,
    ): self {
        return new self(
            message: "Cannot upload to [$field]: an earlier withFile() call gave this field a different shape.",
            context: "withFile() was called for $field after a single file and a list or nested field were mixed under the same name",
            suggestion: 'Use one shape per field: either a single file (avatar), a list (photos[]) '
                . 'or named nested fields (documents[passport]), as a browser form would send them.',
        );
    }

    public static function staleResponse(
        int $requestSequence,
        int $latestSequence,
    ): self {
        return new self(
            message: 'Cannot assert on the session: this response is stale: a later request has run on the client.',
            context: "The response is from request #$requestSequence; the client has since sent request #$latestSequence, "
                . 'which may have changed the session.',
            suggestion: 'Make session assertions on the latest response, before the client sends another request.',
        );
    }

    public static function responseWithoutClient(): self
    {
        return new self(
            message: 'This TestResponse has no session to assert on: it was not returned by a TestClient request.',
            context: 'Session assertions read the session through the TestClient that sent the request.',
            suggestion: 'Send the request with TestClient (get(), post(), ...) and assert on the TestResponse it returns.',
        );
    }

    public static function statelessGuard(
        string $guard,
    ): self {
        return new self(
            message: "Guard [$guard] is stateless: it keeps no login state between requests, so there is nothing to assert on.",
            context: 'A StatelessGuardInterface guard authenticates each request from the credentials it carries.',
            suggestion: 'Assert on the response of a request that carries (or lacks) the credentials instead, '
                . 'e.g. assertOk() or assertUnauthorized().',
        );
    }

    public static function bodyAndData(
        string $method,
        string $uri,
    ): self {
        return new self(
            message: "Cannot send both form data and a raw body with $method $uri.",
            context: 'call() was given a non-empty $data array and a $body string.',
            suggestion: 'Pass the payload either as $data (form-encoded) or as a raw $body, not both. '
                . 'For JSON use postJson(), putJson(), patchJson() or deleteJson().',
        );
    }
}
