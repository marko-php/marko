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

    public static function unexpectedGuest(): self
    {
        return new self(
            message: 'Expected user to be a guest but a user is authenticated.',
            context: 'Guard has an authenticated user.',
            suggestion: 'Call logout() or setUser(null) before asserting guest state.',
        );
    }
}
