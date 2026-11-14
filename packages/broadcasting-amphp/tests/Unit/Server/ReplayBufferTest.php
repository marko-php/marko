<?php

declare(strict_types=1);

use Marko\Broadcasting\Amphp\Server\ReplayBuffer;
use Marko\Broadcasting\Amphp\Server\SseEvent;
use Marko\Testing\Fake\FakeClock;

function replayBuffer(
    FakeClock $clock,
    int $size = 3,
    int $ttl = 60,
): ReplayBuffer {
    return new ReplayBuffer(size: $size, ttl: $ttl, clock: $clock);
}

/**
 * @param list<SseEvent>|null $events
 * @return list<string>|null
 */
function replayedIds(
    ?array $events,
): ?array {
    return $events === null ? null : array_map(fn (SseEvent $event): string => $event->id, $events);
}

describe('ReplayBuffer', function (): void {
    it('assigns increasing sequences to recorded events', function (): void {
        $clock = new FakeClock('@0');
        $buffer = replayBuffer($clock);
        $buffer->openChannel('a');

        $first = $buffer->record('a', 'e1', 'x', '{}');
        $second = $buffer->record('a', 'e2', 'x', '{}');

        expect($first->sequence)->toBe(1)
            ->and($second->sequence)->toBe(2)
            ->and($second->channel)->toBe('a');
    });

    it('returns events after a sequence in order', function (): void {
        $clock = new FakeClock('@0');
        $buffer = replayBuffer($clock, size: 10);
        $buffer->openChannel('a');

        foreach (['e1', 'e2', 'e3', 'e4'] as $id) {
            $buffer->record('a', $id, 'x', '{}');
        }

        expect(replayedIds($buffer->replay(['a'], 'e2')))->toBe(['e3', 'e4'])
            ->and(replayedIds($buffer->replay(['a'], 'e4')))->toBeEmpty();
    });

    it('keeps at most replay_buffer events', function (): void {
        $clock = new FakeClock('@0');
        $buffer = replayBuffer($clock, size: 2);
        $buffer->openChannel('a');

        foreach (['e1', 'e2', 'e3', 'e4'] as $id) {
            $buffer->record('a', $id, 'x', '{}');
        }

        expect($buffer->count('a'))->toBe(2)
            ->and(replayedIds($buffer->replay(['a'], 'e3')))->toBe(['e4']);
    });

    it('reports whether it can replay from a sequence', function (): void {
        $clock = new FakeClock('@0');
        $buffer = replayBuffer($clock, size: 2);
        $buffer->openChannel('a');

        foreach (['e1', 'e2', 'e3', 'e4'] as $id) {
            $buffer->record('a', $id, 'x', '{}');
        }

        expect($buffer->canReplay(['a'], 3))->toBeTrue()
            ->and($buffer->canReplay(['a'], 1))->toBeFalse()
            ->and($buffer->canReplay(['b'], 3))->toBeFalse();
    });

    it('resolves an event id to its sequence and returns null for unknown ids', function (): void {
        $clock = new FakeClock('@0');
        $buffer = replayBuffer($clock);
        $buffer->openChannel('a');
        $buffer->openChannel('b');
        $buffer->record('a', 'e1', 'x', '{}');
        $buffer->record('b', 'shared', 'x', '{}');
        $buffer->record('a', 'shared', 'x', '{}');

        expect($buffer->sequenceOf('e1'))->toBe(1)
            ->and($buffer->sequenceOf('shared'))->toBe(3)
            ->and($buffer->sequenceOf('missing'))->toBeNull();
    });

    it('returns null (reset) when the last event id is unknown or too old', function (): void {
        $clock = new FakeClock('@0');
        $buffer = replayBuffer($clock, size: 2);
        $buffer->openChannel('a');

        foreach (['e1', 'e2', 'e3'] as $id) {
            $buffer->record('a', $id, 'x', '{}');
        }

        expect($buffer->replay(['a'], 'nope'))->toBeNull()
            ->and($buffer->replay(['a'], 'e1'))->toBeNull();
    });

    it('evicts events older than replay_ttl', function (): void {
        $clock = new FakeClock('@0');
        $buffer = replayBuffer($clock, size: 10, ttl: 60);
        $buffer->openChannel('a');
        $buffer->record('a', 'e1', 'x', '{}');
        $clock->travel('+30 seconds');
        $buffer->record('a', 'e2', 'x', '{}');
        $clock->travel('+40 seconds');

        expect($buffer->replay(['a'], 'e1'))->toBeNull()
            ->and(replayedIds($buffer->replay(['a'], 'e2')))->toBeEmpty()
            ->and($buffer->count('a'))->toBe(1);
    });

    it('evicts replay events once the injected clock passes the ttl', function (): void {
        $clock = new FakeClock('2026-01-01 12:00:00.250000 UTC');
        $buffer = replayBuffer($clock, size: 10, ttl: 60);
        $buffer->openChannel('a');
        $event = $buffer->record('a', 'e1', 'x', '{}');

        $clock->travel('+59 seconds');
        $keptBeforeTtl = $buffer->count('a');

        $clock->travel('+1 second');

        expect($event->receivedAt)->toBe(1767268800.25)
            ->and($keptBeforeTtl)->toBe(1)
            ->and($buffer->count('a'))->toBe(0);
    });

    it('merges events from several channels in sequence order', function (): void {
        $clock = new FakeClock('@0');
        $buffer = replayBuffer($clock, size: 10);
        $buffer->openChannel('a');
        $buffer->openChannel('b');
        $buffer->openChannel('c');
        $buffer->record('a', 'a1', 'x', '{}');
        $buffer->record('b', 'b1', 'x', '{}');
        $buffer->record('c', 'c1', 'x', '{}');
        $buffer->record('a', 'a2', 'x', '{}');
        $buffer->record('b', 'b2', 'x', '{}');

        expect(replayedIds($buffer->replay(['a', 'b'], 'a1')))->toBe(['b1', 'a2', 'b2']);
    });

    it('cannot replay a channel subscribed after the last event id', function (): void {
        $clock = new FakeClock('@0');
        $buffer = replayBuffer($clock, size: 10);
        $buffer->openChannel('a');
        $buffer->record('a', 'a1', 'x', '{}');
        $buffer->record('a', 'a2', 'x', '{}');
        $buffer->openChannel('b');
        $buffer->record('b', 'b1', 'x', '{}');

        expect($buffer->replay(['a', 'b'], 'a1'))->toBeNull()
            ->and(replayedIds($buffer->replay(['a', 'b'], 'a2')))->toBe(['b1']);
    });

    it("forgets a channel's history when the channel is dropped", function (): void {
        $clock = new FakeClock('@0');
        $buffer = replayBuffer($clock, size: 10);
        $buffer->openChannel('a');
        $buffer->record('a', 'e1', 'x', '{}');
        $buffer->record('a', 'e2', 'x', '{}');
        $buffer->dropChannel('a');
        $buffer->openChannel('a');

        expect($buffer->sequenceOf('e1'))->toBeNull()
            ->and($buffer->count('a'))->toBe(0)
            ->and($buffer->replay(['a'], 'e1'))->toBeNull();
    });

    it('resets every Last-Event-ID when replay_buffer is 0', function (): void {
        $clock = new FakeClock('@0');
        $buffer = replayBuffer($clock, size: 0);
        $buffer->openChannel('a');
        $buffer->record('a', 'e1', 'x', '{}');

        expect($buffer->replay(['a'], 'e1'))->toBeNull();
    });
});
