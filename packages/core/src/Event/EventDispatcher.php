<?php

declare(strict_types=1);

namespace Marko\Core\Event;

use Marko\Core\Container\ContainerInterface;
use Marko\Queue\AsyncObserverJob;
use Marko\Queue\QueueInterface;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;

readonly class EventDispatcher implements EventDispatcherInterface
{
    public function __construct(
        private ContainerInterface $container,
        private ObserverRegistry $registry,
        private ?QueueInterface $queue = null,
    ) {}

    /**
     * @throws ContainerExceptionInterface|NotFoundExceptionInterface
     */
    public function dispatch(
        Event $event,
    ): void {
        $eventClass = $event::class;
        $observers = $this->registry->getObserversFor($eventClass);

        // Sort by priority (higher first)
        usort($observers, fn (ObserverDefinition $a, ObserverDefinition $b) => $b->priority <=> $a->priority);

        foreach ($observers as $definition) {
            if ($event->propagationStopped) {
                break;
            }

            if ($definition->async && $this->queue !== null) {
                $job = new AsyncObserverJob(
                    $definition->observerClass,
                    serialize($event),
                );
                $this->queue->push($job);
            } else {
                $observer = $this->container->get($definition->observerClass);
                $observer->handle($event);
            }
        }
    }
}
