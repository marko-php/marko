<?php

declare(strict_types=1);

namespace Marko\Cache\Tests\Support;

use Closure;
use Marko\Cache\Exceptions\LockException;
use Marko\Cache\Exceptions\LockTimeoutException;

/**
 * One contract suite every LockStoreInterface implementation runs, so the
 * stores cannot drift apart. Call LockStoreContract::register() from a test
 * file in the driver package; the cases are registered on that file.
 */
class LockStoreContract
{
    /**
     * @param Closure(): LockStoreHarness $makeHarness Builds a fresh, empty harness per test
     * @param Closure(): bool|null $skip Returns true when the store's backend is unavailable
     */
    public static function register(
        string $storeName,
        Closure $makeHarness,
        ?Closure $skip = null,
        string $skipReason = '',
    ): void {
        $skip ??= fn (): bool => false;

        describe("$storeName lock store contract", function () use ($makeHarness, $skip, $skipReason): void {
            beforeEach(function () use ($makeHarness, $skip): void {
                if ($skip()) {
                    return;
                }

                $this->lockHarness = $makeHarness();
            });

            afterEach(function (): void {
                if (isset($this->lockHarness)) {
                    $this->lockHarness->cleanUp();
                }
            });

            it('refuses a second acquire while the first is held', function (): void {
                $store = $this->lockHarness->store();

                expect($store->acquire('contract', 'owner-a', 60))->toBeTrue()
                    ->and($store->acquire('contract', 'owner-b', 60))->toBeFalse()
                    ->and($store->acquire('contract', 'owner-a', 60))->toBeFalse()
                    ->and($store->currentOwner('contract'))->toBe('owner-a');
            })->skip($skip, $skipReason);

            it('keeps locks with different names independent', function (): void {
                $store = $this->lockHarness->store();

                expect($store->acquire('contract-one', 'owner-a', 60))->toBeTrue()
                    ->and($store->acquire('contract-two', 'owner-b', 60))->toBeTrue();
            })->skip($skip, $skipReason);

            it('accepts lock names with characters cache keys reject', function (): void {
                $store = $this->lockHarness->store();

                expect($store->acquire('video:42/{pipeline}', 'owner-a', 60))->toBeTrue()
                    ->and($store->currentOwner('video:42/{pipeline}'))->toBe('owner-a');
            })->skip($skip, $skipReason);

            it('makes the lock acquirable again after it expires', function (): void {
                $store = $this->lockHarness->store();
                $store->acquire('contract', 'owner-a', 60);

                $this->lockHarness->expire('contract');

                expect($store->currentOwner('contract'))->toBeNull()
                    ->and($store->acquire('contract', 'owner-b', 60))->toBeTrue()
                    ->and($store->currentOwner('contract'))->toBe('owner-b');
            })->skip($skip, $skipReason);

            it('releases the lock for its owner', function (): void {
                $store = $this->lockHarness->store();
                $store->acquire('contract', 'owner-a', 60);

                expect($store->release('contract', 'owner-a'))->toBeTrue()
                    ->and($store->currentOwner('contract'))->toBeNull()
                    ->and($store->acquire('contract', 'owner-b', 60))->toBeTrue();
            })->skip($skip, $skipReason);

            it('refuses a release by a non-owner', function (): void {
                $store = $this->lockHarness->store();
                $store->acquire('contract', 'owner-a', 60);

                expect($store->release('contract', 'owner-b'))->toBeFalse()
                    ->and($store->currentOwner('contract'))->toBe('owner-a');
            })->skip($skip, $skipReason);

            it('refuses a release of a lock that is not held or has expired', function (): void {
                $store = $this->lockHarness->store();

                expect($store->release('contract', 'owner-a'))->toBeFalse();

                $store->acquire('contract', 'owner-a', 60);
                $this->lockHarness->expire('contract');

                expect($store->release('contract', 'owner-a'))->toBeFalse();
            })->skip($skip, $skipReason);

            it('force releases whoever holds the lock', function (): void {
                $store = $this->lockHarness->store();
                $store->acquire('contract', 'owner-a', 60);

                $store->forceRelease('contract');

                expect($store->currentOwner('contract'))->toBeNull()
                    ->and($store->acquire('contract', 'owner-b', 60))->toBeTrue();
            })->skip($skip, $skipReason);

            it('waits in block() and then throws LockTimeoutException', function (): void {
                $factory = $this->lockHarness->factory();
                $factory->create('contract', 60)->acquire();
                $started = $this->lockHarness->clockSeconds();

                expect(fn () => $factory->create('contract', 60)->block(1))
                    ->toThrow(LockTimeoutException::class, "waiting for lock 'contract'")
                    ->and($this->lockHarness->clockSeconds() - $started)->toBeGreaterThanOrEqual(1.0);
            })->skip($skip, $skipReason);

            it('acquires in block() once the lock is free', function (): void {
                $lock = $this->lockHarness->factory()->create('contract', 60);

                $lock->block(1);

                expect($this->lockHarness->store()->currentOwner('contract'))->toBe($lock->owner());
            })->skip($skip, $skipReason);

            it('releases a lock restored by name and owner', function (): void {
                $factory = $this->lockHarness->factory();
                $lock = $factory->create('contract', 60);
                $lock->acquire();

                expect($factory->restore('contract', 'someone-else')->release())->toBeFalse()
                    ->and($factory->restore('contract', $lock->owner())->release())->toBeTrue()
                    ->and($this->lockHarness->store()->currentOwner('contract'))->toBeNull();
            })->skip($skip, $skipReason);

            it('throws when the lock expiry is below one second', function (): void {
                expect(fn () => $this->lockHarness->factory()->create('contract', 0))
                    ->toThrow(LockException::class, 'needs an expiry of at least 1 second');
            })->skip($skip, $skipReason);

            it('reports its scope', function (): void {
                expect($this->lockHarness->store()->scope())->toBe($this->lockHarness->expectedScope())
                    ->and($this->lockHarness->factory()->scope())->toBe($this->lockHarness->expectedScope());
            })->skip($skip, $skipReason);
        });
    }
}
