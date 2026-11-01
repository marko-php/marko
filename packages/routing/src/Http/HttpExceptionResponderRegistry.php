<?php

declare(strict_types=1);

namespace Marko\Routing\Http;

use Marko\Core\Container\ContainerInterface;
use Marko\Core\Exceptions\HttpExceptionInterface;
use Marko\Routing\Discovery\HttpExceptionResponderDefinition;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;

/**
 * The #[HttpExceptionResponder] classes the ExceptionRenderer asks before its
 * default rendering.
 *
 * RoutingBootstrapper builds it from the discovery cache or a module scan.
 * Each responder is built once through the container on the first exception,
 * so responders can inject their dependencies without slowing every boot.
 */
class HttpExceptionResponderRegistry
{
    /** @var array<int, HttpExceptionResponderInterface>|null */
    private ?array $responders = null;

    /**
     * @param array<int, HttpExceptionResponderDefinition> $definitions In priority order, highest first
     */
    public function __construct(
        private readonly ContainerInterface $container,
        private readonly array $definitions = [],
    ) {}

    /**
     * The responders in priority order, highest first.
     *
     * @return array<int, HttpExceptionResponderInterface>
     * @throws ContainerExceptionInterface|NotFoundExceptionInterface
     */
    public function responders(): array
    {
        if ($this->responders === null) {
            $responders = [];

            foreach ($this->definitions as $definition) {
                /** @var HttpExceptionResponderInterface $responder */
                $responder = $this->container->get($definition->className);
                $responders[] = $responder;
            }

            $this->responders = $responders;
        }

        return $this->responders;
    }

    /**
     * The first responder's non-null response, or null when every responder passes.
     *
     * @throws ContainerExceptionInterface|NotFoundExceptionInterface
     */
    public function respond(
        HttpExceptionInterface $exception,
        Request $request,
    ): ?Response {
        foreach ($this->responders() as $responder) {
            $response = $responder->respond($exception, $request);

            if ($response !== null) {
                return $response;
            }
        }

        return null;
    }
}
