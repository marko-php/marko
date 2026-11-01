<?php

declare(strict_types=1);

namespace Marko\Authentication\Http;

use Marko\Authentication\Contracts\IntendedUrlInterface;
use Marko\Routing\Http\Request;
use Marko\Session\Contracts\SessionInterface;

/**
 * Keeps the intended URL in the session under `auth.intended_url`.
 *
 * Only the request's path and query string are stored, never a scheme or host,
 * and leading slashes and backslashes collapse to one slash, so a request to
 * `//evil.example/` cannot turn the redirect back into an open redirect.
 */
readonly class IntendedUrl implements IntendedUrlInterface
{
    public const string SESSION_KEY = 'auth.intended_url';

    public function __construct(
        private SessionInterface $session,
    ) {}

    public function remember(
        Request $request,
    ): void {
        if (!in_array($request->method(), ['GET', 'HEAD'], true)) {
            $this->session->remove(self::SESSION_KEY);

            return;
        }

        $uri = $request->server('REQUEST_URI') ?? '/';

        $this->session->set(self::SESSION_KEY, '/' . ltrim($uri, '/\\'));
    }

    public function pull(
        string $default,
    ): string {
        $url = $this->session->get(self::SESSION_KEY);
        $this->session->remove(self::SESSION_KEY);

        return is_string($url) ? $url : $default;
    }
}
