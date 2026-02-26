<?php

declare(strict_types=1);

namespace Marko\Routing;

use Marko\Core\Container\ContainerInterface;
use Marko\Routing\Http\Request;
use Marko\Routing\Http\Response;
use Marko\Routing\Middleware\MiddlewareInterface;
use Marko\Routing\Middleware\MiddlewarePipeline;
use ReflectionMethod;
use ReflectionNamedType;

readonly class Router
{
    private RouteMatcher $matcher;

    private MiddlewarePipeline $pipeline;

    /**
     * @param array<class-string<MiddlewareInterface>> $globalMiddleware
     */
    public function __construct(
        private RouteCollection $routes,
        private ContainerInterface $container,
        private array $globalMiddleware = [],
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

        $middleware = [...$this->globalMiddleware, ...$matched->route->middleware];

        return $this->pipeline->process(
            $middleware,
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
            $type = $param->getType();

            // Inject Request object when type-hinted
            if ($type instanceof ReflectionNamedType && $type->getName() === Request::class) {
                $parameters[] = $request;
                continue;
            }

            // Priority: route params > POST data > query string > default
            if (array_key_exists($name, $routeParams)) {
                $parameters[] = $this->castToType($routeParams[$name], $type);
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

    private function castToType(
        mixed $value,
        ?\ReflectionType $type,
    ): mixed {
        if (!$type instanceof ReflectionNamedType) {
            return $value;
        }

        return match ($type->getName()) {
            'int' => (int) $value,
            'float' => (float) $value,
            'bool' => (bool) $value,
            default => $value,
        };
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
