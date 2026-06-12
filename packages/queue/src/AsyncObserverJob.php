<?php

declare(strict_types=1);

namespace Marko\Queue;

use Marko\Core\Container\ContainerInterface;
use Marko\Queue\Exceptions\SerializationException;
use RuntimeException;

class AsyncObserverJob extends Job
{
    private ?ContainerInterface $container = null;

    private ?JobEnvelope $jobEnvelope = null;

    public function __construct(
        public readonly string $observerClass,
        public readonly string $eventData,
    ) {}

    public function setContainer(ContainerInterface $container): void
    {
        $this->container = $container;
    }

    public function setJobEnvelope(JobEnvelope $jobEnvelope): void
    {
        $this->jobEnvelope = $jobEnvelope;
    }

    /**
     * Execute the async observer job.
     *
     * Resolves the observer from the container and invokes its handle() method
     * with the deserialized event. When a JobEnvelope has been set, the eventData
     * is treated as an HMAC-signed envelope and verified before unserializing.
     *
     * @throws SerializationException|RuntimeException
     */
    public function handle(): void
    {
        if ($this->container === null) {
            throw new RuntimeException(
                'AsyncObserverJob::handle() was called without a container. '
                . 'Call setContainer() before handle() to provide the DI container for observer resolution.',
            );
        }

        $rawEventData = $this->jobEnvelope !== null
            ? $this->jobEnvelope->verifyAndUnwrap($this->eventData)
            : $this->eventData;

        $event = unserialize($rawEventData);

        $observer = $this->container->get($this->observerClass);
        $observer->handle($event);
    }
}
