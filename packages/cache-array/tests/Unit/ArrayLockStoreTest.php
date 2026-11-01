<?php

declare(strict_types=1);

use Marko\Cache\Contracts\LockFactoryInterface;
use Marko\Cache\Contracts\LockStoreInterface;
use Marko\Cache\Lock\LockFactory;
use Marko\Cache\Lock\LockScope;
use Marko\Cache\Memory\Lock\ArrayLockStore;
use Marko\Cache\Tests\Support\ClockAdvancingSleeper;
use Marko\Cache\Tests\Support\LockStoreContract;
use Marko\Cache\Tests\Support\LockStoreHarness;
use Marko\Core\Container\Container;
use Marko\Testing\Fake\FakeClock;
use Psr\Clock\ClockInterface;

LockStoreContract::register('ArrayLockStore', fn (): LockStoreHarness => new class () implements LockStoreHarness
{
    private FakeClock $clock;

    private ArrayLockStore $store;

    public function __construct()
    {
        $this->clock = new FakeClock('2026-01-01 12:00:00 UTC');
        $this->store = new ArrayLockStore($this->clock);
    }

    public function store(): LockStoreInterface
    {
        return $this->store;
    }

    public function factory(): LockFactoryInterface
    {
        return new LockFactory($this->store, $this->clock, new ClockAdvancingSleeper($this->clock));
    }

    public function expire(
        string $name,
    ): void {
        $this->clock->travel('+61 seconds');
    }

    public function expectedScope(): LockScope
    {
        return LockScope::Process;
    }

    public function clockSeconds(): float
    {
        return (float) $this->clock->now()->format('U.u');
    }

    public function cleanUp(): void {}
});

it('holds the lock until the last moment of its expiry', function (): void {
    $clock = new FakeClock('2026-01-01 12:00:00 UTC');
    $store = new ArrayLockStore($clock);
    $store->acquire('video:42', 'owner-a', 10);

    $clock->travel('+9 seconds +999 milliseconds');
    $heldAtEnd = $store->currentOwner('video:42');
    $clock->travel('+1 millisecond');

    expect($heldAtEnd)->toBe('owner-a')
        ->and($store->currentOwner('video:42'))->toBeNull();
});

it('binds one shared ArrayLockStore so every LockFactory sees the same locks', function (): void {
    $container = new Container();
    $container->instance(ClockInterface::class, new FakeClock());

    foreach ([dirname(__DIR__, 3) . '/cache/module.php', dirname(__DIR__, 2) . '/module.php'] as $modulePath) {
        $module = require $modulePath;

        foreach ($module['bindings'] as $id => $implementation) {
            $container->bind($id, $implementation);
        }

        foreach ($module['singletons'] ?? [] as $id) {
            $container->singleton($id);
        }
    }

    $container->get(LockFactoryInterface::class)->create('video:42', 60)->acquire();

    expect($container->get(LockStoreInterface::class))->toBeInstanceOf(ArrayLockStore::class)
        ->and($container->get(LockFactoryInterface::class)->create('video:42', 60)->acquire())->toBeFalse();
});
