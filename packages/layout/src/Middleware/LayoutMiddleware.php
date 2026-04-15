<?php

declare(strict_types=1);

namespace Marko\Layout\Middleware;

use Marko\Layout\Exceptions\LayoutNotFoundException;
use Marko\Layout\LayoutProcessorInterface;
use Marko\Layout\LayoutResolver;
use Marko\Routing\Http\Request;
use Marko\Routing\Http\Response;
use Marko\Routing\Middleware\MiddlewareInterface;
use Marko\Routing\RouteMatcherInterface;

readonly class LayoutMiddleware implements MiddlewareInterface
{
    public function __construct(
        private RouteMatcherInterface $routeMatcher,
        private LayoutProcessorInterface $layoutProcessor,
        private LayoutResolver $layoutResolver,
    ) {}

    public function handle(
        Request $request,
        callable $next,
    ): Response {
        $matched = $this->routeMatcher->match($request->method(), $request->path());

        if ($matched === null) {
            return $next($request);
        }

        $controllerClass = $matched->route->controller;
        $action = $matched->route->action;

        try {
            $this->layoutResolver->resolve($controllerClass, $action);
        } catch (LayoutNotFoundException) {
            return $next($request);
        }

        // Invoke the controller action for side effects (authorization, etc.),
        // but ignore its return value — layout components provide their own data.
        $next($request);

        return $this->layoutProcessor->process(
            controllerClass: $controllerClass,
            action: $action,
            routePath: $matched->route->path,
            routeParameters: $matched->parameters,
            request: $request,
        );
    }
}
