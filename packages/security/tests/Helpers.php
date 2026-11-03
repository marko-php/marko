<?php

declare(strict_types=1);

namespace Marko\Security\Tests;

use Marko\Routing\Http\Cookie;
use Marko\Routing\Http\Response;
use Marko\Security\Config\SecurityConfig;
use Marko\Security\Contracts\CsrfTokenManagerInterface;
use Marko\Security\Middleware\CsrfMiddleware;
use Marko\Session\Config\SessionConfig;
use Marko\Session\Contracts\SessionInterface;
use Marko\Testing\Fake\FakeConfigRepository;
use Marko\Testing\Fake\FakeSession;

/**
 * A `Response` subclass carrying extra state, used to prove that middleware
 * decorates the response returned by `$next()` instead of rebuilding a base
 * `Response` and discarding subclass identity (loaded via composer
 * autoload-dev.files).
 */
class TaggedResponse extends Response
{
    /**
     * @param array<string, string> $headers
     */
    public function __construct(
        public readonly string $tag,
        string $body = '',
        int $statusCode = 200,
        array $headers = [],
    ) {
        parent::__construct($body, $statusCode, $headers);
    }
}

/**
 * A `Response` subclass carrying a simulated streamed payload, used to prove
 * that streaming state survives middleware that used to rebuild a base
 * `Response`.
 */
class StreamingLikeResponse extends Response
{
    /**
     * @param list<string> $chunks
     * @param array<string, string> $headers
     */
    public function __construct(
        private readonly array $chunks,
        int $statusCode = 200,
        array $headers = [],
    ) {
        parent::__construct('', $statusCode, $headers);
    }

    /**
     * @return list<string>
     */
    public function chunks(): array
    {
        return $this->chunks;
    }
}

final class Helpers
{
    /**
     * @param array<string, string> $headers
     */
    public static function createTaggedResponse(
        string $tag = 'tagged',
        string $body = '',
        int $statusCode = 200,
        array $headers = [],
    ): TaggedResponse {
        return new TaggedResponse($tag, $body, $statusCode, $headers);
    }

    /**
     * @param list<string> $chunks
     * @param array<string, string> $headers
     */
    public static function createStreamingLikeResponse(
        array $chunks = ['event: message', 'data: hello'],
        int $statusCode = 200,
        array $headers = [],
    ): StreamingLikeResponse {
        return new StreamingLikeResponse($chunks, $statusCode, $headers);
    }

    public const string SESSION_COOKIE = 'marko_session';

    public const string SESSION_ID = 'resumed-session-id';

    /**
     * A started session with id SESSION_ID, as SessionMiddleware leaves it
     * for a request carrying that session cookie.
     */
    public static function createResumedSession(): FakeSession
    {
        $session = new FakeSession();
        $session->setId(self::SESSION_ID);
        $session->start();

        return $session;
    }

    /**
     * Cookies of a request that resumes createResumedSession().
     *
     * @return array<string, string>
     */
    public static function resumedSessionCookies(): array
    {
        return [self::SESSION_COOKIE => self::SESSION_ID];
    }

    /**
     * A CsrfMiddleware over a started FakeSession (or the given session) and
     * session cookie config that defaults to the shipped session.php values.
     *
     * @param array<string, mixed> $sessionCookieConfig
     * @param class-string<CsrfMiddleware> $middlewareClass
     */
    public static function createCsrfMiddleware(
        CsrfTokenManagerInterface $tokenManager,
        ?SessionInterface $session = null,
        array $sessionCookieConfig = [],
        string $middlewareClass = CsrfMiddleware::class,
    ): CsrfMiddleware {
        return new $middlewareClass(
            tokenManager: $tokenManager,
            session: $session ?? self::createResumedSession(),
            sessionConfig: new SessionConfig(new FakeConfigRepository(array_merge([
                'session.cookie.name' => self::SESSION_COOKIE,
                'session.cookie.path' => '/',
                'session.cookie.domain' => '',
                'session.cookie.secure' => true,
            ], $sessionCookieConfig))),
        );
    }

    public static function findXsrfCookie(
        Response $response,
    ): ?Cookie {
        foreach ($response->cookies() as $cookie) {
            if ($cookie->name() === CsrfMiddleware::COOKIE_NAME) {
                return $cookie;
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $configData
     */
    public static function createSecurityConfig(array $configData = []): SecurityConfig
    {
        return new SecurityConfig(new FakeConfigRepository($configData));
    }

    /**
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    public static function defaultHeadersConfig(array $overrides = []): array
    {
        return array_merge([
            'security.headers.x_content_type_options' => 'nosniff',
            'security.headers.x_frame_options' => 'SAMEORIGIN',
            'security.headers.x_xss_protection' => '0',
            'security.headers.strict_transport_security' => 'max-age=31536000; includeSubDomains',
            'security.headers.referrer_policy' => 'strict-origin-when-cross-origin',
            'security.headers.content_security_policy' => "default-src 'self'",
        ], $overrides);
    }
}
