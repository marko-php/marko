<?php

declare(strict_types=1);

namespace Marko\Broadcasting\Amphp\Server;

use Psr\Clock\ClockInterface;

/**
 * Bounded, per-process history of received events for Last-Event-ID replay.
 *
 * Every event gets a process-local sequence. Replaying from an id with sequence S is only safe
 * when each requested channel was subscribed before S and has evicted nothing newer than S;
 * otherwise events may be missing and the caller must send a reset instead.
 */
class ReplayBuffer
{
    private int $sequence = 0;

    /**
     * @var array<string, list<SseEvent>>
     */
    private array $events = [];

    /**
     * Sequence counter at the time each channel was subscribed.
     *
     * @var array<string, int>
     */
    private array $openedAt = [];

    /**
     * Highest sequence evicted from each channel.
     *
     * @var array<string, int>
     */
    private array $evictedUpTo = [];

    /**
     * @var array<string, int>
     */
    private array $sequenceById = [];

    /**
     * @param int $size Events kept per channel (0 disables replay)
     * @param int $ttl Seconds an event stays replayable
     */
    public function __construct(
        private readonly int $size,
        private readonly int $ttl,
        private readonly ClockInterface $clock,
    ) {}

    /**
     * Mark a channel as subscribed from now on: later events are complete for it.
     */
    public function openChannel(
        string $channel,
    ): void {
        $this->openedAt[$channel] = $this->sequence;
        $this->events[$channel] = [];
        unset($this->evictedUpTo[$channel]);
    }

    /**
     * Forget a channel whose subscription ended; its history can no longer be trusted.
     */
    public function dropChannel(
        string $channel,
    ): void {
        foreach ($this->events[$channel] ?? [] as $event) {
            $this->forgetId($event);
        }

        unset($this->events[$channel], $this->openedAt[$channel], $this->evictedUpTo[$channel]);
    }

    public function record(
        string $channel,
        string $id,
        string $event,
        string $data,
    ): SseEvent {
        $sseEvent = new SseEvent(++$this->sequence, $channel, $id, $event, $data, $this->now());

        if (
            $this->size > 0
            && isset($this->openedAt[$channel])
        ) {
            $this->events[$channel][] = $sseEvent;
            $this->sequenceById[$id] = $sseEvent->sequence;

            while (count($this->events[$channel]) > $this->size) {
                $this->evict($channel);
            }
        }

        return $sseEvent;
    }

    public function sequenceOf(
        string $id,
    ): ?int {
        $this->evictExpired();

        return $this->sequenceById[$id] ?? null;
    }

    /**
     * @param list<string> $channels
     */
    public function canReplay(
        array $channels,
        int $afterSequence,
    ): bool {
        $this->evictExpired();

        return array_all(
            $channels,
            fn (string $channel): bool => isset($this->openedAt[$channel])
                && $this->openedAt[$channel] <= $afterSequence
                && ($this->evictedUpTo[$channel] ?? 0) <= $afterSequence,
        );
    }

    /**
     * Events on the given channels after the one with id $lastEventId, in sequence order, or
     * null when that cannot be answered completely (unknown id, evicted, or subscribed later).
     *
     * @param list<string> $channels
     * @return list<SseEvent>|null
     */
    public function replay(
        array $channels,
        string $lastEventId,
    ): ?array {
        $after = $this->sequenceOf($lastEventId);

        if (
            $after === null
            || !$this->canReplay($channels, $after)
        ) {
            return null;
        }

        $events = [];

        foreach (array_unique($channels) as $channel) {
            foreach ($this->events[$channel] ?? [] as $event) {
                if ($event->sequence > $after) {
                    $events[] = $event;
                }
            }
        }

        usort($events, fn (SseEvent $a, SseEvent $b): int => $a->sequence <=> $b->sequence);

        return $events;
    }

    public function count(
        string $channel,
    ): int {
        $this->evictExpired();

        return count($this->events[$channel] ?? []);
    }

    /**
     * Current unix time in seconds, with microseconds.
     */
    private function now(): float
    {
        return (float) $this->clock->now()->format('U.u');
    }

    private function evictExpired(): void
    {
        $cutoff = $this->now() - $this->ttl;

        foreach ($this->events as $channel => $events) {
            while (
                $events !== []
                && $events[0]->receivedAt <= $cutoff
            ) {
                $this->evict($channel);
                $events = $this->events[$channel];
            }
        }
    }

    private function evict(
        string $channel,
    ): void {
        $event = array_shift($this->events[$channel]);

        if ($event === null) {
            return;
        }

        $this->evictedUpTo[$channel] = max($this->evictedUpTo[$channel] ?? 0, $event->sequence);
        $this->forgetId($event);
    }

    private function forgetId(
        SseEvent $event,
    ): void {
        if (($this->sequenceById[$event->id] ?? null) === $event->sequence) {
            unset($this->sequenceById[$event->id]);
        }
    }
}
