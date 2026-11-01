<?php

declare(strict_types=1);

namespace Marko\Routing\Middleware;

use Marko\Config\Exceptions\ConfigNotFoundException;
use Marko\Routing\Exceptions\InvalidSignatureException;
use Marko\Routing\Exceptions\UrlSigningException;
use Marko\Routing\Http\Request;
use Marko\Routing\Http\Response;
use Marko\Routing\Signing\UrlSignerInterface;

/**
 * Only lets requests with a valid, unexpired URL signature through.
 *
 * It throws InvalidSignatureException, which the routing pipeline renders as
 * a 403. Query parameters to ignore while validating come from
 * `routing.signing.ignored_parameters`.
 */
readonly class ValidSignatureMiddleware implements MiddlewareInterface
{
    public function __construct(
        private UrlSignerInterface $urlSigner,
    ) {}

    /**
     * @throws InvalidSignatureException|UrlSigningException|ConfigNotFoundException
     */
    public function handle(
        Request $request,
        callable $next,
    ): Response {
        $this->urlSigner->assertValidSignature($request);

        return $next($request);
    }
}
