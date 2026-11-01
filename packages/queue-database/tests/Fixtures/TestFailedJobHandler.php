<?php

declare(strict_types=1);

namespace Marko\Queue\Database\Tests\Fixtures;

use Marko\Core\Container\Container;
use Marko\Core\Container\ContainerInterface;
use Marko\Core\Event\EventDispatcherInterface;
use Marko\Queue\FailedJobHandler;
use Marko\Queue\FailedJobRepositoryInterface;
use Marko\Queue\JobEnvelope;
use Marko\Queue\JobRunner;
use Marko\Testing\Fake\FakeEventDispatcher;
use Marko\Testing\Fake\FakeLogger;
use Psr\Clock\ClockInterface;

/**
 * Builds the real FailedJobHandler around a test's failed-job repository, envelope and clock.
 */
class TestFailedJobHandler
{
    public static function create(
        FailedJobRepositoryInterface $failedJobRepository,
        JobEnvelope $jobEnvelope,
        ClockInterface $clock,
        EventDispatcherInterface $eventDispatcher = new FakeEventDispatcher(),
        ContainerInterface $container = new Container(),
    ): FailedJobHandler {
        return new FailedJobHandler(
            $failedJobRepository,
            $jobEnvelope,
            new JobRunner($container, $jobEnvelope, $eventDispatcher),
            $eventDispatcher,
            $clock,
            logger: new FakeLogger(),
        );
    }
}
