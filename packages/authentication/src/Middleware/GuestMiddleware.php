<?php

declare(strict_types=1);

namespace Marko\Authentication\Middleware;

use Marko\Authentication\AuthManager;
use Marko\Authentication\Exceptions\AuthException;
use Marko\Routing\Http\Request;
use Marko\Routing\Http\Response;
use Marko\Routing\Middleware\MiddlewareInterface;

readonly class GuestMiddleware implements MiddlewareInterface
{
    public function __construct(
        private AuthManager $auth,
        private string $redirectTo = '/',
        private ?string $guard = null,
    ) {}

    /**
     * @throws AuthException
     */
    public function handle(
        Request $request,
        callable $next,
    ): Response {
        $guard = $this->auth->guard($this->guard);

        if ($guard->guest()) {
            return $next($request);
        }

        return Response::redirect($this->redirectTo);
    }
}
