<?php

declare(strict_types=1);

namespace Marko\Layout\Middleware;

use Marko\Layout\Exceptions\LayoutNotFoundException;
use Marko\Layout\LayoutProcessorInterface;
use Marko\Layout\LayoutResolver;
use Marko\Routing\Attributes\RunsInnermost;
use Marko\Routing\Http\Request;
use Marko\Routing\Http\Response;
use Marko\Routing\Middleware\MiddlewareInterface;
use Marko\Routing\RouteMatcherInterface;

/**
 * Renders the layout of a matched route whose controller declares #[Layout].
 *
 * Marked #[RunsInnermost], so it runs after every route middleware, directly
 * around the controller: route middleware that denies the request answers
 * before any layout renders, and route middleware that adds headers or
 * cookies on the way out decorates the rendered layout response.
 */
#[RunsInnermost]
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

        // Run the controller (and anything inside this middleware) first.
        // Denials that reach here — 404s thrown by the controller, a redirect
        // it returns — arrive as ordinary responses because the pipeline
        // renders HTTP exceptions at the depth they are thrown. They must reach
        // the client untouched; only a successful controller result is replaced
        // by the layout, whose components provide their own data.
        $response = $next($request);

        if (!$this->isSuccessfulResult($response)) {
            return $response;
        }

        $layout = $this->layoutProcessor->process(
            controllerClass: $controllerClass,
            action: $action,
            routePath: $matched->route->path,
            routeParameters: $matched->parameters,
            request: $request,
        );

        return $this->carryOver($response, $layout);
    }

    /**
     * Keep the headers and cookies the controller put on its result. The
     * layout's own headers and cookies win on a conflict, and Content-Type
     * and Content-Length always describe the rendered layout body.
     */
    private function carryOver(
        Response $result,
        Response $layout,
    ): Response {
        $layoutHeaders = array_map(strtolower(...), array_keys($layout->headers()));
        $headers = [];

        foreach ($result->headers() as $name => $value) {
            $lower = strtolower($name);

            if (
                in_array($lower, ['content-type', 'content-length'], true)
                || in_array($lower, $layoutHeaders, true)
            ) {
                continue;
            }

            $headers[$name] = $value;
        }

        $merged = $layout->withHeaders($headers);

        foreach ($result->cookies() as $cookie) {
            $merged = $merged->withCookie($cookie);
        }

        foreach ($layout->cookies() as $cookie) {
            $merged = $merged->withCookie($cookie);
        }

        return $merged;
    }

    private function isSuccessfulResult(
        Response $response,
    ): bool {
        $status = $response->statusCode();

        if (
            $status < 200
            || $status >= 300
        ) {
            return false;
        }

        foreach (array_keys($response->headers()) as $name) {
            if (strtolower($name) === 'location') {
                return false;
            }
        }

        return true;
    }
}
