<?php

declare(strict_types=1);

namespace Marko\AuthenticationTwoFactor\Middleware;

use Marko\Authentication\AuthManager;
use Marko\Authentication\Exceptions\AuthException;
use Marko\Authentication\Exceptions\UnauthenticatedException;
use Marko\AuthenticationTwoFactor\Config\TwoFactorConfig;
use Marko\AuthenticationTwoFactor\Contracts\TwoFactorAuthenticatableInterface;
use Marko\AuthenticationTwoFactor\Contracts\TwoFactorManagerInterface;
use Marko\AuthenticationTwoFactor\Exceptions\TwoFactorException;
use Marko\AuthenticationTwoFactor\Exceptions\TwoFactorRequiredException;
use Marko\Config\Exceptions\ConfigNotFoundException;
use Marko\Routing\Http\Request;
use Marko\Routing\Http\Response;
use Marko\Routing\Middleware\MiddlewareInterface;

/**
 * Lets through only users who have confirmed two-factor authentication, for
 * routes such as an admin area where it is mandatory.
 *
 * Place it after AuthMiddleware. A browser request from a user without
 * confirmed two-factor is redirected to authentication-two-factor.setup_route;
 * a request that wants JSON gets a 403 TwoFactorRequiredException instead.
 */
readonly class RequireTwoFactorMiddleware implements MiddlewareInterface
{
    public function __construct(
        private AuthManager $authManager,
        private TwoFactorManagerInterface $twoFactorManager,
        private TwoFactorConfig $twoFactorConfig,
        private ?string $guard = null,
    ) {}

    /**
     * @throws AuthException|ConfigNotFoundException|TwoFactorException|TwoFactorRequiredException|UnauthenticatedException
     */
    public function handle(
        Request $request,
        callable $next,
    ): Response {
        $guard = $this->authManager->guard($this->guard);
        $user = $guard->user();

        if ($user === null) {
            throw UnauthenticatedException::forGuard($guard);
        }

        if (!$user instanceof TwoFactorAuthenticatableInterface) {
            throw TwoFactorException::userNotTwoFactorAuthenticatable($user::class);
        }

        if ($this->twoFactorManager->isEnabled($user)) {
            return $next($request);
        }

        if ($request->wantsJson()) {
            throw TwoFactorRequiredException::forGuard($guard->getName());
        }

        return Response::redirect($this->twoFactorConfig->setupRoute());
    }
}
