<?php

declare(strict_types=1);

use Marko\Cache\Contracts\LockFactoryInterface;
use Marko\Cache\Contracts\LockInterface;
use Marko\Cache\Exceptions\LockException;
use Marko\Cache\Exceptions\LockTimeoutException;
use Marko\Cache\Lock\Lock;
use Marko\Cache\Lock\LockFactory;
use Marko\Cache\Lock\LockScope;
use Marko\Cache\Tests\Support\ClockAdvancingSleeper;
use Marko\Cache\Tests\Support\RecordingLockStore;
use Marko\Testing\Fake\FakeClock;

beforeEach(function (): void {
    $this->store = new RecordingLockStore();
    $this->clock = new FakeClock('2026-01-01 12:00:00 UTC');
    $this->sleeper = new ClockAdvancingSleeper($this->clock);
    $this->factory = new LockFactory($this->store, $this->clock, $this->sleeper);
});

describe('LockFactory', function (): void {
    it('implements LockFactoryInterface', function (): void {
        expect($this->factory)->toBeInstanceOf(LockFactoryInterface::class);
    });

    it('creates a lock with the given name and a fresh random hex owner token', function (): void {
        $first = $this->factory->create('video:42', 30);
        $second = $this->factory->create('video:42', 30);

        expect($first)->toBeInstanceOf(LockInterface::class)
            ->and($first->name())->toBe('video:42')
            ->and($first->owner())->toMatch('/\A[0-9a-f]{32}\z/')
            ->and($second->owner())->not->toBe($first->owner());
    });

    it('passes the lock expiry to the store on acquire', function (): void {
        $lock = $this->factory->create('video:42', 30);

        expect($lock->acquire())->toBeTrue()
            ->and($this->store->calls)->toBe([['acquire', ['video:42', $lock->owner(), 30]]]);
    });

    it(
        'throws when the expiry is below one second so a crashed holder can never deadlock',
        function (int $seconds): void {
            expect(fn (): LockInterface => $this->factory->create('video:42', $seconds))
                ->toThrow(LockException::class, "Lock 'video:42' needs an expiry of at least 1 second, got $seconds");
        },
    )->with([0, -5]);

    it('throws on an empty lock name', function (): void {
        expect(fn (): LockInterface => $this->factory->create('', 30))
            ->toThrow(LockException::class, 'Lock name cannot be empty');
    });

    it('restores a lock by name and owner that releases the original holder', function (): void {
        $lock = $this->factory->create('video:42', 30);
        $lock->acquire();

        $restored = $this->factory->restore('video:42', $lock->owner());

        expect($restored->name())->toBe('video:42')
            ->and($restored->owner())->toBe($lock->owner())
            ->and($restored->release())->toBeTrue()
            ->and($this->store->currentOwner('video:42'))->toBeNull();
    });

    it('refuses to acquire through a restored lock because it has no expiry', function (): void {
        $restored = $this->factory->restore('video:42', 'abc123');

        expect(fn (): bool => $restored->acquire())
            ->toThrow(LockException::class, "Lock 'video:42' was restored and cannot be acquired");
    });

    it('throws when restoring with an empty owner', function (): void {
        expect(fn (): LockInterface => $this->factory->restore('video:42', ''))
            ->toThrow(LockException::class, "Lock 'video:42' needs an owner token");
    });

    it('reports the scope of the bound store', function (): void {
        $factory = new LockFactory(new RecordingLockStore(LockScope::Distributed), $this->clock, $this->sleeper);

        expect($factory->scope())->toBe(LockScope::Distributed)
            ->and($this->factory->scope())->toBe(LockScope::Process);
    });
});

describe('Lock', function (): void {
    it('refuses a release by an owner that does not hold the lock', function (): void {
        $holder = $this->factory->create('video:42', 30);
        $holder->acquire();

        expect($this->factory->create('video:42', 30)->release())->toBeFalse()
            ->and($this->store->currentOwner('video:42'))->toBe($holder->owner());
    });

    it('force releases whoever holds the lock', function (): void {
        $this->factory->create('video:42', 30)->acquire();

        $this->factory->restore('video:42', 'someone-else')->forceRelease();

        expect($this->store->currentOwner('video:42'))->toBeNull();
    });

    it('returns from block() at once when the lock is free', function (): void {
        $lock = $this->factory->create('video:42', 30);

        $lock->block(5);

        expect($this->store->currentOwner('video:42'))->toBe($lock->owner())
            ->and($this->sleeper->sleeps)->toBe([]);
    });

    it('polls in block() until the holder releases the lock', function (): void {
        $holder = $this->factory->create('video:42', 30);
        $holder->acquire();
        $sleeper = new ClockAdvancingSleeper(
            $this->clock,
            function (int $sleeps) use ($holder): void {
                if ($sleeps === 2) {
                    $holder->release();
                }
            },
        );
        $waiter = new LockFactory($this->store, $this->clock, $sleeper)->create('video:42', 30);

        $waiter->block(5);

        expect($this->store->currentOwner('video:42'))->toBe($waiter->owner())
            ->and($sleeper->sleeps)->toBe([Lock::RETRY_INTERVAL_MILLISECONDS, Lock::RETRY_INTERVAL_MILLISECONDS]);
    });

    it('throws LockTimeoutException from block() when the wait runs out', function (): void {
        $this->factory->create('video:42', 30)->acquire();
        $waiter = $this->factory->create('video:42', 30);

        expect(fn () => $waiter->block(1))
            ->toThrow(LockTimeoutException::class, "Timed out after 1 second waiting for lock 'video:42'")
            ->and(array_sum($this->sleeper->sleeps))->toBe(1000);
    });

    it('tries once and throws from block(0) when the lock is held', function (): void {
        $this->factory->create('video:42', 30)->acquire();

        expect(fn () => $this->factory->create('video:42', 30)->block(0))
            ->toThrow(LockTimeoutException::class)
            ->and($this->sleeper->sleeps)->toBe([]);
    });

    it('throws on a negative block() wait', function (): void {
        expect(fn () => $this->factory->create('video:42', 30)->block(-1))
            ->toThrow(LockException::class, "Lock 'video:42' cannot wait a negative number of seconds (-1)");
    });
});
