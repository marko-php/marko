<?php

declare(strict_types=1);

namespace Marko\Routing;

use Marko\Config\Exceptions\ConfigNotFoundException;
use Marko\Routing\Exceptions\UrlGenerationException;

readonly class UrlGenerator implements UrlGeneratorInterface
{
    private const int MAX_SUGGESTION_DISTANCE = 3;

    public function __construct(
        private RouteCollection $routeCollection,
        private RoutingConfig $routingConfig,
    ) {}

    /**
     * @param array<string, mixed> $parameters
     * @throws UrlGenerationException|ConfigNotFoundException
     */
    public function route(
        string $name,
        array $parameters = [],
        bool $absolute = false,
    ): string {
        $route = $this->routeCollection->named($name)
            ?? throw UrlGenerationException::unknownRoute($name, $this->similarNames($name));

        $replacements = [];

        foreach ($route->parameters as $parameter) {
            $value = $this->pathValue($route, $name, $parameter, $parameters[$parameter] ?? null);
            $replacements[$route->placeholders[$parameter]] = $parameter === $route->catchAll
                ? implode('/', array_map(rawurlencode(...), explode('/', $value)))
                : rawurlencode($value);
            unset($parameters[$parameter]);
        }

        $url = strtr($route->path, $replacements);

        if ($parameters !== []) {
            $query = http_build_query($parameters, '', '&', PHP_QUERY_RFC3986);
            $url .= $query === '' ? '' : '?' . $query;
        }

        return $absolute ? $this->baseUrl($name) . $url : $url;
    }

    /**
     * @throws UrlGenerationException
     */
    private function pathValue(
        RouteDefinition $route,
        string $name,
        string $parameter,
        mixed $value,
    ): string {
        if (
            $value !== null
            && !is_scalar($value)
        ) {
            throw UrlGenerationException::invalidParameterType($name, $parameter, get_debug_type($value));
        }

        $string = match (true) {
            $value === true => '1',
            $value === false => '0',
            default => (string) $value,
        };

        if ($parameter === $route->catchAll) {
            $string = trim($string, '/');
        }

        if ($string === '') {
            throw UrlGenerationException::missingParameter($name, $parameter, $route->path);
        }

        if (!$route->acceptsValue($parameter, $string)) {
            throw UrlGenerationException::unsafeParameterValue(
                $name,
                $parameter,
                $string,
                $parameter === $route->catchAll,
            );
        }

        if (!$route->satisfiesConstraint($parameter, $string)) {
            throw UrlGenerationException::constraintViolation(
                $name,
                $parameter,
                $string,
                $route->constraints[$parameter],
            );
        }

        return $string;
    }

    /**
     * @throws UrlGenerationException|ConfigNotFoundException
     */
    private function baseUrl(
        string $name,
    ): string {
        $baseUrl = rtrim($this->routingConfig->url(), '/');

        if ($baseUrl === '') {
            throw UrlGenerationException::missingBaseUrl($name);
        }

        return $baseUrl;
    }

    /**
     * @return array<int, string>
     */
    private function similarNames(
        string $name,
    ): array {
        return array_values(array_filter(
            $this->routeCollection->names(),
            fn (string $candidate): bool => levenshtein($name, $candidate) <= self::MAX_SUGGESTION_DISTANCE,
        ));
    }
}
