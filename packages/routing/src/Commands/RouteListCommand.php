<?php

declare(strict_types=1);

namespace Marko\Routing\Commands;

use Marko\Core\Attributes\Command;
use Marko\Core\Command\CommandInterface;
use Marko\Core\Command\Input;
use Marko\Core\Command\Output;
use Marko\Routing\RouteCollection;
use Marko\Routing\RouteDefinition;

/** @noinspection PhpUnused */
#[Command(name: 'route:list', description: 'Show all registered routes', options: ['method', 'path'])]
readonly class RouteListCommand implements CommandInterface
{
    public function __construct(
        private RouteCollection $routes,
    ) {}

    public function execute(
        Input $input,
        Output $output,
    ): int {
        $hasFilters = $input->hasOption('method') || $input->hasOption('path');
        $routes = $this->applyFilters($input);

        if ($routes === []) {
            $message = $hasFilters ? 'No routes match the given filters' : 'No routes registered';
            $output->writeLine($message);

            return 0;
        }

        $methodWidth = strlen('METHOD');
        $pathWidth = strlen('PATH');
        $nameWidth = strlen('NAME');
        $actionWidth = strlen('ACTION');

        foreach ($routes as $route) {
            $action = $this->shortAction($route);
            $methodWidth = max($methodWidth, strlen($route->method));
            $pathWidth = max($pathWidth, strlen($route->path));
            $nameWidth = max($nameWidth, strlen((string) $route->name));
            $actionWidth = max($actionWidth, strlen($action));
        }

        $methodWidth += 2;
        $pathWidth += 2;
        $nameWidth += 2;
        $actionWidth += 2;

        $output->writeLine(
            str_pad('METHOD', $methodWidth) .
            str_pad('PATH', $pathWidth) .
            str_pad('NAME', $nameWidth) .
            str_pad('ACTION', $actionWidth) .
            'MIDDLEWARE',
        );

        foreach ($routes as $route) {
            $action = $this->shortAction($route);
            $middleware = $this->shortMiddleware($route->middleware, $route->withoutMiddleware);
            $output->writeLine(rtrim(
                str_pad($route->method, $methodWidth) .
                str_pad($route->path, $pathWidth) .
                str_pad((string) $route->name, $nameWidth) .
                str_pad($action, $actionWidth) .
                $middleware,
            ));
        }

        return 0;
    }

    /**
     * Routes in effective match order: grouped by method, then in the order
     * the matcher tries them (see RouteCollection).
     *
     * @return array<int, RouteDefinition>
     */
    private function applyFilters(Input $input): array
    {
        $routes = $this->routes->inMatchOrder();

        if ($input->hasOption('method')) {
            $method = strtoupper((string) $input->getOption('method'));
            $routes = array_values(
                array_filter($routes, fn (RouteDefinition $r): bool => $r->method === $method),
            );
        }

        if ($input->hasOption('path')) {
            $path = ltrim((string) $input->getOption('path'), '/');
            $routes = array_values(
                array_filter($routes, fn (RouteDefinition $r): bool => str_contains($r->path, $path)),
            );
        }

        return $routes;
    }

    private function shortAction(RouteDefinition $route): string
    {
        $parts = explode('\\', $route->controller);
        $shortClass = end($parts);

        return $shortClass . '::' . $route->action;
    }

    /**
     * Route middleware as short class names, then excluded middleware
     * prefixed with a minus sign.
     *
     * @param array<int, string> $middleware
     * @param array<int, string> $withoutMiddleware
     */
    private function shortMiddleware(
        array $middleware,
        array $withoutMiddleware,
    ): string {
        $shortName = function (string $class): string {
            $parts = explode('\\', $class);

            return end($parts);
        };

        return implode(', ', [
            ...array_map($shortName, $middleware),
            ...array_map(fn (string $class): string => '-' . $shortName($class), $withoutMiddleware),
        ]);
    }
}
