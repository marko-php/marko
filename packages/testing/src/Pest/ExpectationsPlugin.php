<?php

declare(strict_types=1);

namespace Marko\Testing\Pest;

use InvalidArgumentException;
use Marko\Broadcasting\Channel;
use Marko\Log\LogLevel;
use Marko\Notification\Contracts\NotifiableInterface;
use Marko\Testing\Exceptions\AssertionFailedException;
use Marko\Testing\Fake\FakeBroadcaster;
use Marko\Testing\Fake\FakeEventDispatcher;
use Marko\Testing\Fake\FakeFilesystem;
use Marko\Testing\Fake\FakeGuard;
use Marko\Testing\Fake\FakeHttpClient;
use Marko\Testing\Fake\FakeLogger;
use Marko\Testing\Fake\FakeMailer;
use Marko\Testing\Fake\FakeNotificationSender;
use Marko\Testing\Fake\FakeQueue;
use Marko\Testing\Fake\Http\RecordedRequest;
use Marko\Testing\Http\TestClient;
use Marko\Testing\Http\TestResponse;
use Pest\Contracts\Plugins\Bootable;
use Pest\Expectation;
use PHPUnit\Framework\Assert;

/**
 * Registers the marko/testing Pest expectations.
 *
 * Declared in composer.json under extra.pest.plugins, so pestphp/pest-plugin lists it in
 * vendor/pest-plugins.json and Pest boots it once its own functions are loaded, in the main
 * process and in every --parallel worker. No require in Pest.php is needed.
 *
 * Registration must not depend on Composer's autoload.files order: Composer includes a
 * package's files before Pest's Functions.php, when expect() does not exist yet.
 */
class ExpectationsPlugin implements Bootable
{
    public function boot(): void
    {
        self::registerExpectations();
    }

    /**
     * Registers every expectation. Idempotent: Pest stores extends by name, so registering
     * again overwrites the same entries.
     */
    public static function registerExpectations(): void
    {
        expect()->extend('toHaveDispatched', function (
            string $eventClass,
        ): Expectation {
            $fake = $this->value;

            if (! $fake instanceof FakeEventDispatcher) {
                throw new InvalidArgumentException(
                    'Expected FakeEventDispatcher, got ' . get_debug_type($fake),
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
                    'Expected FakeMailer, got ' . get_debug_type($fake),
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

        expect()->extend('toHaveNotified', function (
            NotifiableInterface $notifiable,
            string $notificationClass,
            ?callable $filter = null,
        ): Expectation {
            $fake = $this->value;

            if (! $fake instanceof FakeNotificationSender) {
                throw new InvalidArgumentException(
                    'Expected FakeNotificationSender, got ' . get_debug_type($fake),
                );
            }

            $type = $notifiable->getNotifiableType();
            $id = $notifiable->getNotifiableId();
            Assert::assertNotEmpty(
                $fake->sentTo($notifiable, $notificationClass, $filter),
                "Expected $notificationClass to be sent to $type#$id but it was not.",
            );

            return $this;
        });

        expect()->extend('toHaveSentRequest', function (
            ?callable $callback = null,
        ): Expectation {
            $fake = $this->value;

            if (! $fake instanceof FakeHttpClient) {
                throw new InvalidArgumentException(
                    'Expected FakeHttpClient, got ' . get_debug_type($fake),
                );
            }

            if ($callback === null) {
                Assert::assertTrue(
                    $fake->requests !== [],
                    'Expected at least one HTTP request to be sent but none were.',
                );
            } else {
                $found = array_any($fake->requests, fn (RecordedRequest $request) => $callback($request));
                Assert::assertTrue(
                    $found,
                    'Expected an HTTP request matching the callback to be sent but none matched.',
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
                    'Expected FakeQueue, got ' . get_debug_type($fake),
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

        expect()->extend('toHaveStored', function (
            string $path,
            ?string $content = null,
        ): Expectation {
            $fake = $this->value;

            if (! $fake instanceof FakeFilesystem) {
                throw new InvalidArgumentException(
                    'Expected FakeFilesystem, got ' . get_debug_type($fake),
                );
            }

            $stored = $fake->isFile($path);

            if ($stored && $content !== null) {
                $stored = $fake->read($path) === $content;
            }

            Assert::assertTrue(
                $stored,
                $content === null
                    ? "Expected a file to be stored at '$path' but none was."
                    : "Expected a file with the given contents to be stored at '$path' but none was.",
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
                    'Expected FakeLogger, got ' . get_debug_type($fake),
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

        expect()->extend('toHaveBroadcast', function (
            string|Channel $channel,
            string $event,
            ?callable $callback = null,
        ): Expectation {
            $fake = $this->value;

            if (! $fake instanceof FakeBroadcaster) {
                throw new InvalidArgumentException(
                    'Expected FakeBroadcaster, got ' . get_debug_type($fake),
                );
            }

            $matches = $fake->broadcastsOf($channel, $event);

            if ($callback !== null) {
                $matches = array_filter($matches, fn (array $entry) => $callback($entry['data'], $entry['id']));
            }

            $name = $channel instanceof Channel ? $channel->name : $channel;
            Assert::assertNotEmpty(
                $matches,
                "Expected $event to be broadcast on channel $name but it was not.",
            );

            return $this;
        });

        expect()->extend('toHaveAttempted', function (
            ?callable $callback = null,
        ): Expectation {
            $fake = $this->value;

            if (! $fake instanceof FakeGuard) {
                throw new InvalidArgumentException(
                    'Expected FakeGuard, got ' . get_debug_type($fake),
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

        expect()->extend('toHaveStatus', function (
            int $status,
        ): Expectation {
            $response = $this->value;

            if (! $response instanceof TestResponse) {
                throw new InvalidArgumentException(
                    'Expected TestResponse, got ' . get_debug_type($response),
                );
            }

            try {
                $response->assertStatus($status);
            } catch (AssertionFailedException $e) {
                Assert::fail($e->getMessage());
            }

            return $this;
        });

        expect()->extend('toHaveJsonPath', function (
            string $path,
            mixed $expected,
        ): Expectation {
            $response = $this->value;

            if (! $response instanceof TestResponse) {
                throw new InvalidArgumentException(
                    'Expected TestResponse, got ' . get_debug_type($response),
                );
            }

            try {
                $response->assertJsonPath($path, $expected);
            } catch (AssertionFailedException $e) {
                Assert::fail($e->getMessage());
            }

            return $this;
        });

        expect()->extend('toBeAuthenticated', function (
            ?string $guard = null,
        ): Expectation {
            $subject = $this->value;

            if ($subject instanceof TestClient) {
                try {
                    $subject->assertAuthenticated($guard);
                } catch (AssertionFailedException $e) {
                    Assert::fail($e->getMessage());
                }

                return $this;
            }

            if (! $subject instanceof FakeGuard) {
                throw new InvalidArgumentException(
                    'Expected FakeGuard or TestClient, got ' . get_debug_type($subject),
                );
            }

            Assert::assertTrue(
                $subject->check(),
                'Expected user to be authenticated but no user is set.',
            );

            return $this;
        });

        expect()->extend('toHaveSession', function (
            string $key,
            mixed $value = null,
        ): Expectation {
            $response = $this->value;

            if (! $response instanceof TestResponse) {
                throw new InvalidArgumentException(
                    'Expected TestResponse, got ' . get_debug_type($response),
                );
            }

            try {
                $response->assertSessionHas($key, $value);
            } catch (AssertionFailedException $e) {
                Assert::fail($e->getMessage());
            }

            return $this;
        });
    }
}
