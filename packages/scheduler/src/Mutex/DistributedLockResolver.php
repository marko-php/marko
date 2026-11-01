<?php

declare(strict_types=1);

namespace Marko\Scheduler\Mutex;

use Marko\Cache\Contracts\LockFactoryInterface;
use Marko\Cache\Contracts\LockStoreInterface;
use Marko\Cache\Lock\LockScope;
use Marko\Core\Container\ContainerInterface;
use Marko\Scheduler\Exceptions\SchedulerException;
use Throwable;

/**
 * Resolves marko/cache's lock factory for the lock-backed scheduler classes, only when one
 * of them is used, and refuses a store that does not exclude callers on every host.
 *
 * marko/cache is a suggested dependency of marko/scheduler, so the factory cannot be
 * constructor-injected: an application without marko/cache must still be able to build the
 * runner. A missing package, a missing store, or a file or array store throws
 * SchedulerException instead of silently running a task on every server.
 */
class DistributedLockResolver
{
    private ?LockFactoryInterface $factory = null;

    public function __construct(
        private readonly ContainerInterface $container,
    ) {}

    /**
     * @param string $requester What needs the lock, for the exception message, e.g. "Scheduled task 'x' uses onOneServer()"
     *
     * @throws SchedulerException When no lock factory resolves, or its store is not LockScope::Distributed
     */
    public function factory(
        string $requester,
    ): LockFactoryInterface {
        if ($this->factory !== null) {
            return $this->factory;
        }

        if (!interface_exists(LockFactoryInterface::class)) {
            throw SchedulerException::lockFactoryUnavailable($requester);
        }

        try {
            $factory = $this->container->get(LockFactoryInterface::class);
        } catch (Throwable $e) {
            throw SchedulerException::lockFactoryUnavailable($requester, $e);
        }

        $scope = $factory->scope();

        if ($scope !== LockScope::Distributed) {
            throw SchedulerException::lockStoreNotDistributed($requester, $scope->value);
        }

        return $this->factory = $factory;
    }

    /**
     * The store behind the factory, to read a lock's current holder.
     *
     * @throws SchedulerException When no lock factory resolves, or its store is not LockScope::Distributed
     */
    public function store(
        string $requester,
    ): LockStoreInterface {
        $this->factory($requester);

        return $this->container->get(LockStoreInterface::class);
    }
}
