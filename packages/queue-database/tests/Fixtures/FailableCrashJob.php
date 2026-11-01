<?php

declare(strict_types=1);

namespace Marko\Queue\Database\Tests\Fixtures;

use Marko\Core\Container\ContainerInterface;
use Marko\Queue\ContainerAwareJobInterface;
use Marko\Queue\FailableJobInterface;
use Marko\Queue\Job;
use Marko\Queue\JobEnvelope;
use Throwable;

/**
 * Container-aware job whose failed() reports to the FailedHookRecorder resolved from its container.
 */
class FailableCrashJob extends Job implements ContainerAwareJobInterface, FailableJobInterface
{
    private ?ContainerInterface $container = null;

    public function __construct(
        public string $message = 'crashy',
    ) {}

    public function setContainer(ContainerInterface $container): void
    {
        $this->container = $container;
    }

    public function setJobEnvelope(JobEnvelope $jobEnvelope): void {}

    public function releaseContainer(): void
    {
        $this->container = null;
    }

    public function handle(): void {}

    public function failed(Throwable $exception): void
    {
        $this->container->get(FailedHookRecorder::class)->record($this->message, $exception);
    }
}
