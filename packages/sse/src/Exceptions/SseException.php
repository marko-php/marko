<?php

declare(strict_types=1);

namespace Marko\Sse\Exceptions;

use Marko\Core\Exceptions\MarkoException;

/** @noinspection PhpUnused */
class SseException extends MarkoException
{
    public static function ambiguousSource(): self
    {
        return new self(
            message: 'SseStream cannot accept both a dataProvider and a subscription.',
            context: 'Both a dataProvider closure and a Subscription were passed to SseStream.',
            suggestion: 'Provide only one: either a dataProvider for polling or a subscription for real-time messages.',
        );
    }

    public static function noSource(): self
    {
        return new self(
            message: 'SseStream requires either a dataProvider or a subscription.',
            context: 'SseStream was created with neither a dataProvider nor a subscription.',
            suggestion: 'Pass a dataProvider closure or a Subscription instance to SseStream.',
        );
    }

    public static function invalidField(
        string $field,
        string $value,
    ): self {
        return new self(
            message: "SSE field '$field' must not contain CR or LF characters.",
            context: "SseEvent was constructed with a '$field' value containing a carriage return or line feed: " . json_encode(
                $value,
            ),
            suggestion: "Remove all CR (\\r) and LF (\\n) characters from the '$field' value before constructing SseEvent.",
        );
    }
}
