<?php

declare(strict_types=1);

namespace Marko\Authentication\Middleware;

use JsonException;
use Marko\Authentication\AuthManager;
use Marko\Authentication\Exceptions\AuthException;
use Marko\Authentication\Guard\TokenGuard;
use Marko\Routing\Http\Request;
use Marko\Routing\Http\Response;
use Marko\Routing\Middleware\MiddlewareInterface;

readonly class AuthMiddleware implements MiddlewareInterface
{
    public function __construct(
        private AuthManager $auth,
        private ?string $guard = null,
        private ?string $redirectTo = '/login',
    ) {}

    /**
     * @throws JsonException|AuthException
     */
    public function handle(
        Request $request,
        callable $next,
    ): Response {
        $guard = $this->auth->guard($this->guard);

        if ($guard->check()) {
            return $next($request);
        }

        // API guards return JSON 401
        if ($guard instanceof TokenGuard) {
            return Response::json(
                data: ['error' => 'Unauthorized'],
                statusCode: 401,
            );
        }

        // Web guards redirect if redirectTo is configured
        if ($this->redirectTo !== null) {
            return Response::redirect($this->redirectTo);
        }

        return new Response(
            body: 'Unauthorized',
            statusCode: 401,
        );
    }
}
