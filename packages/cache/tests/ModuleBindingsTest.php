<?php

declare(strict_types=1);

use Marko\Cache\Contracts\LockFactoryInterface;
use Marko\Cache\Contracts\LockStoreInterface;
use Marko\Cache\Exceptions\NoDriverException;
use Marko\Cache\Lock\LockFactory;
use Marko\Cache\Lock\SleeperInterface;
use Marko\Cache\Lock\UsleepSleeper;
use Marko\Cache\Tests\Support\RecordingLockStore;
use Marko\Core\Container\Container;
use Marko\Testing\Fake\FakeClock;
use Psr\Clock\ClockInterface;

function cacheModuleContainer(): Container
{
    $container = new Container();
    $container->instance(ClockInterface::class, new FakeClock());
    $module = require dirname(__DIR__) . '/module.php';

    foreach ($module['bindings'] as $id => $implementation) {
        $container->bind($id, $implementation);
    }

    return $container;
}

describe('cache module bindings', function (): void {
    it('binds LockFactoryInterface to LockFactory over the bound lock store', function (): void {
        $container = cacheModuleContainer();
        $container->instance(LockStoreInterface::class, new RecordingLockStore());

        expect($container->get(LockFactoryInterface::class))->toBeInstanceOf(LockFactory::class);
    });

    it('binds the block() sleeper to UsleepSleeper', function (): void {
        expect(cacheModuleContainer()->get(SleeperInterface::class))->toBeInstanceOf(UsleepSleeper::class);
    });

    it('throws NoDriverException naming the driver packages when no lock store is bound', function (): void {
        expect(fn (): mixed => cacheModuleContainer()->get(LockFactoryInterface::class))
            ->toThrow(NoDriverException::class, 'No cache lock store installed.');
    });
});
