<?php

declare(strict_types=1);

namespace Marko\Testing\Fake;

use Marko\Broadcasting\BroadcastableInterface;
use Marko\Broadcasting\BroadcasterInterface;
use Marko\Broadcasting\Channel;
use Marko\Broadcasting\Exceptions\BroadcastException;
use Marko\Testing\Exceptions\AssertionFailedException;

class FakeBroadcaster implements BroadcasterInterface
{
    /** @var list<array{channel: Channel, event: string, data: array<string, mixed>, id: ?string}> */
    public private(set) array $broadcasts = [];

    /**
     * @throws BroadcastException
     */
    public function broadcast(
        string|Channel $channel,
        string $event,
        array $data,
        ?string $id = null,
    ): void {
        $this->broadcasts[] = [
            'channel' => Channel::from($channel),
            'event' => $event,
            'data' => $data,
            'id' => $id,
        ];
    }

    /**
     * @throws BroadcastException
     */
    public function dispatch(
        BroadcastableInterface $broadcastable,
    ): void {
        foreach ($broadcastable->channels() as $channel) {
            $this->broadcast($channel, $broadcastable->event(), $broadcastable->payload());
        }
    }

    /**
     * Broadcasts of $event on $channel. A string matches by name; a Channel also matches its kind (public, private or presence).
     *
     * @return list<array{channel: Channel, event: string, data: array<string, mixed>, id: ?string}>
     */
    public function broadcastsOf(
        string|Channel $channel,
        string $event,
    ): array {
        return array_values(array_filter(
            $this->broadcasts,
            fn (array $entry): bool => $entry['event'] === $event
                && $entry['channel']->name === ($channel instanceof Channel ? $channel->name : $channel)
                && (
                    !$channel instanceof Channel
                    || (
                        $entry['channel']->isPrivate() === $channel->isPrivate()
                    && $entry['channel']->isPresence() === $channel->isPresence()
                    )
                ),
        ));
    }

    /**
     * @param callable(array<string, mixed>, ?string): bool|null $callback Receives the data and id
     * @throws AssertionFailedException
     */
    public function assertBroadcast(
        string|Channel $channel,
        string $event,
        ?callable $callback = null,
    ): void {
        $matches = $this->broadcastsOf($channel, $event);

        if ($callback !== null) {
            $matches = array_filter($matches, fn (array $entry): bool => $callback($entry['data'], $entry['id']));
        }

        if ($matches === []) {
            throw AssertionFailedException::expectedContains('broadcast', $this->describe($channel, $event));
        }
    }

    /**
     * @throws AssertionFailedException
     */
    public function assertNotBroadcast(
        string|Channel $channel,
        string $event,
    ): void {
        if ($this->broadcastsOf($channel, $event) !== []) {
            throw AssertionFailedException::unexpectedContains('broadcast', $this->describe($channel, $event));
        }
    }

    /**
     * @throws AssertionFailedException
     */
    public function assertBroadcastCount(
        int $expected,
    ): void {
        $actual = count($this->broadcasts);

        if ($actual !== $expected) {
            throw AssertionFailedException::expectedCount('broadcasts', $expected, $actual);
        }
    }

    /**
     * @throws AssertionFailedException
     */
    public function assertNothingBroadcast(): void
    {
        if ($this->broadcasts !== []) {
            throw AssertionFailedException::expectedEmpty('broadcasts');
        }
    }

    public function clear(): void
    {
        $this->broadcasts = [];
    }

    private function describe(
        string|Channel $channel,
        string $event,
    ): string {
        $name = $channel instanceof Channel ? $channel->name : $channel;

        return "[$event] on channel [$name]";
    }
}
