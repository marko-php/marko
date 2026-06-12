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
        $parts = preg_split('/(\{[^}]+\})/', $path, -1, PREG_SPLIT_DELIM_CAPTURE);
        $pattern = '';

        foreach ($parts as $part) {
            if (preg_match('/^\{([^}]+)\}$/', $part, $matches)) {
                $pattern .= '(?P<' . $matches[1] . '>[^/]+)';
            } else {
                $pattern .= preg_quote($part, '#');
            }
        }

        return '#^' . $pattern . '$#';
    }
}
