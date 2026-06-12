<?php

declare(strict_types=1);

namespace Marko\Queue;

use Marko\Queue\Exceptions\SerializationException;

class AsyncObserverJob extends Job
{
    public function __construct(
        public readonly string $observerClass,
        public readonly string $eventData,
    ) {}

    /**
     * Execute the async observer job.
     *
     * When a JobEnvelope is provided, the eventData is treated as an HMAC-signed envelope
     * and is verified before unserializing. Pass a JobEnvelope when running in a secure
     * context (e.g., inside the Worker) where the eventData was wrapped at construction time.
     *
     * @throws SerializationException
     */
    public function handle(
        ?callable $resolver = null,
        ?JobEnvelope $jobEnvelope = null,
    ): void {
        $rawEventData = $jobEnvelope !== null
            ? $jobEnvelope->verifyAndUnwrap($this->eventData)
            : $this->eventData;

        $event = unserialize($rawEventData);

        if ($resolver !== null) {
            $observer = $resolver($this->observerClass);
            $observer->handle($event);
        }
        // When no resolver provided, this is a no-op placeholder
        // Real implementation will use container to resolve observer
    }
}
