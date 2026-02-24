<?php

declare(strict_types=1);

namespace Marko\Authorization\Middleware;

use JsonException;
use Marko\Authentication\Contracts\GuardInterface;
use Marko\Authorization\Attributes\Can;
use Marko\Authorization\Contracts\GateInterface;
use Marko\Routing\Http\Request;
use Marko\Routing\Http\Response;
use Marko\Routing\Middleware\MiddlewareInterface;
use ReflectionException;
use ReflectionMethod;

readonly class AuthorizationMiddleware implements MiddlewareInterface
{
    public function __construct(
        private GateInterface $gate,
        private GuardInterface $guard,
        private ?string $controller = null,
        private ?string $action = null,
    ) {}

    /**
     * @throws ReflectionException|JsonException
     */
    public function handle(
        Request $request,
        callable $next,
    ): Response {
        $canAttribute = $this->getCanAttribute();

        if ($canAttribute === null) {
            return $next($request);
        }

        if (!$this->guard->check()) {
            return $this->unauthorizedResponse($request);
        }

        $arguments = [];

        if ($canAttribute->entityClass !== null) {
            $arguments[] = $canAttribute->entityClass;
        }

        if ($this->gate->allows($canAttribute->ability, ...$arguments)) {
            return $next($request);
        }

        return $this->forbiddenResponse($request);
    }

    /**
     * @throws ReflectionException
     */
    private function getCanAttribute(): ?Can
    {
        if ($this->controller === null || $this->action === null) {
            return null;
        }

        $reflection = new ReflectionMethod($this->controller, $this->action);
        $attributes = $reflection->getAttributes(Can::class);

        if (empty($attributes)) {
            return null;
        }

        return $attributes[0]->newInstance();
    }

    /**
     * @throws JsonException
     */
    private function unauthorizedResponse(
        Request $request,
    ): Response {
        if ($this->isJsonRequest($request)) {
            return Response::json(
                data: ['error' => 'Unauthorized'],
                statusCode: 401,
            );
        }

        return new Response(
            body: 'Unauthorized',
            statusCode: 401,
        );
    }

    /**
     * @throws JsonException
     */
    private function forbiddenResponse(
        Request $request,
    ): Response {
        if ($this->isJsonRequest($request)) {
            return Response::json(
                data: ['error' => 'Forbidden'],
                statusCode: 403,
            );
        }

        return new Response(
            body: 'Forbidden',
            statusCode: 403,
        );
    }

    private function isJsonRequest(
        Request $request,
    ): bool {
        $accept = $request->header('Accept');

        return $accept !== null && str_contains($accept, 'application/json');
    }
}
