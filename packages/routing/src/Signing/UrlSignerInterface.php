<?php

declare(strict_types=1);

namespace Marko\Routing\Signing;

use DateTimeImmutable;
use Marko\Config\Exceptions\ConfigNotFoundException;
use Marko\Routing\Exceptions\InvalidSignatureException;
use Marko\Routing\Exceptions\UrlGenerationException;
use Marko\Routing\Exceptions\UrlSigningException;
use Marko\Routing\Http\Request;

/**
 * Signs URLs so they cannot be tampered with, optionally until an expiry time.
 *
 * A signed URL carries a `signature` query parameter (and `expires`, a Unix
 * timestamp, when it expires). The signature covers the scheme, host, path
 * and the canonicalised query string, so changing any of them, or moving the
 * link to another host, invalidates it.
 */
interface UrlSignerInterface
{
    /**
     * Build the URL for a named route and sign it.
     *
     * A relative URL ($absolute = false) is signed for the host of the
     * configured base URL (`routing.url`), so it only validates there.
     *
     * @param array<string, mixed> $parameters Same as UrlGeneratorInterface::route()
     * @throws UrlGenerationException|UrlSigningException|ConfigNotFoundException
     */
    public function signedRoute(
        string $name,
        array $parameters = [],
        ?DateTimeImmutable $expiresAt = null,
        bool $absolute = true,
    ): string;

    /**
     * Sign an existing URL. A relative URL is signed for the host of `routing.url`.
     *
     * @throws UrlSigningException|ConfigNotFoundException When the key is not configured, or the URL already carries `signature`/`expires` or a fragment
     */
    public function sign(
        string $url,
        ?DateTimeImmutable $expiresAt = null,
    ): string;

    /**
     * Whether the request URL carries a valid, unexpired signature.
     *
     * @throws UrlSigningException|ConfigNotFoundException When the signing keys are not configured
     */
    public function hasValidSignature(
        Request $request,
    ): bool;

    /**
     * @throws InvalidSignatureException|UrlSigningException|ConfigNotFoundException
     */
    public function assertValidSignature(
        Request $request,
    ): void;
}
