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
#[Command(name: 'route:list', description: 'Show all registered routes')]
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

        usort($routes, function (RouteDefinition $a, RouteDefinition $b): int {
            $pathCmp = strcmp($a->path, $b->path);

            if ($pathCmp !== 0) {
                return $pathCmp;
            }

            return strcmp($a->method, $b->method);
        });

        $methodWidth = strlen('METHOD');
        $pathWidth = strlen('PATH');
        $actionWidth = strlen('ACTION');

        foreach ($routes as $route) {
            $action = $this->shortAction($route);
            $methodWidth = max($methodWidth, strlen($route->method));
            $pathWidth = max($pathWidth, strlen($route->path));
            $actionWidth = max($actionWidth, strlen($action));
        }

        $methodWidth += 2;
        $pathWidth += 2;
        $actionWidth += 2;

        $output->writeLine(
            str_pad('METHOD', $methodWidth) .
            str_pad('PATH', $pathWidth) .
            str_pad('ACTION', $actionWidth) .
            'MIDDLEWARE',
        );

        foreach ($routes as $route) {
            $action = $this->shortAction($route);
            $middleware = $this->shortMiddleware($route->middleware);
            $output->writeLine(
                str_pad($route->method, $methodWidth) .
                str_pad($route->path, $pathWidth) .
                str_pad($action, $actionWidth) .
                $middleware,
            );
        }

        return 0;
    }

    /**
     * @return array<int, RouteDefinition>
     */
    private function applyFilters(Input $input): array
    {
        $routes = $this->routes->all();

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
     * @param array<int, string> $middleware
     */
    private function shortMiddleware(array $middleware): string
    {
        $short = array_map(function (string $class): string {
            $parts = explode('\\', $class);

            return end($parts);
        }, $middleware);

        return implode(', ', $short);
    }
}
