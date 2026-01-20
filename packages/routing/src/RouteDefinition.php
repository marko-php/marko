<?php

declare(strict_types=1);

namespace Marko\Routing;

readonly class RouteDefinition
{
    /** @var array<int, string> */
    public array $parameters;

    public string $regex;

    /**
     * @param array<int, string> $middleware
     */
    public function __construct(
        public string $method,
        public string $path,
        public string $controller,
        public string $action,
        public array $middleware = [],
    ) {
        $this->parameters = $this->extractParameters($path);
        $this->regex = $this->buildRegex($path);
    }

    /**
     * @return array<int, string>
     */
    private function extractParameters(
        string $path,
    ): array {
        preg_match_all('/\{([^}]+)\}/', $path, $matches);

        return $matches[1];
    }

    private function buildRegex(
        string $path,
    ): string {
        $pattern = preg_replace('/\{([^}]+)\}/', '(?P<$1>[^/]+)', $path);

        return '#^' . $pattern . '$#';
    }
}
