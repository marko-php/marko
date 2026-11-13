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
 * a service no request used is never constructed just to reset it.
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
     *     RefreshDatabase helper keeps its test transaction open this way)
     */
    public function reset(
        ResettableInterface ...$except,
    ): void {
        $instances = $this->container->resolvedInstances();
        ksort($instances);

        foreach ($instances as $instance) {
            $resettable = $this->resettableBehind($instance);

            if ($resettable === null || in_array($resettable, $except, true)) {
                continue;
            }

            $resettable->reset();
        }
    }

    /**
     * A plugin interface proxy implements only the plugged interface, so the
     * resettable service it wraps would otherwise keep one request's state.
     * A concrete subclass proxy is itself resettable and holds the state.
     */
    private function resettableBehind(
        object $instance,
    ): ?ResettableInterface {
        while ($instance instanceof PluginInterceptedInterface && !$instance instanceof ResettableInterface) {
            $instance = $instance->getPluginTarget();
        }

        return $instance instanceof ResettableInterface ? $instance : null;
    }
}
