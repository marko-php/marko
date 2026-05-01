<?php

declare(strict_types=1);

namespace Marko\Inertia\Middleware;

use Marko\Config\ConfigRepositoryInterface;
use Marko\Config\Exceptions\ConfigException;
use Marko\Inertia\Exceptions\InertiaConfigurationException;
use Marko\Routing\Http\Request;
use Marko\Routing\Http\Response;
use Marko\Routing\Middleware\MiddlewareInterface;

readonly class InertiaMiddleware implements MiddlewareInterface
{
    public function __construct(
        private ConfigRepositoryInterface $config,
    ) {}

    public function handle(
        Request $request,
        callable $next,
    ): Response {
        $response = $next($request);

        if (! $this->isInertiaRequest($request)) {
            return $response;
        }

        $headers = $response->headers();
        $headers['Vary'] = $this->mergeVaryHeader($headers['Vary'] ?? null);

        if ($this->isRedirectResponse($response)) {
            $statusCode = $response->statusCode();

            if ($statusCode === 302 && in_array($request->method(), ['PUT', 'PATCH', 'DELETE'], true)) {
                $statusCode = 303;
            }

            return new Response(
                body: $response->body(),
                statusCode: $statusCode,
                headers: $headers,
            );
        }

        $headers['X-Inertia'] = 'true';

        $configuredVersion = $this->nullableScalarConfig('inertia.version');
        $requestVersion = $request->header('X-Inertia-Version');

        if (
            $request->method() === 'GET'
            && $configuredVersion !== null
            && $requestVersion !== null
            && $requestVersion !== $configuredVersion
        ) {
            return new Response(
                body: '',
                statusCode: 409,
                headers: array_merge($headers, [
                    'X-Inertia-Location' => $request->path(),
                ]),
            );
        }

        return new Response(
            body: $response->body(),
            statusCode: $response->statusCode(),
            headers: $headers,
        );
    }

    private function isInertiaRequest(Request $request): bool
    {
        return $request->header('X-Inertia') === 'true';
    }

    private function isRedirectResponse(Response $response): bool
    {
        return in_array($response->statusCode(), [301, 302, 303, 307, 308], true);
    }

    private function mergeVaryHeader(?string $vary): string
    {
        $values = array_map('trim', explode(',', (string) $vary));
        $values = array_filter($values, static fn (string $value): bool => $value !== '');

        if (! in_array('X-Inertia', $values, true)) {
            $values[] = 'X-Inertia';
        }

        return implode(', ', $values);
    }

    private function nullableScalarConfig(string $key): ?string
    {
        try {
            $value = $this->config->get($key);
        } catch (ConfigException $exception) {
            throw InertiaConfigurationException::missingOrInvalid($key, $exception);
        }

        if ($value === null) {
            return null;
        }

        if (is_string($value) || is_int($value) || is_float($value)) {
            return (string) $value;
        }

        throw InertiaConfigurationException::invalidVersion($key, $value);
    }
}
