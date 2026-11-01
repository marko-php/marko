<?php

declare(strict_types=1);

use Marko\Cache\Contracts\LockFactoryInterface;
use Marko\Cache\Contracts\LockStoreInterface;
use Marko\Cache\Exceptions\NoDriverException;
use Marko\Cache\Lock\LockFactory;
use Marko\Cache\Tests\Support\RecordingLockStore;
use Marko\Core\Container\Container;
use Marko\Core\Sleep\SleeperInterface;
use Marko\Core\Sleep\UsleepSleeper;
use Marko\Testing\Fake\FakeClock;
use Psr\Clock\ClockInterface;

function cacheModuleContainer(): Container
{
    $container = new Container();
    $container->instance(ClockInterface::class, new FakeClock());
    // marko/core's default binding
    $container->bind(SleeperInterface::class, UsleepSleeper::class);
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

    it('leaves the block() sleeper binding to marko/core', function (): void {
        $module = require dirname(__DIR__) . '/module.php';

        expect($module['bindings'])->not->toHaveKey(SleeperInterface::class);
    });

    it('throws NoDriverException naming the driver packages when no lock store is bound', function (): void {
        expect(fn (): mixed => cacheModuleContainer()->get(LockFactoryInterface::class))
            ->toThrow(NoDriverException::class, 'No cache lock store installed.');
    });
});
