<?php

declare(strict_types=1);

namespace Marko\Routing;

use JsonException;
use Marko\Core\Container\ContainerInterface;
use Marko\Core\Plugin\PluginInterceptedInterface;
use Marko\Routing\Attributes\InputSource;
use Marko\Routing\Attributes\RunsInnermost;
use Marko\Routing\Attributes\RunsOnUnmatched;
use Marko\Routing\Exceptions\HttpException;
use Marko\Routing\Exceptions\InvalidRouteParameterException;
use Marko\Routing\Exceptions\MalformedJsonException;
use Marko\Routing\Exceptions\RouteException;
use Marko\Routing\Http\ExceptionRenderer;
use Marko\Routing\Http\Request;
use Marko\Routing\Http\Response;
use Marko\Routing\Middleware\MiddlewareInterface;
use Marko\Routing\Middleware\MiddlewarePipeline;
use Psr\Container\ContainerExceptionInterface;
use ReflectionAttribute;
use ReflectionClass;
use ReflectionException;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionType;

readonly class Router
{
    private MiddlewarePipeline $pipeline;

    /**
     * @param array<class-string<MiddlewareInterface>> $globalMiddleware
     */
    public function __construct(
        private RouteMatcherInterface $matcher,
        private ContainerInterface $container,
        private array $globalMiddleware = [],
        ExceptionRenderer $exceptionRenderer = new ExceptionRenderer(),
    ) {
        $this->pipeline = new MiddlewarePipeline($container, $exceptionRenderer);
    }

    /**
     * Dispatch a request through global middleware (and route middleware when a route matches).
     *
     * Unmatched requests run only the global middleware marked
     * #[RunsOnUnmatched] (CORS, for example), never session, CSRF or auth;
     * the terminal handler answers them with a 404, a 405 carrying `Allow`,
     * or — for OPTIONS — an automatic 204 with `Allow`. Responses to HEAD
     * never carry a body.
     *
     * @throws ContainerExceptionInterface|ReflectionException|JsonException
     */
    public function handle(
        Request $request,
    ): Response {
        $response = $this->dispatch($request);

        return $request->method() === 'HEAD' ? $response->withoutBody() : $response;
    }

    /**
     * @throws ContainerExceptionInterface|ReflectionException|JsonException
     */
    private function dispatch(
        Request $request,
    ): Response {
        $matched = $this->matcher->match($request->method(), $request->path());

        if ($matched === null) {
            return $this->pipeline->process(
                $this->unmatchedMiddleware(),
                $request,
                $this->unmatchedHandler($request),
            );
        }

        $request = $request->withRoute($matched->route->controller, $matched->route->action);

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
            $this->middlewareFor($matched->route),
            $request,
            $handler,
        );
    }

    /**
     * Global middleware, then route middleware, then the global middleware
     * marked #[RunsInnermost] (in declaration order), minus anything the
     * route excludes with #[WithoutMiddleware].
     *
     * @return array<int, string>
     */
    private function middlewareFor(
        RouteDefinition $route,
    ): array {
        $outer = [];
        $innermost = [];

        foreach ($this->globalMiddleware as $global) {
            if ($this->hasClassAttribute($global, RunsInnermost::class)) {
                $innermost[] = $global;
            } else {
                $outer[] = $global;
            }
        }

        $middleware = [...$outer, ...$route->middleware, ...$innermost];

        if ($route->withoutMiddleware === []) {
            return $middleware;
        }

        return array_values(array_diff($middleware, $route->withoutMiddleware));
    }

    /**
     * The global middleware that opted in to requests no route matched with
     * #[RunsOnUnmatched], in declaration order. A class that cannot be loaded
     * cannot carry the attribute, so it is skipped here; matched routes still
     * fail loudly when the container resolves it.
     *
     * @return array<int, string>
     */
    private function unmatchedMiddleware(): array
    {
        return array_values(array_filter(
            $this->globalMiddleware,
            fn (string $middleware): bool => $this->hasClassAttribute($middleware, RunsOnUnmatched::class),
        ));
    }

    /**
     * Whether a loadable class carries the attribute. A class that cannot be
     * loaded carries none; the container fails loudly when it resolves it.
     *
     * @param class-string $attribute
     */
    private function hasClassAttribute(
        string $class,
        string $attribute,
    ): bool {
        return class_exists($class)
            && new ReflectionClass($class)->getAttributes($attribute) !== [];
    }

    /**
     * Terminal handler for a request no route matched.
     *
     * @return callable(Request): Response
     */
    private function unmatchedHandler(
        Request $request,
    ): callable {
        $allowedMethods = $this->matcher->allowedMethods($request->path());

        /** @throws HttpException */
        return function (Request $request) use ($allowedMethods): Response {
            if ($allowedMethods === []) {
                throw HttpException::notFound();
            }

            if ($request->method() === 'OPTIONS') {
                return new Response(
                    body: '',
                    statusCode: 204,
                    headers: ['Allow' => implode(', ', $allowedMethods)],
                );
            }

            throw HttpException::methodNotAllowed($allowedMethods);
        };
    }

    /**
     * Resolve controller method parameters.
     *
     * A parameter is bound from, in order: a `Request` type hint, an input
     * attribute (#[FromQuery], #[FromBody], #[FromInput]), the route path, or
     * the container for class and interface types. Request input never reaches a
     * parameter that has not opted in with an attribute. Anything left falls
     * back to its default value, then to null when the type allows it.
     *
     * @param array<string, mixed> $routeParams
     * @return array<mixed>
     * @throws ReflectionException|InvalidRouteParameterException|MalformedJsonException|HttpException|RouteException|ContainerExceptionInterface
     */
    private function resolveParameters(
        object $controller,
        string $action,
        array $routeParams,
        Request $request,
    ): array {
        // Unwrap interceptor to reflect on the real controller
        $reflectionTarget = $controller instanceof PluginInterceptedInterface
            ? $controller->getPluginTarget()
            : $controller;
        $reflection = new ReflectionMethod($reflectionTarget, $action);
        $parameters = [];

        foreach ($reflection->getParameters() as $param) {
            $parameters[] = $this->resolveParameter(
                $param,
                $routeParams,
                $request,
                $reflectionTarget::class,
                $action,
            );
        }

        return $parameters;
    }

    /**
     * @param array<string, mixed> $routeParams
     * @throws InvalidRouteParameterException|MalformedJsonException|HttpException|RouteException|ContainerExceptionInterface
     */
    private function resolveParameter(
        ReflectionParameter $param,
        array $routeParams,
        Request $request,
        string $controller,
        string $action,
    ): mixed {
        $name = $param->getName();
        $type = $param->getType();
        if (
            $type instanceof ReflectionNamedType
            && $type->getName() === Request::class
        ) {
            return $request;
        }

        $source = $this->inputSource($param, $controller, $action);

        if ($source !== null) {
            $value = $source->read($request, $source->name ?? $name);

            if ($value !== null) {
                return $this->coerce($value, $type, $name, $controller, $action);
            }

            if ($param->isDefaultValueAvailable()) {
                return $param->getDefaultValue();
            }

            if ($param->allowsNull()) {
                return null;
            }

            throw InvalidRouteParameterException::missingRequired(
                paramName: $name,
                expectedType: $type instanceof ReflectionType ? (string) $type : 'mixed',
                controller: $controller,
                action: $action,
            );
        }

        if (array_key_exists($name, $routeParams)) {
            return $this->coerce($routeParams[$name], $type, $name, $controller, $action);
        }

        if (
            $type instanceof ReflectionNamedType
            && !$type->isBuiltin()
        ) {
            return $this->resolveDependency($param, $type->getName());
        }

        if ($param->isDefaultValueAvailable()) {
            return $param->getDefaultValue();
        }

        if ($param->allowsNull()) {
            return null;
        }

        throw RouteException::unboundParameter(
            controller: $controller,
            action: $action,
            parameter: $name,
        );
    }

    /**
     * @throws RouteException
     */
    private function inputSource(
        ReflectionParameter $param,
        string $controller,
        string $action,
    ): ?InputSource {
        $attributes = $param->getAttributes(InputSource::class, ReflectionAttribute::IS_INSTANCEOF);

        if ($attributes === []) {
            return null;
        }

        if (count($attributes) > 1) {
            throw RouteException::conflictingInputSources(
                controller: $controller,
                action: $action,
                parameter: $param->getName(),
            );
        }

        return $attributes[0]->newInstance();
    }

    /**
     * Resolve a class or interface typed parameter from the container. A
     * parameter with a default (or a nullable one) keeps it when the container
     * cannot provide the type.
     *
     * @throws ContainerExceptionInterface
     */
    private function resolveDependency(
        ReflectionParameter $param,
        string $class,
    ): mixed {
        if (!$this->container->has($class)) {
            if ($param->isDefaultValueAvailable()) {
                return $param->getDefaultValue();
            }

            if ($param->allowsNull()) {
                return null;
            }
        }

        return $this->container->get($class);
    }

    /**
     * Convert a raw route or input value to the parameter's declared type.
     * Values that do not fit (`?page=abc` for `int`, `?name[]=x` for `string`)
     * are a client error and answered with a 400, never a TypeError.
     *
     * @throws HttpException|RouteException
     */
    private function coerce(
        mixed $value,
        ?ReflectionType $type,
        string $name,
        string $controller,
        string $action,
    ): mixed {
        if (
            !$type instanceof ReflectionNamedType
            || $type->getName() === 'mixed'
        ) {
            return $value;
        }

        $typeName = $type->getName();

        $coerced = match ($typeName) {
            'int' => (is_string($value) || is_int($value))
                ? filter_var($value, FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE)
                : null,
            'float' => (is_string($value) || is_int($value) || is_float($value))
                ? filter_var($value, FILTER_VALIDATE_FLOAT, FILTER_NULL_ON_FAILURE)
                : null,
            'bool' => (is_string($value) || is_int($value) || is_bool($value))
                ? filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE)
                : null,
            'string' => (is_string($value) || is_int($value) || is_float($value))
                ? (string) $value
                : null,
            'array' => is_array($value) ? $value : null,
            default => throw RouteException::unsupportedParameterType(
                controller: $controller,
                action: $action,
                parameter: $name,
                type: $typeName,
            ),
        };

        if ($coerced === null) {
            throw HttpException::badRequest("Invalid value for parameter '$name': expected $typeName");
        }

        return $coerced;
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
