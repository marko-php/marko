<?php

declare(strict_types=1);

namespace Marko\Sse;

use Closure;
use Generator;
use IteratorAggregate;
use JsonException;
use Marko\PubSub\Subscription;
use Marko\Sse\Exceptions\SseException;

readonly class SseStream implements IteratorAggregate
{
    /**
     * @throws SseException
     */
    public function __construct(
        private ?Closure $dataProvider = null,
        private ?Subscription $subscription = null,
        private int $heartbeatInterval = 15,
        private int $timeout = 300,
        private int $pollInterval = 1,
    ) {
        if ($this->dataProvider !== null && $this->subscription !== null) {
            throw SseException::ambiguousSource();
        }

        if ($this->dataProvider === null && $this->subscription === null) {
            throw SseException::noSource();
        }
    }

    public function close(): void
    {
        $this->subscription?->cancel();
    }

    /**
     * @return Generator<int, string>
     * @throws JsonException
     */
    public function getIterator(): Generator
    {
        if ($this->subscription !== null) {
            yield from $this->iterateSubscription();
            return;
        }

        yield from $this->iterateDataProvider();
    }

    /**
     * @return Generator<int, string>
     * @throws JsonException
     */
    private function iterateSubscription(): Generator
    {
        $startTime = time();

        foreach ($this->subscription as $message) {
            if ((time() - $startTime) >= $this->timeout) {
                return;
            }

            $event = new SseEvent(
                data: $message->payload,
                event: $message->channel,
            );
            yield $event->format();
        }
    }

    /**
     * @return Generator<int, string>
     * @throws JsonException
     */
    private function iterateDataProvider(): Generator
    {
        $startTime = time();
        $lastHeartbeat = time();

        do {
            $events = ($this->dataProvider)();
            $hasEvents = false;

            foreach ($events as $event) {
                yield $event->format();
                $hasEvents = true;
                $lastHeartbeat = time();
            }

            if (!$hasEvents && (time() - $lastHeartbeat) >= $this->heartbeatInterval) {
                yield ": keepalive\n\n";
                $lastHeartbeat = time();
            }

            if ((time() - $startTime) >= $this->timeout) {
                return;
            }

            sleep($this->pollInterval);
        } while (true);
    }
}
