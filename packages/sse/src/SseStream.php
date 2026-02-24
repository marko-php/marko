<?php

declare(strict_types=1);

namespace Marko\Sse;

use Closure;
use Generator;
use IteratorAggregate;
use JsonException;

readonly class SseStream implements IteratorAggregate
{
    public function __construct(
        private Closure $dataProvider,
        private int $heartbeatInterval = 15,
        private int $timeout = 300,
        private int $pollInterval = 1,
    ) {}

    /**
     * @return Generator<int, string>
     * @throws JsonException
     */
    public function getIterator(): Generator
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
