<?php

declare(strict_types=1);

namespace Marko\Integration\Fixture\Job;

use Marko\Core\Container\ContainerInterface;
use Marko\Database\Connection\ConnectionInterface;
use Marko\Queue\ContainerAwareJobInterface;
use Marko\Queue\Job;
use Marko\Queue\JobEnvelope;
use RuntimeException;

/**
 * Writes an authors row through the container's database connection, so a
 * test can prove the worker's connection is usable when the job runs.
 */
class InsertAuthorJob extends Job implements ContainerAwareJobInterface
{
    private ?ContainerInterface $container = null;

    public function __construct(
        public string $name,
    ) {}

    public function setContainer(
        ContainerInterface $container,
    ): void {
        $this->container = $container;
    }

    public function setJobEnvelope(
        JobEnvelope $jobEnvelope,
    ): void {
        // Not needed: this job carries only a name
    }

    public function releaseContainer(): void
    {
        $this->container = null;
    }

    public function handle(): void
    {
        if ($this->container === null) {
            throw new RuntimeException('InsertAuthorJob::handle() was called without a container.');
        }

        $this->container->get(ConnectionInterface::class)->execute(
            'INSERT INTO authors (name) VALUES (:name)',
            ['name' => $this->name],
        );
    }
}
