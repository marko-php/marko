<?php

declare(strict_types=1);

namespace Marko\Queue\Rabbitmq\Tests\Fixtures;

use Marko\Cache\Contracts\LockFactoryInterface;
use Marko\Cache\Lock\LockFactory;
use Marko\Cache\Lock\UsleepSleeper;
use Marko\Cache\Memory\Lock\ArrayLockStore;
use Marko\Core\Container\Container;
use Marko\Core\Container\ContainerInterface;
use Marko\Core\Event\EventDispatcher;
use Marko\Core\Event\ObserverDefinition;
use Marko\Core\Event\ObserverRegistry;
use Marko\Core\Plugin\InterceptorClassGenerator;
use Marko\Core\Plugin\PluginDiscovery;
use Marko\Core\Plugin\PluginInterceptor;
use Marko\Core\Plugin\PluginRegistry;
use Marko\Encryption\Config\EncryptionConfig;
use Marko\Queue\Event\JobFailedEvent;
use Marko\Queue\Event\JobProcessedEvent;
use Marko\Queue\Event\JobProcessingEvent;
use Marko\Queue\FailedJobHandler;
use Marko\Queue\FailedJobRepositoryInterface;
use Marko\Queue\JobEnvelope;
use Marko\Queue\JobRunner;
use Marko\Queue\Observer\ReleaseUniqueJobLockOnFailure;
use Marko\Queue\Observer\ReleaseUniqueJobLockOnProcessed;
use Marko\Queue\Observer\ReleaseUniqueJobLockOnProcessing;
use Marko\Queue\Plugin\UniqueJobPushPlugin;
use Marko\Queue\QueueInterface;
use Marko\Queue\UniqueJobInterface;
use Marko\Queue\UniqueJobLocks;
use Marko\Testing\Fake\FakeClock;
use Marko\Testing\Fake\FakeConfigRepository;

/**
 * The pieces an app wires for unique jobs, the way module discovery wires them: the push
 * plugin on QueueInterface, the three lifecycle observers, and an array lock store.
 */
class UniqueJobStack
{
    public readonly FakeClock $clock;

    public readonly Container $container;

    public readonly EventDispatcher $eventDispatcher;

    public readonly JobEnvelope $jobEnvelope;

    private readonly PluginInterceptor $interceptor;

    public function __construct(
        bool $withLockDriver = true,
    ) {
        $this->clock = new FakeClock();
        $this->container = new Container();
        $this->container->instance(ContainerInterface::class, $this->container);

        if ($withLockDriver) {
            $this->container->instance(
                LockFactoryInterface::class,
                new LockFactory(new ArrayLockStore($this->clock), $this->clock, new UsleepSleeper()),
            );
        } else {
            // As marko/cache binds it: LockFactory needs a LockStoreInterface that no driver binds
            $this->container->bind(LockFactoryInterface::class, LockFactory::class);
        }

        $observers = new ObserverRegistry();
        $observers->register(
            new ObserverDefinition(ReleaseUniqueJobLockOnProcessing::class, JobProcessingEvent::class),
        );
        $observers->register(new ObserverDefinition(ReleaseUniqueJobLockOnProcessed::class, JobProcessedEvent::class));
        $observers->register(new ObserverDefinition(ReleaseUniqueJobLockOnFailure::class, JobFailedEvent::class));
        $this->eventDispatcher = new EventDispatcher($this->container, $observers);

        $plugins = new PluginRegistry();
        $plugins->register(new PluginDiscovery()->parsePluginClass(UniqueJobPushPlugin::class));
        $this->interceptor = new PluginInterceptor($this->container, $plugins, new InterceptorClassGenerator());

        $this->jobEnvelope = new JobEnvelope(new EncryptionConfig(new FakeConfigRepository([
            'encryption.key' => 'unique-job-stack-key',
        ])));
    }

    /**
     * Wrap a driver in the plugin chain, exactly as the container does when it resolves QueueInterface.
     */
    public function wrap(
        QueueInterface $queue,
    ): QueueInterface {
        return $this->interceptor->createProxy(QueueInterface::class, $queue::class, $queue);
    }

    public function jobRunner(): JobRunner
    {
        return new JobRunner($this->container, $this->jobEnvelope, $this->eventDispatcher);
    }

    public function failedJobHandler(
        FailedJobRepositoryInterface $failedJobRepository,
    ): FailedJobHandler {
        return new FailedJobHandler(
            $failedJobRepository,
            $this->jobEnvelope,
            $this->jobRunner(),
            $this->eventDispatcher,
            $this->clock,
        );
    }

    /**
     * Whether the job's lock is free, checked by trying to take it with a throwaway owner.
     */
    public function lockIsFree(
        UniqueJobInterface $job,
    ): bool {
        $lockFactory = $this->container->get(LockFactoryInterface::class);
        $lock = $lockFactory->create(new UniqueJobLocks($this->container)->lockName($job), 1);

        if (!$lock->acquire()) {
            return false;
        }

        $lock->release();

        return true;
    }
}
