<?php

declare(strict_types=1);

namespace Marko\Inertia\Middleware;

use Marko\Core\Container\ContainerInterface;
use Marko\Inertia\Inertia;
use Marko\Inertia\Enums\InertiaHeaderEnum;
use Marko\Inertia\Interfaces\InertiaInterface;
use Marko\Inertia\Props\OnceProp;
use Marko\Routing\Http\Request;
use Marko\Routing\Http\Response;
use Marko\Routing\Middleware\MiddlewareInterface;

class HandleInertiaRequests implements MiddlewareInterface
{
    public function __construct(
        private readonly InertiaInterface $inertia,
        private readonly ContainerInterface $container,
    ) {}

    public function handle(
        Request $request,
        callable $next,
    ): Response {
        // Ensure downstream controller resolution sees this request's Inertia instance.
        if ($this->inertia instanceof Inertia) {
            $this->container->instance(Inertia::class, $this->inertia);
        }

        $this->container->instance(InertiaInterface::class, $this->inertia);

        $this->inertia->flushShared();
        $this->inertia->share($this->share($request));

        foreach ($this->shareOnce($request) as $key => $value) {
            if ($value instanceof OnceProp) {
                $this->inertia->share($key, $value);
            } else {
                $this->inertia->share($key, $this->inertia->once($value));
            }
        }

        $response = $this->withVaryHeader($next($request));

        if (! $this->isInertiaRequest($request)) {
            return $response;
        }

        if ($response->statusCode() === 302 && in_array($request->method(), ['PUT', 'PATCH', 'DELETE'], true)) {
            $response = new Response(
                body: $response->body(),
                statusCode: 303,
                headers: $response->headers(),
            );
        }

        $isRedirect = in_array($response->statusCode(), [301, 302, 303, 307, 308], true);
        if ($isRedirect && $this->redirectHasFragment($response)) {
            $headers = $response->headers();

            return new Response(
                body: '',
                statusCode: 409,
                headers: [
                    InertiaHeaderEnum::LOCATION->value => $headers['Location'] ?? '',
                ],
            );
        }

        return $response;
    }

    /**
     * @return array<string, mixed>
     */
    protected function share(
        Request $request,
    ): array {
        return [];
    }

    /**
     * @return array<string, mixed>
     */
    protected function shareOnce(
        Request $request,
    ): array {
        return [];
    }

    private function withVaryHeader(
        Response $response,
    ): Response {
        $headers = $response->headers();
        $vary = $headers['Vary'] ?? '';
        $values = array_values(array_filter(array_map(
            static fn (string $value): string => trim($value),
            explode(',', $vary),
        )));

        if (! in_array(InertiaHeaderEnum::INERTIA->value, $values, true)) {
            $values[] = InertiaHeaderEnum::INERTIA->value;
        }

        $headers['Vary'] = implode(', ', $values);

        return new Response(
            body: $response->body(),
            statusCode: $response->statusCode(),
            headers: $headers,
        );
    }

    private function isInertiaRequest(
        Request $request,
    ): bool {
        return strtolower(
            (string) $request->header(InertiaHeaderEnum::INERTIA->value, ''),
        ) === 'true';
    }

    private function redirectHasFragment(Response $response): bool
    {
        $headers = $response->headers();
        $location = $headers['Location'] ?? '';

        return is_string($location) && str_contains($location, '#');
    }
}
