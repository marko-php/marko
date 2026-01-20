<?php

declare(strict_types=1);

namespace Marko\Routing\Middleware;

use Marko\Core\Container\ContainerInterface;
use Marko\Routing\Http\Request;
use Marko\Routing\Http\Response;

class MiddlewarePipeline
{
    public function __construct(
        private readonly ContainerInterface $container,
    ) {}

    /**
     * Process the request through the middleware pipeline.
     *
     * @param array<class-string<MiddlewareInterface>> $middlewareClasses
     * @param Request $request
     * @param callable(Request): Response $handler
     * @return Response
     */
    public function process(
        array $middlewareClasses,
        Request $request,
        callable $handler,
    ): Response {
        if (empty($middlewareClasses)) {
            return $handler($request);
        }

        $middlewareClass = array_shift($middlewareClasses);
        /** @var MiddlewareInterface $middleware */
        $middleware = $this->container->get($middlewareClass);

        return $middleware->handle(
            $request,
            fn (Request $r) => $this->process($middlewareClasses, $r, $handler),
        );
    }
}
