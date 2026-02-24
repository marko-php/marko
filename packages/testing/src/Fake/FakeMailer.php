<?php

declare(strict_types=1);

namespace Marko\Testing\Fake;

use Marko\Mail\Contracts\MailerInterface;
use Marko\Mail\Message;
use Marko\Testing\Exceptions\AssertionFailedException;

class FakeMailer implements MailerInterface
{
    /** @var array<Message> */
    public private(set) array $sent = [];

    /** @var array<array{to: string, raw: string}> */
    public private(set) array $sentRaw = [];

    public function send(
        Message $message,
    ): bool {
        $this->sent[] = $message;

        return true;
    }

    public function sendRaw(
        string $to,
        string $raw,
    ): bool {
        $this->sentRaw[] = ['to' => $to, 'raw' => $raw];

        return true;
    }

    public function assertSent(
        ?callable $callback = null,
    ): void {
        if ($callback === null) {
            if ($this->sent === []) {
                throw AssertionFailedException::unexpectedEmpty('messages');
            }

            return;
        }

        if (!array_any($this->sent, fn ($message) => $callback($message))) {
            throw AssertionFailedException::unexpectedEmpty('messages');
        }
    }

    public function assertNothingSent(): void
    {
        if ($this->sent !== [] || $this->sentRaw !== []) {
            throw AssertionFailedException::expectedEmpty('messages');
        }
    }

    public function assertSentCount(
        int $expected,
    ): void {
        $actual = count($this->sent);

        if ($actual !== $expected) {
            throw AssertionFailedException::expectedCount('messages', $expected, $actual);
        }
    }

    public function clear(): void
    {
        $this->sent = [];
        $this->sentRaw = [];
    }
}
