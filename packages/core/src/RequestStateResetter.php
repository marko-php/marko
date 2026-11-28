<?php

declare(strict_types=1);

namespace Marko\Core;

use Marko\Core\Container\ContainerInterface;
use Marko\Core\Contracts\ResettableInterface;
use Marko\Core\Plugin\PluginInterceptedInterface;

/**
 * Clears request-scoped state between requests served by one booted
 * application: the RoadRunner worker and the marko/testing HTTP test client
 * both call reset() before handling each request.
 *
 * Every already-resolved ResettableInterface instance is discovered
 * generically via ContainerInterface::resolvedInstances(), never by a
 * hardcoded per-package list. Only instances the container has already
 * built are touched, since resolvedInstances() never forces instantiation:
 * a service no request used is never constructed just to reset it. For each
 * resolved instance, only the outermost resettable object in its plugin proxy
 * chain is reset (the proxy itself when it is resettable, else the first
 * resettable service behind it), so one binding never resets twice.
 *
 * Reset order is fixed and deterministic — ascending by container binding
 * identifier — so it never depends on which services happened to be
 * resolved first, in case a resettable's reset() needs to run relative to
 * another's. A reset() that throws propagates: swallowing it could leak one
 * request's state (a session, an identity) into the next.
 */
readonly class RequestStateResetter
{
    public function __construct(
        private ContainerInterface $container,
    ) {}

    /**
     * @param ResettableInterface ...$except Instances to leave alone, matched by
     *     identity whatever binding id they were resolved under (the marko/testing
     *     RefreshDatabase helper keeps its test transaction open this way). A plugin
     *     proxy is skipped when it, or anything it wraps, is passed here.
     */
    public function reset(
        ResettableInterface ...$except,
    ): void {
        $instances = $this->container->resolvedInstances();
        ksort($instances);

        foreach ($instances as $instance) {
            $chain = $this->proxyChain($instance);

            if (array_any($chain, fn (object $link): bool => in_array($link, $except, true))) {
                continue;
            }

            $resettable = array_find(
                $chain,
                fn (object $link): bool => $link instanceof ResettableInterface,
            );

            $resettable?->reset();
        }
    }

    /**
     * The instance followed by every object a plugin proxy wraps, outermost
     * first. A plugin interface proxy implements the plugged interface and
     * only those other interfaces it can reproduce, so a resettable service it
     * wraps may be reachable only through the chain. A concrete subclass proxy
     * of a resettable class is itself resettable and holds the copied state, so
     * it is the one reset.
     *
     * @return array<int, object>
     */
    private function proxyChain(
        object $instance,
    ): array {
        $chain = [$instance];

        while ($instance instanceof PluginInterceptedInterface) {
            $instance = $instance->getPluginTarget();
            $chain[] = $instance;
        }

        return $chain;
    }
}
