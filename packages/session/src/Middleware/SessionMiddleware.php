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
use Psr\Clock\ClockInterface;

readonly class SessionMiddleware implements MiddlewareInterface
{
    private const int SECONDS_PER_MINUTE = 60;

    private const int EXPIRED_COOKIE_OFFSET_SECONDS = 42000;

    public function __construct(
        private SessionInterface $session,
        private SessionConfig $sessionConfig,
        private ClockInterface $clock,
    ) {}

    /**
     * Starting is lazy for requests without a usable session cookie: the
     * session is armed and starts on first access, so cookieless traffic
     * that never touches it makes no handler call (no read, no GC).
     *
     * Persistence is lazy: the session is saved (and its cookie sent) only
     * when the request resumed an existing session from its cookie or
     * modified the session. A session nobody wrote to is discarded, so
     * cookieless traffic that never touches the session (bots, health
     * checks, static pages) creates no stored session and gets no cookie.
     *
     * A session is resumed only when the store knows the inbound id: under
     * strict mode PHP asks the handler's validateId() and replaces an unknown
     * or expired id with a fresh one. A cookie that resumed nothing (unknown,
     * expired or malformed) is expired on the response unless a new session
     * was persisted in its place, so the client stops replaying it.
     *
     * @throws CookieException
     */
    public function handle(
        Request $request,
        callable $next,
    ): Response {
        $inboundId = $this->inboundSessionId($request);

        if (!$this->session->isAvailable()) {
            $this->prepare($inboundId);
        }

        $resumed = $inboundId !== null
            && $this->session->getId() === $inboundId;
        $persisted = false;

        try {
            $response = $next($request);
        } finally {
            $persisted = $this->close($resumed);
        }

        if (!$persisted) {
            return ($inboundId !== null && !$resumed)
                ? $response->withCookie($this->expiredCookie())
                : $response;
        }

        return $this->attachSessionCookie($response, $inboundId);
    }

    /**
     * Save the session when it was resumed or modified, otherwise discard it.
     * Returns whether it was saved.
     */
    private function close(
        bool $resumed,
    ): bool {
        if (
            !$resumed
            && !$this->session->isModified()
        ) {
            $this->session->discard();

            return false;
        }

        $this->session->save();

        return true;
    }

    private function inboundSessionId(
        Request $request,
    ): ?string {
        $value = $request->cookie($this->sessionConfig->cookieName());

        return (is_string($value) && $value !== '')
            ? $value
            : null;
    }

    /**
     * Start the session eagerly when the request carries a well-formed
     * session cookie, so a resumed session is read before the controller runs
     * and its expiry slides on every request. Without one there is nothing to
     * resume: the session is only armed, and starts on first access. A
     * request that never touches it makes no handler call at all.
     */
    private function prepare(
        ?string $inboundId,
    ): void {
        if (
            $inboundId === null
            || !$this->seedSessionId($inboundId)
        ) {
            $this->session->arm();

            return;
        }

        $this->session->start();
    }

    /**
     * Returns whether the inbound id was accepted.
     */
    private function seedSessionId(
        string $inboundId,
    ): bool {
        try {
            $this->session->setId($inboundId);
        } catch (InvalidSessionIdException) {
            // Attacker-controlled cookie value — ignore and fall through to a fresh session
            // rather than surfacing a 500 for a tampered or malformed inbound cookie.
            return false;
        }

        return true;
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
                : $this->clock->now()->getTimestamp() + $this->sessionConfig->lifetime() * self::SECONDS_PER_MINUTE,
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
            expires: $this->clock->now()->getTimestamp() - self::EXPIRED_COOKIE_OFFSET_SECONDS,
            path: $this->sessionConfig->cookiePath(),
            domain: $this->sessionConfig->cookieDomain(),
            secure: $this->sessionConfig->cookieSecure(),
            httpOnly: $this->sessionConfig->cookieHttpOnly(),
        );
    }
}
