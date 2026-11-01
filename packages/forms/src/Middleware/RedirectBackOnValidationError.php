<?php

declare(strict_types=1);

namespace Marko\Forms\Middleware;

use Marko\Forms\Config\FormsConfig;
use Marko\Forms\Exceptions\FormsException;
use Marko\Forms\Exceptions\FormsSessionUnavailableException;
use Marko\Forms\FlashedFormStateWriter;
use Marko\Routing\Http\Request;
use Marko\Routing\Http\Response;
use Marko\Routing\Middleware\MiddlewareInterface;
use Marko\Session\Contracts\SessionInterface;
use Marko\Session\Exceptions\InvalidFlashDataException;
use Marko\Validation\Exceptions\ValidationException;

/**
 * Turns a ValidationException into the server-rendered form round trip: flash
 * the errors and the submitted input, then redirect back (303) so the form
 * shows them.
 *
 * Requests that want JSON are left alone: the exception is rethrown, so the
 * routing pipeline renders its 422 response. The redirect goes to the Referer
 * only when it has the request's origin; otherwise to forms.fallback_redirect.
 *
 * Opt in per route or group with #[Middleware(RedirectBackOnValidationError::class)].
 * It must run inside SessionMiddleware.
 */
readonly class RedirectBackOnValidationError implements MiddlewareInterface
{
    private const array DEFAULT_PORTS = [80, 443];

    public function __construct(
        private SessionInterface $session,
        private FlashedFormStateWriter $writer,
        private FormsConfig $config,
    ) {}

    /**
     * @throws FormsSessionUnavailableException|FormsException|InvalidFlashDataException|ValidationException
     */
    public function handle(
        Request $request,
        callable $next,
    ): Response {
        if (!$this->session->isAvailable()) {
            throw FormsSessionUnavailableException::forRequest($request->method(), $request->path());
        }

        try {
            return $next($request);
        } catch (ValidationException $exception) {
            if ($request->wantsJson()) {
                throw $exception;
            }

            $this->writer->flash($exception->errors(), $request->post());

            return Response::redirect($this->redirectTarget($request), 303);
        }
    }

    /**
     * The path and query of a same-origin Referer, else the configured fallback.
     *
     * @throws FormsException
     */
    private function redirectTarget(
        Request $request,
    ): string {
        $referer = parse_url($request->header('Referer') ?? '');
        $requestHost = $request->server('HTTP_HOST');

        if (
            $referer === false
            || $requestHost === null
            || !isset($referer['host'])
            || !in_array(strtolower($referer['scheme'] ?? ''), ['http', 'https'], true)
            || $this->authority($referer['host'], $referer['port'] ?? null) !== $this->requestAuthority($requestHost)
        ) {
            return $this->config->fallbackRedirect();
        }

        // Collapse leading slashes: '//evil.example' in the path would be a protocol-relative URL
        $path = '/' . ltrim($referer['path'] ?? '', '/\\');

        return isset($referer['query']) ? "$path?{$referer['query']}" : $path;
    }

    private function requestAuthority(
        string $hostHeader,
    ): string {
        $parsed = parse_url("http://$hostHeader");

        return $this->authority($parsed['host'] ?? '', $parsed['port'] ?? null);
    }

    /**
     * Host and port, lowercased, with the default HTTP/HTTPS ports left out. The
     * scheme is not compared: behind a TLS-terminating proxy the application
     * often sees http while the browser's Referer says https.
     */
    private function authority(
        string $host,
        ?int $port,
    ): string {
        $host = strtolower($host);

        return $port === null || in_array($port, self::DEFAULT_PORTS, true) ? $host : "$host:$port";
    }
}
