<?php

declare(strict_types=1);

namespace Marko\Authentication\Middleware;

use Marko\Authentication\Config\AuthConfig;
use Marko\Authentication\Contracts\GuardInterface;
use Marko\Authentication\Contracts\IntendedUrlInterface;
use Marko\Authentication\Contracts\PasswordConfirmationInterface;
use Marko\Authentication\Contracts\StatelessGuardInterface;
use Marko\Authentication\Exceptions\AuthException;
use Marko\Authentication\Exceptions\PasswordConfirmationRequiredException;
use Marko\Config\Exceptions\ConfigNotFoundException;
use Marko\Routing\Http\Request;
use Marko\Routing\Http\Response;
use Marko\Routing\Middleware\MiddlewareInterface;

/**
 * Asks the user to re-enter their password before a sensitive route when they
 * have not confirmed it within authentication.password_confirmation.timeout_seconds.
 *
 * A browser request is redirected to authentication.password_confirmation.route,
 * with the current URL remembered through IntendedUrlInterface so the
 * confirmation page can send the user back. A request that wants JSON gets a 423
 * PasswordConfirmationRequiredException instead.
 *
 * Place it after AuthMiddleware. It needs a session guard: a stateless default
 * guard (e.g. the token guard) has no session to hold the confirmation, so it
 * throws a configuration error rather than letting the request through.
 */
readonly class RequirePasswordConfirmationMiddleware implements MiddlewareInterface
{
    public function __construct(
        private GuardInterface $guard,
        private PasswordConfirmationInterface $passwordConfirmation,
        private IntendedUrlInterface $intendedUrl,
        private AuthConfig $authConfig,
    ) {}

    /**
     * @throws AuthException|ConfigNotFoundException|PasswordConfirmationRequiredException
     */
    public function handle(
        Request $request,
        callable $next,
    ): Response {
        if ($this->guard instanceof StatelessGuardInterface) {
            throw AuthException::passwordConfirmationNeedsSessionGuard($this->guard->getName());
        }

        if ($this->passwordConfirmation->isRecentlyConfirmed()) {
            return $next($request);
        }

        if ($request->wantsJson()) {
            throw PasswordConfirmationRequiredException::create();
        }

        $this->intendedUrl->remember($request);

        return Response::redirect($this->authConfig->passwordConfirmationRoute());
    }
}
