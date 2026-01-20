<?php

declare(strict_types=1);

namespace Marko\Core\Event;

use Marko\Core\Container\ContainerInterface;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;

readonly class EventDispatcher implements EventDispatcherInterface
{
    public function __construct(
        private ContainerInterface $container,
        private ObserverRegistry $registry,
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

            $observer = $this->container->get($definition->observerClass);
            $observer->handle($event);
        }
    }
}
