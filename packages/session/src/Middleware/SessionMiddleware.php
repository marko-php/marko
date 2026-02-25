<?php

declare(strict_types=1);

namespace Marko\Session\Middleware;

use Marko\Routing\Http\Request;
use Marko\Routing\Http\Response;
use Marko\Routing\Middleware\MiddlewareInterface;
use Marko\Session\Contracts\SessionInterface;

readonly class SessionMiddleware implements MiddlewareInterface
{
    public function __construct(
        private SessionInterface $session,
    ) {}

    public function handle(
        Request $request,
        callable $next,
    ): Response {
        if (!$this->session->started) {
            $this->session->start();
        }

        try {
            $response = $next($request);
        } finally {
            $this->session->save();
        }

        return $response;
    }
}
