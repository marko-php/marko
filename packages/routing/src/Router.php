<?php

declare(strict_types=1);

namespace Marko\Routing;

use Marko\Core\Container\ContainerInterface;
use Marko\Routing\Http\Request;
use Marko\Routing\Http\Response;
use Marko\Routing\Middleware\MiddlewarePipeline;
use ReflectionMethod;

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

            $parameters = $this->resolveParameters(
                $controller,
                $matched->route->action,
                $matched->parameters,
                $request,
            );

            $result = $controller->{$matched->route->action}(...$parameters);

            return $this->wrapResult($result);
        };

        return $this->pipeline->process(
            $matched->route->middleware,
            $request,
            $handler,
        );
    }

    /**
     * Resolve controller method parameters from route params, POST data, and query string.
     *
     * @param array<string, mixed> $routeParams
     * @return array<mixed>
     */
    private function resolveParameters(
        object $controller,
        string $action,
        array $routeParams,
        Request $request,
    ): array {
        $reflection = new ReflectionMethod($controller, $action);
        $parameters = [];

        foreach ($reflection->getParameters() as $param) {
            $name = $param->getName();

            // Priority: route params > POST data > query string > default
            if (array_key_exists($name, $routeParams)) {
                $parameters[] = $routeParams[$name];
            } elseif (($postValue = $request->post($name)) !== null) {
                $parameters[] = $postValue;
            } elseif (($queryValue = $request->query($name)) !== null) {
                $parameters[] = $queryValue;
            } elseif ($param->isDefaultValueAvailable()) {
                $parameters[] = $param->getDefaultValue();
            } else {
                $parameters[] = null;
            }
        }

        return $parameters;
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
