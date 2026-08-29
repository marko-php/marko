<?php

declare(strict_types=1);

namespace Marko\Session\Middleware;

use Marko\Routing\Exceptions\CookieException;
use Marko\Routing\Http\Cookie;
use Marko\Routing\Http\Request;
use Marko\Routing\Http\Response;
use Marko\Routing\Middleware\MiddlewareInterface;
use Marko\Session\Config\SessionConfig;
use Marko\Session\Contracts\SessionInterface;
use Marko\Session\Exceptions\InvalidSessionIdException;

readonly class SessionMiddleware implements MiddlewareInterface
{
    private const int SECONDS_PER_MINUTE = 60;

    private const int EXPIRED_COOKIE_OFFSET_SECONDS = 42000;

    public function __construct(
        private SessionInterface $session,
        private SessionConfig $sessionConfig,
    ) {}

    /**
     * @throws CookieException
     */
    public function handle(
        Request $request,
        callable $next,
    ): Response {
        $inboundId = $this->inboundSessionId($request);

        if (!$this->session->started) {
            $this->seedSessionId($inboundId);
            $this->session->start();
        }

        try {
            $response = $next($request);
        } finally {
            $this->session->save();
        }

        return $this->attachSessionCookie($response, $inboundId);
    }

    private function inboundSessionId(
        Request $request,
    ): ?string {
        $value = $request->cookie($this->sessionConfig->cookieName());

        return is_string($value) && $value !== '' ? $value : null;
    }

    private function seedSessionId(
        ?string $inboundId,
    ): void {
        if ($inboundId === null) {
            return;
        }

        try {
            $this->session->setId($inboundId);
        } catch (InvalidSessionIdException) {
            // Attacker-controlled cookie value — ignore and fall through to a fresh session
            // rather than surfacing a 500 for a tampered or malformed inbound cookie.
        }
    }

    /**
     * @throws CookieException
     */
    private function attachSessionCookie(
        Response $response,
        ?string $inboundId,
    ): Response {
        $outgoingId = $this->session->getId();

        if ($outgoingId === '') {
            return $response->withCookie($this->expiredCookie());
        }

        if ($outgoingId === $inboundId) {
            return $response;
        }

        return $response->withCookie($this->freshCookie($outgoingId));
    }

    /**
     * @throws CookieException
     */
    private function freshCookie(
        string $id,
    ): Cookie {
        return new Cookie(
            name: $this->sessionConfig->cookieName(),
            value: $id,
            expires: $this->sessionConfig->expireOnClose()
                ? null
                : time() + $this->sessionConfig->lifetime() * self::SECONDS_PER_MINUTE,
            path: $this->sessionConfig->cookiePath(),
            domain: $this->sessionConfig->cookieDomain(),
            secure: $this->sessionConfig->cookieSecure(),
            httpOnly: $this->sessionConfig->cookieHttpOnly(),
            sameSite: ucfirst($this->sessionConfig->cookieSameSite()),
        );
    }

    /**
     * @throws CookieException
     */
    private function expiredCookie(): Cookie
    {
        return new Cookie(
            name: $this->sessionConfig->cookieName(),
            value: '',
            expires: time() - self::EXPIRED_COOKIE_OFFSET_SECONDS,
            path: $this->sessionConfig->cookiePath(),
            domain: $this->sessionConfig->cookieDomain(),
            secure: $this->sessionConfig->cookieSecure(),
            httpOnly: $this->sessionConfig->cookieHttpOnly(),
        );
    }
}
