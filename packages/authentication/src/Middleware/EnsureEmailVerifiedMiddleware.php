<?php

declare(strict_types=1);

namespace Marko\Authentication\Middleware;

use Marko\Authentication\Config\AuthConfig;
use Marko\Authentication\EmailVerification\CurrentVerifiableUser;
use Marko\Authentication\Exceptions\AuthException;
use Marko\Authentication\Exceptions\EmailNotVerifiedException;
use Marko\Authentication\Exceptions\UnauthenticatedException;
use Marko\Config\Exceptions\ConfigNotFoundException;
use Marko\Routing\Http\Request;
use Marko\Routing\Http\Response;
use Marko\Routing\Middleware\MiddlewareInterface;

/**
 * Only lets users with a verified email address through.
 *
 * An unverified browser request is redirected to
 * authentication.verification.notice_route; a request that wants JSON gets a
 * 403 EmailNotVerifiedException instead.
 *
 * Place it after AuthMiddleware: a guest throws UnauthenticatedException. A
 * logged-in user whose class does not implement MustVerifyEmailInterface throws
 * a configuration error rather than being let through.
 */
readonly class EnsureEmailVerifiedMiddleware implements MiddlewareInterface
{
    public function __construct(
        private CurrentVerifiableUser $currentUser,
        private AuthConfig $authConfig,
    ) {}

    /**
     * @throws AuthException|ConfigNotFoundException|EmailNotVerifiedException|UnauthenticatedException
     */
    public function handle(
        Request $request,
        callable $next,
    ): Response {
        if ($this->currentUser->get()->hasVerifiedEmail()) {
            return $next($request);
        }

        if ($request->wantsJson()) {
            throw EmailNotVerifiedException::create();
        }

        return Response::redirect($this->authConfig->verificationNoticeRoute());
    }
}
