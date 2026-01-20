<?php

declare(strict_types=1);

namespace Marko\Routing;

use Marko\Core\Container\ContainerInterface;
use Marko\Routing\Http\Request;
use Marko\Routing\Http\Response;
use Marko\Routing\Middleware\MiddlewarePipeline;

readonly class Router
{
    private RouteMatcher $matcher;

    private MiddlewarePipeline $pipeline;

    public function __construct(
        private RouteCollection $routes,
        private ContainerInterface $container,
    ) {
        $this->matcher = new RouteMatcher($routes);
        $this->pipeline = new MiddlewarePipeline($container);
    }

    public function handle(
        Request $request,
    ): Response {
        $matched = $this->matcher->match($request->method(), $request->path());

        if ($matched === null) {
            return new Response('Not Found', 404);
        }

        $handler = function (Request $request) use ($matched): Response {
            $controller = $this->container->get($matched->route->controller);
            $result = $controller->{$matched->route->action}(...array_values($matched->parameters));

            return $this->wrapResult($result);
        };

        return $this->pipeline->process(
            $matched->route->middleware,
            $request,
            $handler,
        );
    }

    private function wrapResult(
        mixed $result,
    ): Response {
        if ($result instanceof Response) {
            return $result;
        }

        if (is_string($result)) {
            return new Response($result);
        }

        if (is_array($result)) {
            return Response::json($result);
        }

        return new Response((string) $result);
    }
}
