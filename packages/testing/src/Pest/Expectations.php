<?php

declare(strict_types=1);

use Marko\Log\LogLevel;
use Marko\Testing\Fake\FakeEventDispatcher;
use Marko\Testing\Fake\FakeGuard;
use Marko\Testing\Fake\FakeLogger;
use Marko\Testing\Fake\FakeMailer;
use Marko\Testing\Fake\FakeQueue;
use Pest\Expectation;
use PHPUnit\Framework\Assert;

if (function_exists('expect')) {
    expect()->extend('toHaveDispatched', function (
        string $eventClass,
    ): Expectation {
        $fake = $this->value;

        if (! $fake instanceof FakeEventDispatcher) {
            throw new InvalidArgumentException(
                'Expected FakeEventDispatcher, got ' . get_class($fake),
            );
        }

        $hasDispatched = count($fake->dispatched($eventClass)) > 0;
        Assert::assertTrue(
            $hasDispatched,
            "Expected $eventClass to be dispatched but it was not.",
        );

        return $this;
    });

    expect()->extend('toHaveSent', function (
        ?callable $callback = null,
    ): Expectation {
        $fake = $this->value;

        if (! $fake instanceof FakeMailer) {
            throw new InvalidArgumentException(
                'Expected FakeMailer, got ' . get_class($fake),
            );
        }

        if ($callback === null) {
            $hasSent = count($fake->sent) > 0;
            Assert::assertTrue(
                $hasSent,
                'Expected at least one message to be sent but none were.',
            );
        } else {
            $found = array_any($fake->sent, fn ($message) => $callback($message));
            Assert::assertTrue(
                $found,
                'Expected a message matching the callback to be sent but none matched.',
            );
        }

        return $this;
    });

    expect()->extend('toHavePushed', function (
        string $jobClass,
        ?callable $callback = null,
    ): Expectation {
        $fake = $this->value;

        if (! $fake instanceof FakeQueue) {
            throw new InvalidArgumentException(
                'Expected FakeQueue, got ' . get_class($fake),
            );
        }

        $matches = array_filter(
            $fake->pushed,
            fn (array $entry) => $entry['job'] instanceof $jobClass,
        );

        $hasPushed = count($matches) > 0;

        if ($hasPushed && $callback !== null) {
            $hasPushed = array_any($matches, fn (array $entry) => $callback($entry['job']));
        }

        Assert::assertTrue(
            $hasPushed,
            "Expected $jobClass to be pushed to queue but it was not.",
        );

        return $this;
    });

    expect()->extend('toHaveLogged', function (
        string $message,
        ?LogLevel $level = null,
    ): Expectation {
        $fake = $this->value;

        if (! $fake instanceof FakeLogger) {
            throw new InvalidArgumentException(
                'Expected FakeLogger, got ' . get_class($fake),
            );
        }

        $found = array_any(
            $fake->entries,
            fn (array $entry) => $entry['message'] === $message
                && ($level === null || $entry['level'] === $level),
        );

        Assert::assertTrue(
            $found,
            "Expected message \"$message\" to be logged but it was not.",
        );

        return $this;
    });

    expect()->extend('toHaveAttempted', function (
        ?callable $callback = null,
    ): Expectation {
        $fake = $this->value;

        if (! $fake instanceof FakeGuard) {
            throw new InvalidArgumentException(
                'Expected FakeGuard, got ' . get_class($fake),
            );
        }

        if ($callback === null) {
            $hasAttempted = count($fake->attempts) > 0;
            Assert::assertTrue(
                $hasAttempted,
                'Expected at least one authentication attempt but none were made.',
            );
        } else {
            $found = array_any($fake->attempts, fn (array $credentials) => $callback($credentials));
            Assert::assertTrue(
                $found,
                'Expected an authentication attempt matching the callback but none matched.',
            );
        }

        return $this;
    });

    expect()->extend('toBeAuthenticated', function (): Expectation {
        $fake = $this->value;

        if (! $fake instanceof FakeGuard) {
            throw new InvalidArgumentException(
                'Expected FakeGuard, got ' . get_class($fake),
            );
        }

        Assert::assertTrue(
            $fake->check(),
            'Expected user to be authenticated but no user is set.',
        );

        return $this;
    });
}
