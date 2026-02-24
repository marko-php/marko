<?php

declare(strict_types=1);

namespace Marko\Security\Middleware;

use Marko\Routing\Http\Request;
use Marko\Routing\Http\Response;
use Marko\Routing\Middleware\MiddlewareInterface;
use Marko\Security\Contracts\CsrfTokenManagerInterface;
use Marko\Security\Exceptions\CsrfTokenMismatchException;

class CsrfMiddleware implements MiddlewareInterface
{
    private const array SAFE_METHODS = ['GET', 'HEAD', 'OPTIONS'];

    public function __construct(
        private readonly CsrfTokenManagerInterface $tokenManager,
    ) {}

    /**
     * @throws CsrfTokenMismatchException
     */
    public function handle(
        Request $request,
        callable $next,
    ): Response {
        if (in_array($request->method(), self::SAFE_METHODS, true)) {
            return $next($request);
        }

        $token = $this->extractToken($request);

        if ($token === null || !$this->tokenManager->validate($token)) {
            throw CsrfTokenMismatchException::invalidToken();
        }

        return $next($request);
    }

    private function extractToken(
        Request $request,
    ): ?string {
        $postToken = $request->post('_token');

        if ($postToken !== null) {
            return (string) $postToken;
        }

        return $request->header('X-CSRF-TOKEN');
    }
}
