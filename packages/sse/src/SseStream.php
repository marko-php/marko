<?php

declare(strict_types=1);

namespace Marko\Sse;

use Closure;
use Generator;
use IteratorAggregate;
use JsonException;
use Marko\Clock\SystemClock;
use Marko\PubSub\Subscription;
use Marko\Sse\Exceptions\SseException;
use Psr\Clock\ClockInterface;

readonly class SseStream implements IteratorAggregate
{
    /**
     * @param ClockInterface $clock Measures the heartbeat interval and timeout; pass a FakeClock in tests
     * @throws SseException
     */
    public function __construct(
        private ?Closure $dataProvider = null,
        private ?Subscription $subscription = null,
        private int $heartbeatInterval = 15,
        private int $timeout = 300,
        private int $pollInterval = 1,
        private ClockInterface $clock = new SystemClock(),
    ) {
        if (
            $this->dataProvider !== null
            && $this->subscription !== null
        ) {
            throw SseException::ambiguousSource();
        }

        if (
            $this->dataProvider === null
            && $this->subscription === null
        ) {
            throw SseException::noSource();
        }
    }

    /**
     * Maximum stream duration in seconds.
     */
    public function timeout(): int
    {
        return $this->timeout;
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
        $startTime = $this->now();
        $lastActivity = $this->now();
        $iterator = $this->subscription->getIterator();
        $iterator->rewind();

        while ($iterator->valid()) {
            if ($this->now() - $startTime >= $this->timeout) {
                return;
            }

            $message = $iterator->current();

            if ($message !== null) {
                $event = new SseEvent(
                    data: $message->payload,
                    event: $message->channel,
                );
                yield $event->format();
                $lastActivity = $this->now();
            } else {
                if ($this->now() - $lastActivity >= $this->heartbeatInterval) {
                    yield ": keepalive\n\n";
                    $lastActivity = $this->now();
                }

                sleep($this->pollInterval);
            }

            $iterator->next();
        }
    }

    /**
     * @return Generator<int, string>
     * @throws JsonException
     */
    private function iterateDataProvider(): Generator
    {
        $startTime = $this->now();
        $lastHeartbeat = $this->now();

        do {
            $events = ($this->dataProvider)();
            $hasEvents = false;

            foreach ($events as $event) {
                yield $event->format();
                $hasEvents = true;
                $lastHeartbeat = $this->now();
            }

            if (
                !$hasEvents
                && ($this->now() - $lastHeartbeat) >= $this->heartbeatInterval
            ) {
                yield ": keepalive\n\n";
                $lastHeartbeat = $this->now();
            }

            if ($this->now() - $startTime >= $this->timeout) {
                return;
            }

            sleep($this->pollInterval);
        } while (true);
    }

    /**
     * Current unix time in seconds, read from the injected clock.
     */
    private function now(): int
    {
        return $this->clock->now()->getTimestamp();
    }
}
