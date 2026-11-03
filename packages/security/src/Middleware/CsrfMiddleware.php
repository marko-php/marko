<?php

declare(strict_types=1);

namespace Marko\Security\Middleware;

use Marko\Routing\Attributes\RunsInnermost;
use Marko\Routing\Exceptions\CookieException;
use Marko\Routing\Http\Cookie;
use Marko\Routing\Http\Request;
use Marko\Routing\Http\Response;
use Marko\Routing\Middleware\MiddlewareInterface;
use Marko\Security\Contracts\CsrfTokenManagerInterface;
use Marko\Security\Exceptions\CsrfSessionUnavailableException;
use Marko\Security\Exceptions\CsrfTokenMismatchException;
use Marko\Session\Config\SessionConfig;
use Marko\Session\Contracts\SessionInterface;

/**
 * Registered globally by marko/security: every matched route that is not
 * exempted with #[WithoutMiddleware(CsrfMiddleware::class)] must carry a
 * valid token on state-changing requests.
 *
 * The token is read from the `_token` form field, the `X-CSRF-TOKEN` header,
 * or the `X-XSRF-TOKEN` header. Whenever the response persists the session,
 * the current token is mirrored to a readable `XSRF-TOKEN` cookie so SPA
 * clients (axios, Inertia) can send it back automatically.
 *
 * It runs innermost, after the route's own middleware, so an auth guard
 * answers a logged out visitor's stale form with its login redirect instead
 * of a token error.
 */
#[RunsInnermost]
class CsrfMiddleware implements MiddlewareInterface
{
    public const string COOKIE_NAME = 'XSRF-TOKEN';

    /**
     * The form field the token is read from.
     */
    public const string TOKEN_FIELD = '_token';

    private const array SAFE_METHODS = ['GET', 'HEAD', 'OPTIONS'];

    public function __construct(
        private readonly CsrfTokenManagerInterface $tokenManager,
        private readonly SessionInterface $session,
        private readonly SessionConfig $sessionConfig,
    ) {}

    /**
     * @throws CsrfTokenMismatchException|CsrfSessionUnavailableException|CookieException
     */
    public function handle(
        Request $request,
        callable $next,
    ): Response {
        if (!in_array($request->method(), self::SAFE_METHODS, true)) {
            $this->verify($request);
        }

        $response = $next($request);

        return $this->attachTokenCookie($request, $response);
    }

    /**
     * @throws CsrfTokenMismatchException|CsrfSessionUnavailableException
     */
    private function verify(
        Request $request,
    ): void {
        if (!$this->session->isAvailable()) {
            throw CsrfSessionUnavailableException::forRequest($request->method(), $request->path());
        }

        $token = $this->extractToken($request);

        if ($token === null || $token === '' || !$this->tokenManager->validate($token)) {
            throw CsrfTokenMismatchException::invalidToken();
        }
    }

    private function extractToken(
        Request $request,
    ): ?string {
        $postToken = $request->post(self::TOKEN_FIELD);

        if (is_string($postToken)) {
            return $postToken;
        }

        return $request->header('X-CSRF-TOKEN') ?? $request->header('X-XSRF-TOKEN');
    }

    /**
     * Mirror the token to the XSRF-TOKEN cookie when the session is persisted
     * on this response: it resumed the visitor's session, or the request
     * modified it (issuing a token counts). A session that is only read and
     * then discarded is left alone, so lazy session persistence is preserved
     * and a stale session cookie never mints a new session.
     *
     * @throws CookieException
     */
    private function attachTokenCookie(
        Request $request,
        Response $response,
    ): Response {
        if (!$this->isPersisted($request)) {
            return $response;
        }

        $token = $this->tokenManager->get();

        if ($request->cookie(self::COOKIE_NAME) === $token) {
            return $response;
        }

        return $response->withCookie(new Cookie(
            name: self::COOKIE_NAME,
            value: $token,
            path: $this->sessionConfig->cookiePath(),
            domain: $this->sessionConfig->cookieDomain(),
            secure: $this->sessionConfig->cookieSecure(),
            httpOnly: false,
            sameSite: 'Lax',
        ));
    }

    /**
     * Mirrors SessionMiddleware: a started session is saved when it resumed
     * the inbound session cookie or was modified.
     */
    private function isPersisted(
        Request $request,
    ): bool {
        if (!$this->session->started) {
            return false;
        }

        return $this->session->isModified()
            || $request->cookie($this->sessionConfig->cookieName()) === $this->session->getId();
    }
}
