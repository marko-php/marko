<?php

declare(strict_types=1);

namespace Marko\Authentication\Contracts;

use Marko\Routing\Http\Request;

/**
 * Remembers where a user was going before a redirect (to a login or password
 * confirmation page) so the app can send them back there afterwards.
 */
interface IntendedUrlInterface
{
    /**
     * Remember the request's path and query string as the intended URL.
     *
     * Only GET and HEAD requests are remembered, since a redirect can only revisit
     * a URL with GET. Any other request forgets an earlier intended URL, so pull()
     * returns its default rather than a stale destination.
     */
    public function remember(
        Request $request,
    ): void;

    /**
     * Return the intended URL and forget it, or $default when none is remembered.
     */
    public function pull(
        string $default,
    ): string;
}
