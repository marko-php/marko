<?php

declare(strict_types=1);

namespace Marko\Testing\Http;

use Closure;
use finfo;
use JsonException;
use Marko\Authentication\AuthenticatableInterface;
use Marko\Authentication\AuthManager;
use Marko\Authentication\Config\AuthConfig;
use Marko\Authentication\Contracts\GuardInterface;
use Marko\Clock\SystemClock;
use Marko\Config\Exceptions\ConfigNotFoundException;
use Marko\Core\Application;
use Marko\Core\Contracts\ResettableInterface;
use Marko\Core\Exceptions\BindingConflictException;
use Marko\Core\Exceptions\BindingException;
use Marko\Core\Exceptions\CircularDependencyException;
use Marko\Core\Exceptions\CommandException;
use Marko\Core\Exceptions\DiscoveryCacheException;
use Marko\Core\Exceptions\EventException;
use Marko\Core\Exceptions\ModuleException;
use Marko\Core\Exceptions\PluginException;
use Marko\Core\Exceptions\PreferenceConflictException;
use Marko\Core\RequestStateResetter;
use Marko\Routing\Exceptions\RouteConflictException;
use Marko\Routing\Exceptions\RouteException;
use Marko\Routing\Http\Cookie;
use Marko\Routing\Http\Request;
use Marko\Routing\Http\Response;
use Marko\Routing\Http\UploadedFile;
use Marko\Testing\Exceptions\AssertionFailedException;
use Marko\Testing\Exceptions\TestClientException;
use Marko\Testing\Fake\FakeGuard;
use Psr\Clock\ClockInterface;
use Psr\Container\ContainerExceptionInterface;
use ReflectionException;
use RuntimeException;

/**
 * Sends requests through a booted Marko application in process, the way a
 * browser or API client would: the real router, global and route middleware,
 * and controllers all run. No web server, no superglobals.
 *
 * The application boots once per client, and one client serves many requests.
 * Before each request, every resolved ResettableInterface service is reset
 * (the same {@see RequestStateResetter} the RoadRunner worker uses), so
 * request-scoped state such as the session never leaks between requests.
 *
 * The client is stateful, like a browser: headers set with withHeaders(),
 * server variables, the cookie jar and actingAs() apply to every later request
 * on the same client. withFile() applies to the next request only.
 *
 * The cookie jar scopes cookies like a browser (RFC 6265): a request carries
 * only the cookies whose path and domain match it, and Secure cookies only
 * over HTTPS. Cookies expire on the application clock (Max-Age before Expires),
 * a cross-site request (an Origin from another site, or Sec-Fetch-Site: cross-site)
 * withholds SameSite=Strict cookies and sends Lax ones on GET only, and a cookie
 * scoped to a public suffix such as `co.uk` is ignored.
 * A relative path such as `/dashboard` goes to `https://localhost`;
 * a full URL sets the scheme and host (`http://shop.test/cart` is plain HTTP).
 *
 * An exception thrown by a controller or middleware propagates out of the call,
 * so the test shows the real stack trace. HTTP exceptions
 * (HttpExceptionInterface) are already rendered into a response by the router
 * pipeline, exactly as in production.
 */
class TestClient
{
    private const string MULTIPART_BOUNDARY = 'MarkoTestClientBoundary';

    private readonly RequestStateResetter $resetter;

    /** @var array<string, string> Server keys (HTTP_*, CONTENT_TYPE, ...) => value */
    private array $headers = [];

    /** @var array<string, string> */
    private array $serverVariables = [];

    /** @var array<string, JarCookie> Keyed by name, domain and path, in creation order */
    private array $cookies = [];

    /** @var array<string, UploadedFile|array<mixed>> Keyed like the form fields, nested as PHP nests $_FILES */
    private array $files = [];

    /** @var list<ResettableInterface> */
    private array $preserved = [];

    private readonly SessionProbe $sessionProbe;

    private int $requestCount = 0;

    private ?Request $lastRequest = null;

    public function __construct(
        private readonly Application $application,
    ) {
        $this->resetter = new RequestStateResetter($application->container);
        $this->sessionProbe = new SessionProbe($this);
    }

    /**
     * Boot the application at $basePath (the project root holding vendor/, app/ and config/).
     *
     * @throws ModuleException|CircularDependencyException|BindingConflictException|BindingException|PluginException|PreferenceConflictException|EventException|ContainerExceptionInterface|RouteException|RouteConflictException|CommandException|ReflectionException|RuntimeException|DiscoveryCacheException
     */
    public static function boot(
        string $basePath,
    ): self {
        return new self(Application::boot($basePath));
    }

    /**
     * Send requests through an application that is already booted.
     */
    public static function forApplication(
        Application $application,
    ): self {
        return new self($application);
    }

    public function application(): Application
    {
        return $this->application;
    }

    /**
     * Send these headers with every later request. Per-call headers win for that call.
     *
     * @param array<string, string> $headers
     */
    public function withHeaders(
        array $headers,
    ): static {
        foreach ($headers as $name => $value) {
            $this->headers[self::headerServerKey($name)] = $value;
        }

        return $this;
    }

    public function withHeader(
        string $name,
        string $value,
    ): static {
        return $this->withHeaders([$name => $value]);
    }

    /**
     * Set $_SERVER-style variables (e.g. REMOTE_ADDR, HTTPS) for every later request.
     * They are applied last, so they override what the client computes.
     *
     * @param array<string, string> $variables
     */
    public function withServerVariables(
        array $variables,
    ): static {
        $this->serverVariables = [...$this->serverVariables, ...$variables];

        return $this;
    }

    /**
     * Put a cookie in the jar. With the defaults it is sent with every later request,
     * to any host. A $path limits it to requests on or below that path, a $domain to
     * that domain and its subdomains, and $secure to HTTPS requests.
     */
    public function withCookie(
        string $name,
        string $value,
        string $path = '/',
        ?string $domain = null,
        bool $secure = false,
    ): static {
        $domain = $domain === null ? null : self::normalizeDomain($domain);
        $this->cookies[self::cookieKey($name, $domain, $path)] = new JarCookie(
            name: $name,
            value: $value,
            domain: $domain,
            path: $path,
            secure: $secure,
        );

        return $this;
    }

    /**
     * Put several cookies in the jar, each sent with every later request.
     *
     * @param array<string, string> $cookies
     */
    public function withCookies(
        array $cookies,
    ): static {
        foreach ($cookies as $name => $value) {
            $this->withCookie($name, $value);
        }

        return $this;
    }

    /**
     * Leave these services alone when the client resets request-scoped state
     * before each request. RefreshDatabase passes the database connection here,
     * because resetting it would roll back the test transaction.
     */
    public function withoutResetting(
        ResettableInterface ...$services,
    ): static {
        foreach ($services as $service) {
            if (!in_array($service, $this->preserved, true)) {
                $this->preserved[] = $service;
            }
        }

        return $this;
    }

    /**
     * Empty the cookie jar, like a fresh browser.
     */
    public function withoutCookies(): static
    {
        $this->cookies = [];

        return $this;
    }

    /**
     * The cookies in the jar whose path is `/`, as name => value: the ones a request to `/`
     * carries, whatever the host or scheme. Use cookieJar() for path-scoped cookies.
     *
     * @return array<string, string>
     */
    public function cookies(): array
    {
        $this->evictExpiredCookies();
        $cookies = [];

        foreach ($this->cookies as $cookie) {
            if ($cookie->path === '/') {
                $cookies[$cookie->name] ??= $cookie->value;
            }
        }

        return $cookies;
    }

    /**
     * Every unexpired cookie in the jar with its domain, path, Secure flag, expiry and SameSite,
     * in creation order.
     *
     * @return list<JarCookie>
     * @throws ContainerExceptionInterface
     */
    public function cookieJar(): array
    {
        $this->evictExpiredCookies();

        return array_values($this->cookies);
    }

    /**
     * Reset request-scoped state the way the client does before each request: every
     * resolved ResettableInterface service except those passed to withoutResetting().
     *
     * @throws ContainerExceptionInterface
     */
    public function resetRequestState(): void
    {
        $this->resetter->reset(...$this->preserved);
    }

    /**
     * How many requests the client has sent.
     */
    public function requestCount(): int
    {
        return $this->requestCount;
    }

    /**
     * The value of the jar's cookie $name that a repeat of the last request would carry
     * (same host, path and scheme, same-site); before any request, what a request to
     * https://localhost/ would carry. Null when the jar has no such cookie.
     *
     * @throws ContainerExceptionInterface
     */
    public function lastRequestCookie(
        string $name,
    ): ?string {
        $request = $this->lastRequest;
        $server = $request === null ? ['HTTP_HOST' => 'localhost', 'HTTPS' => 'on'] : [
            'HTTP_HOST' => $request->server('HTTP_HOST') ?? 'localhost',
            'HTTPS' => $request->server('HTTPS') ?? '',
        ];

        $this->evictExpiredCookies();
        $cookie = array_find(
            $this->cookiesFor(
                self::requestHost($server),
                $request?->path() ?? '/',
                self::isSecureRequest($server),
                null,
            ),
            static fn (JarCookie $cookie): bool => $cookie->name === $name,
        );

        return $cookie?->value;
    }

    /**
     * Asserts the guard named $guard (the default guard when null) would see a logged-in
     * user on the next request. The real guard reads the session the client's session
     * cookie points to; after actingAs() the FakeGuard's user is reported. A stateless
     * guard throws TestClientException: assert on a response instead.
     *
     * @throws AssertionFailedException|TestClientException|ContainerExceptionInterface|ConfigNotFoundException
     */
    public function assertAuthenticated(
        ?string $guard = null,
    ): static {
        $name = $this->guardName($guard);

        if ($this->sessionProbe->user($guard) === null) {
            throw AssertionFailedException::guardAssertion(
                "Expected a user to be authenticated on guard [$name] but there is none.",
                $name,
            );
        }

        AssertionCounter::passed();

        return $this;
    }

    /**
     * Asserts the guard named $guard (the default guard when null) would see no user on the next request.
     *
     * @throws AssertionFailedException|TestClientException|ContainerExceptionInterface|ConfigNotFoundException
     */
    public function assertGuest(
        ?string $guard = null,
    ): static {
        $name = $this->guardName($guard);
        $user = $this->sessionProbe->user($guard);

        if ($user !== null) {
            throw AssertionFailedException::guardAssertion(
                "Expected a guest on guard [$name] but user [{$user->getAuthIdentifier()}] is authenticated.",
                $name,
            );
        }

        AssertionCounter::passed();

        return $this;
    }

    /**
     * Asserts the guard named $guard (the default guard when null) would see $user on the next
     * request, compared by getAuthIdentifier(), not by object identity.
     *
     * @throws AssertionFailedException|TestClientException|ContainerExceptionInterface|ConfigNotFoundException
     */
    public function assertAuthenticatedAs(
        AuthenticatableInterface $user,
        ?string $guard = null,
    ): static {
        $name = $this->guardName($guard);
        $expected = $user->getAuthIdentifier();
        $actual = $this->sessionProbe->user($guard);

        if ($actual === null) {
            throw AssertionFailedException::guardAssertion(
                "Expected user [$expected] to be authenticated on guard [$name] but there is none.",
                $name,
            );
        }

        if ($actual->getAuthIdentifier() !== $expected) {
            throw AssertionFailedException::guardAssertion(
                "Expected user [$expected] to be authenticated on guard [$name] but user [{$actual->getAuthIdentifier()}] is.",
                $name,
            );
        }

        AssertionCounter::passed();

        return $this;
    }

    /**
     * Upload a file with the next request, as the form field $field. The file is copied
     * first, so the controller can move the upload without touching the original.
     *
     * $field uses HTML form notation, and the controller sees the same nested shape
     * PHP builds from $_FILES: `photos[]` appends one more file to the `photos` list,
     * and `documents[passport]` nests the file under `documents`. A plain field such
     * as `avatar` holds one file; uploading to it twice throws.
     *
     * @throws TestClientException
     */
    public function withFile(
        string $field,
        string $path,
        ?string $clientFilename = null,
        ?string $clientMediaType = null,
    ): static {
        $segments = self::uploadFieldSegments($field);

        if (!is_file($path) || !is_readable($path)) {
            throw TestClientException::unreadableUpload($path);
        }

        $this->files = self::placeUpload($this->files, $segments, $field, static function () use (
            $path,
            $clientFilename,
            $clientMediaType,
        ): UploadedFile {
            $copy = (string) tempnam(sys_get_temp_dir(), 'marko-test-upload-');
            copy($path, $copy);

            return new UploadedFile(
                tempPath: $copy,
                clientFilename: $clientFilename ?? basename($path),
                clientMediaType: $clientMediaType ?? (string) new finfo(FILEINFO_MIME_TYPE)->file($copy),
                size: (int) filesize($copy),
            );
        });

        return $this;
    }

    /**
     * Upload several files with the next request as the list field $field (`photos`
     * or `photos[]`), in order: the same as one withFile('photos[]', ...) per path.
     *
     * @param list<string> $paths
     * @throws TestClientException
     */
    public function withFiles(
        string $field,
        array $paths,
    ): static {
        $listField = str_ends_with($field, '[]') ? $field : $field . '[]';

        foreach ($paths as $path) {
            $this->withFile($listField, $path);
        }

        return $this;
    }

    /**
     * Authenticate as $user for every later request, without a login request and without
     * writing to the session: a FakeGuard holding the user replaces the guard named $guard
     * (the default guard when null) in the application's AuthManager. For the default
     * guard, the container's GuardInterface instance is replaced too.
     *
     * Needs the marko/authentication module loaded by the application; without it, resolving
     * AuthManager fails with the container's binding error.
     *
     * @throws ContainerExceptionInterface|ConfigNotFoundException
     */
    public function actingAs(
        AuthenticatableInterface $user,
        ?string $guard = null,
    ): static {
        $container = $this->application->container;
        $authManager = $container->get(AuthManager::class);
        $defaultGuard = $this->guardName(null);
        $name = $guard ?? $defaultGuard;

        $fakeGuard = new FakeGuard(name: $name);
        $fakeGuard->setUser($user);
        $authManager->useGuard($name, $fakeGuard);

        if ($name === $defaultGuard) {
            $container->instance(GuardInterface::class, $fakeGuard);
        }

        return $this;
    }

    /**
     * @param array<string, mixed> $query
     * @param array<string, string> $headers
     * @throws ContainerExceptionInterface|ReflectionException|JsonException|TestClientException
     */
    public function get(
        string $uri,
        array $query = [],
        array $headers = [],
    ): TestResponse {
        return $this->call('GET', $uri, $query, $headers);
    }

    /**
     * @param array<string, mixed> $data form fields, sent form-encoded (multipart with withFile())
     * @param array<string, string> $headers
     * @throws ContainerExceptionInterface|ReflectionException|JsonException|TestClientException
     */
    public function post(
        string $uri,
        array $data = [],
        array $headers = [],
    ): TestResponse {
        return $this->call('POST', $uri, $data, $headers);
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, string> $headers
     * @throws ContainerExceptionInterface|ReflectionException|JsonException|TestClientException
     */
    public function put(
        string $uri,
        array $data = [],
        array $headers = [],
    ): TestResponse {
        return $this->call('PUT', $uri, $data, $headers);
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, string> $headers
     * @throws ContainerExceptionInterface|ReflectionException|JsonException|TestClientException
     */
    public function patch(
        string $uri,
        array $data = [],
        array $headers = [],
    ): TestResponse {
        return $this->call('PATCH', $uri, $data, $headers);
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, string> $headers
     * @throws ContainerExceptionInterface|ReflectionException|JsonException|TestClientException
     */
    public function delete(
        string $uri,
        array $data = [],
        array $headers = [],
    ): TestResponse {
        return $this->call('DELETE', $uri, $data, $headers);
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, string> $headers
     * @throws ContainerExceptionInterface|ReflectionException|JsonException|TestClientException
     */
    public function options(
        string $uri,
        array $data = [],
        array $headers = [],
    ): TestResponse {
        return $this->call('OPTIONS', $uri, $data, $headers);
    }

    /**
     * @param array<string, mixed> $query
     * @param array<string, string> $headers
     * @throws ContainerExceptionInterface|ReflectionException|JsonException|TestClientException
     */
    public function head(
        string $uri,
        array $query = [],
        array $headers = [],
    ): TestResponse {
        return $this->call('HEAD', $uri, $query, $headers);
    }

    /**
     * A GET with `Accept: application/json`. GET requests carry no body, so $query is sent as the query string.
     *
     * @param array<string, mixed> $query
     * @param array<string, string> $headers
     * @throws ContainerExceptionInterface|ReflectionException|JsonException|TestClientException
     */
    public function getJson(
        string $uri,
        array $query = [],
        array $headers = [],
    ): TestResponse {
        return $this->call('GET', $uri, $query, ['Accept' => 'application/json', ...$headers]);
    }

    /**
     * @param array<mixed> $data sent as the JSON body
     * @param array<string, string> $headers
     * @throws ContainerExceptionInterface|ReflectionException|JsonException|TestClientException
     */
    public function postJson(
        string $uri,
        array $data = [],
        array $headers = [],
    ): TestResponse {
        return $this->json('POST', $uri, $data, $headers);
    }

    /**
     * @param array<mixed> $data sent as the JSON body
     * @param array<string, string> $headers
     * @throws ContainerExceptionInterface|ReflectionException|JsonException|TestClientException
     */
    public function putJson(
        string $uri,
        array $data = [],
        array $headers = [],
    ): TestResponse {
        return $this->json('PUT', $uri, $data, $headers);
    }

    /**
     * @param array<mixed> $data sent as the JSON body
     * @param array<string, string> $headers
     * @throws ContainerExceptionInterface|ReflectionException|JsonException|TestClientException
     */
    public function patchJson(
        string $uri,
        array $data = [],
        array $headers = [],
    ): TestResponse {
        return $this->json('PATCH', $uri, $data, $headers);
    }

    /**
     * @param array<mixed> $data sent as the JSON body
     * @param array<string, string> $headers
     * @throws ContainerExceptionInterface|ReflectionException|JsonException|TestClientException
     */
    public function deleteJson(
        string $uri,
        array $data = [],
        array $headers = [],
    ): TestResponse {
        return $this->json('DELETE', $uri, $data, $headers);
    }

    /**
     * Send $data as a JSON body with JSON Content-Type and Accept headers.
     *
     * @param array<mixed> $data
     * @param array<string, string> $headers
     * @throws ContainerExceptionInterface|ReflectionException|JsonException|TestClientException
     */
    public function json(
        string $method,
        string $uri,
        array $data = [],
        array $headers = [],
    ): TestResponse {
        return $this->call(
            $method,
            $uri,
            headers: [...self::jsonHeaders(), ...$headers],
            body: json_encode($data, JSON_THROW_ON_ERROR),
        );
    }

    /**
     * Send any request. For GET and HEAD, $data is the query string. For other methods,
     * $data is form data (Request::post()), unless a raw $body is given instead.
     *
     * @param array<string, mixed> $data
     * @param array<string, string> $headers
     * @throws ContainerExceptionInterface|ReflectionException|JsonException|TestClientException
     */
    public function call(
        string $method,
        string $uri,
        array $data = [],
        array $headers = [],
        ?string $body = null,
    ): TestResponse {
        $method = strtoupper($method);
        $request = $this->buildRequest($method, $uri, $data, $headers, $body);
        $files = $this->files;
        $this->files = [];
        $this->requestCount++;
        $this->lastRequest = $request;

        try {
            // Reset before, not after, like the RoadRunner worker: state left by a
            // request that threw must not reach the next one.
            $this->resetRequestState();
            $response = $this->application->router->handle($request);
        } finally {
            array_walk_recursive($files, static function (mixed $file): void {
                if ($file instanceof UploadedFile && is_file($file->tempPath())) {
                    unlink($file->tempPath());
                }
            });
        }

        $this->storeCookies($response, $request);

        return new TestResponse($response, $this->sessionProbe, $this->requestCount);
    }

    /**
     * $guard, or the configured default guard's name when null.
     *
     * @throws ContainerExceptionInterface|ConfigNotFoundException
     */
    private function guardName(
        ?string $guard,
    ): string {
        return $guard ?? $this->application->container->get(AuthConfig::class)->defaultGuard();
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, string> $headers
     * @throws TestClientException
     */
    private function buildRequest(
        string $method,
        string $uri,
        array $data,
        array $headers,
        ?string $body,
    ): Request {
        $parts = parse_url($uri);
        $parts = $parts === false ? [] : $parts;
        $path = ($parts['path'] ?? '') === '' ? '/' : $parts['path'];
        parse_str($parts['query'] ?? '', $query);
        $host = $parts['host'] ?? 'localhost';
        // A relative path goes to https://localhost, as a production app is served over
        // HTTPS: Secure cookies such as the default session cookie round-trip. Only an
        // explicit http:// URL is plain HTTP.
        $secure = strtolower($parts['scheme'] ?? 'https') === 'https';

        $post = [];
        $files = [];
        $contentType = null;
        $bodyless = $method === 'GET' || $method === 'HEAD';

        if ($bodyless) {
            $query = [...$query, ...$data];
        } elseif ($body !== null && $data !== []) {
            throw TestClientException::bodyAndData($method, $uri);
        } elseif ($body === null) {
            $post = $data;
            $files = $this->files;

            if ($files !== []) {
                $contentType = 'multipart/form-data; boundary=' . self::MULTIPART_BOUNDARY;
            } elseif ($data !== []) {
                $contentType = 'application/x-www-form-urlencoded';
                $body = http_build_query($data);
            }
        }

        $body ??= '';
        $queryString = http_build_query($query);

        $server = [
            'SERVER_PROTOCOL' => 'HTTP/1.1',
            'SERVER_NAME' => $host,
            'SERVER_PORT' => (string) ($parts['port'] ?? ($secure ? 443 : 80)),
            'HTTP_HOST' => isset($parts['port']) ? "$host:{$parts['port']}" : $host,
            'REMOTE_ADDR' => '127.0.0.1',
            'REQUEST_TIME' => $this->now(),
            'REQUEST_METHOD' => $method,
            'REQUEST_URI' => $queryString === '' ? $path : "$path?$queryString",
            'QUERY_STRING' => $queryString,
        ];

        if ($secure) {
            $server['HTTPS'] = 'on';
        }

        if ($contentType !== null) {
            $server['CONTENT_TYPE'] = $contentType;
        }

        if ($body !== '') {
            $server['CONTENT_LENGTH'] = (string) strlen($body);
        }

        foreach ($headers as $name => $value) {
            $server[self::headerServerKey($name)] = $value;
        }

        $server = [...$server, ...$this->headersNotOverridden($headers), ...$this->serverVariables];
        $requestHost = self::requestHost($server);
        $this->evictExpiredCookies();
        $cookies = $this->cookiesFor(
            $requestHost,
            $path,
            self::isSecureRequest($server),
            self::isCrossSiteRequest($server, $requestHost) ? $method : null,
        );
        $requestCookies = [];

        foreach ($cookies as $cookie) {
            // Like PHP's $_COOKIE, the first (most specific) cookie of a name wins.
            $requestCookies[$cookie->name] ??= $cookie->value;
        }

        if ($cookies !== [] && !array_key_exists('HTTP_COOKIE', $server)) {
            $server['HTTP_COOKIE'] = implode('; ', array_map(
                static fn (JarCookie $cookie): string => $cookie->name . '=' . rawurlencode($cookie->value),
                $cookies,
            ));
        }

        return new Request(
            server: $server,
            query: $query,
            post: $post,
            body: $body,
            cookies: $requestCookies,
            files: $files,
        );
    }

    /**
     * The jar's cookies a request to $host and $path carries: longest path first,
     * then oldest first, as RFC 6265 §5.4 orders the Cookie header. $crossSiteMethod
     * is the request method when the request is cross-site, which filters by SameSite.
     *
     * @return list<JarCookie>
     */
    private function cookiesFor(
        string $host,
        string $path,
        bool $secure,
        ?string $crossSiteMethod,
    ): array {
        $cookies = array_values(array_filter(
            $this->cookies,
            static fn (JarCookie $cookie): bool => $cookie->matches($host, $path, $secure)
                && ($crossSiteMethod === null || $cookie->allowsCrossSite($crossSiteMethod)),
        ));

        usort($cookies, static fn (JarCookie $a, JarCookie $b): int => strlen($b->path) <=> strlen($a->path));

        return $cookies;
    }

    /**
     * The request's host, from the final Host header (port removed, lowercased).
     *
     * @param array<string, mixed> $server
     */
    private static function requestHost(
        array $server,
    ): string {
        $host = (string) ($server['HTTP_HOST'] ?? $server['SERVER_NAME'] ?? 'localhost');

        if (str_starts_with($host, '[')) {
            return strtolower(substr($host, 0, (int) strpos($host, ']') + 1));
        }

        return strtolower(explode(':', $host, 2)[0]);
    }

    /**
     * Whether a browser would treat the request as cross-site, for SameSite. A
     * `Sec-Fetch-Site` header decides when sent; otherwise an `Origin` header whose site
     * (registrable domain) differs from $host's, or the opaque `null` origin, makes it
     * cross-site. Without either header the request is same-site, like a navigation
     * within the app.
     *
     * @param array<string, mixed> $server
     */
    private static function isCrossSiteRequest(
        array $server,
        string $host,
    ): bool {
        if (isset($server['HTTP_SEC_FETCH_SITE'])) {
            return strtolower((string) $server['HTTP_SEC_FETCH_SITE']) === 'cross-site';
        }

        if (!isset($server['HTTP_ORIGIN'])) {
            return false;
        }

        $originHost = parse_url((string) $server['HTTP_ORIGIN'], PHP_URL_HOST);

        if (!is_string($originHost) || $originHost === '') {
            return true;
        }

        return PublicSuffixList::site(strtolower($originHost)) !== PublicSuffixList::site($host);
    }

    /**
     * @param array<string, mixed> $server
     */
    private static function isSecureRequest(
        array $server,
    ): bool {
        $https = $server['HTTPS'] ?? '';

        return $https !== '' && strtolower((string) $https) !== 'off';
    }

    /**
     * The client-wide headers that the per-call $headers do not replace.
     *
     * @param array<string, string> $headers
     * @return array<string, string>
     */
    private function headersNotOverridden(
        array $headers,
    ): array {
        $overridden = array_map(self::headerServerKey(...), array_keys($headers));

        return array_diff_key($this->headers, array_flip($overridden));
    }

    /**
     * Keep the cookies the response sets, and drop the ones it expires, like a browser
     * (RFC 6265 §5.3): a cookie without Domain belongs to the request host only, one
     * without Path gets the request's default path, and one whose Domain does not cover
     * the request host is ignored. A set or expired cookie replaces only the jar entry
     * with the same name, domain and path, plus a withCookie() entry of that name and
     * path that applies to any host.
     *
     * @throws ContainerExceptionInterface
     */
    private function storeCookies(
        Response $response,
        Request $request,
    ): void {
        $cookies = $response->cookies();

        if ($cookies === []) {
            return;
        }

        $now = $this->now();
        $host = self::requestHost(['HTTP_HOST' => $request->server('HTTP_HOST') ?? 'localhost']);

        foreach ($cookies as $cookie) {
            $domain = $cookie->domain() === null ? '' : self::normalizeDomain($cookie->domain());

            // RFC 6265 §5.3 step 5: a Domain that is a public suffix is ignored, unless it is
            // the request host itself, which makes the cookie host-only.
            if ($domain !== '' && PublicSuffixList::isPublicSuffix($domain)) {
                if ($domain !== $host) {
                    continue;
                }

                $domain = '';
            }

            $hostOnly = $domain === '';
            $domain = $hostOnly ? $host : $domain;

            if (!$hostOnly && !JarCookie::domainMatches($host, $domain)) {
                continue;
            }

            $path = (string) $cookie->path();
            $path = str_starts_with($path, '/') ? $path : JarCookie::defaultPath($request->path());
            $key = self::cookieKey($cookie->name(), $domain, $path);

            unset($this->cookies[self::cookieKey($cookie->name(), null, $path)]);

            $expiresAt = self::expiresAt($cookie, $now);

            if ($expiresAt !== null && $expiresAt <= $now) {
                unset($this->cookies[$key]);

                continue;
            }

            $this->cookies[$key] = new JarCookie(
                name: $cookie->name(),
                value: $cookie->value(),
                domain: $domain,
                path: $path,
                secure: $cookie->secure(),
                hostOnly: $hostOnly,
                expiresAt: $expiresAt,
                sameSite: $cookie->sameSite(),
            );
        }
    }

    /**
     * Drop the cookies whose expiry has passed on the application clock, as a browser does.
     *
     * @throws ContainerExceptionInterface
     */
    private function evictExpiredCookies(): void
    {
        if ($this->cookies === []) {
            return;
        }

        $now = $this->now();
        $this->cookies = array_filter(
            $this->cookies,
            static fn (JarCookie $cookie): bool => !$cookie->isExpired($now),
        );
    }

    private static function cookieKey(
        string $name,
        ?string $domain,
        string $path,
    ): string {
        return implode("\n", [$name, $domain ?? '*', $path]);
    }

    private static function normalizeDomain(
        string $domain,
    ): string {
        return strtolower(ltrim($domain, '.'));
    }

    /**
     * The application's clock when one is bound, so cookie expiry agrees with code that
     * expires cookies relative to that clock (e.g. SessionMiddleware); a SystemClock otherwise.
     *
     * @throws ContainerExceptionInterface
     */
    private function now(): int
    {
        $container = $this->application->container;

        if ($container->has(ClockInterface::class)) {
            return $container->get(ClockInterface::class)->now()->getTimestamp();
        }

        return new SystemClock()->now()->getTimestamp();
    }

    /**
     * When a cookie the response sets expires, as a Unix timestamp: Max-Age counted from $now wins
     * over Expires (RFC 6265 §5.3 step 3); null for a session cookie (no expiry, or Expires 0).
     */
    private static function expiresAt(
        Cookie $cookie,
        int $now,
    ): ?int {
        $maxAge = $cookie->maxAge();

        if ($maxAge !== null) {
            if ($maxAge <= 0) {
                return $now;
            }

            return $maxAge > PHP_INT_MAX - $now ? PHP_INT_MAX : $now + $maxAge;
        }

        $expires = $cookie->expires();

        return $expires === null || $expires === 0 ? null : $expires;
    }

    /**
     * Split a form field name into its key path: `documents[passport]` is ['documents', 'passport'],
     * and `photos[]` is ['photos', ''], where '' appends to a list.
     *
     * @return non-empty-list<string>
     * @throws TestClientException
     */
    private static function uploadFieldSegments(
        string $field,
    ): array {
        if (preg_match('/^([^\[\]]+)((?:\[[^\[\]]*])*)$/', $field, $matches) !== 1) {
            throw TestClientException::invalidUploadField($field);
        }

        preg_match_all('/\[([^\[\]]*)]/', $matches[2], $brackets);

        return [$matches[1], ...$brackets[1]];
    }

    /**
     * Put the upload $make() builds at $segments inside $files, the way PHP nests $_FILES.
     * $make runs only once the slot is known to be free, so a rejected upload leaves no copy behind.
     *
     * @param array<mixed> $files
     * @param list<string> $segments
     * @param Closure(): UploadedFile $make
     * @return array<mixed>
     * @throws TestClientException
     */
    private static function placeUpload(
        array $files,
        array $segments,
        string $field,
        Closure $make,
    ): array {
        $segment = array_shift($segments);
        $isLeaf = $segments === [];

        if ($segment === '') {
            $files[] = $isLeaf ? $make() : self::placeUpload([], $segments, $field, $make);

            return $files;
        }

        $existing = $files[$segment] ?? null;

        if ($isLeaf) {
            if ($existing instanceof UploadedFile) {
                throw TestClientException::duplicateUpload($field);
            }

            if ($existing !== null) {
                throw TestClientException::conflictingUpload($field);
            }

            $files[$segment] = $make();

            return $files;
        }

        if ($existing instanceof UploadedFile) {
            throw TestClientException::conflictingUpload($field);
        }

        $files[$segment] = self::placeUpload(is_array($existing) ? $existing : [], $segments, $field, $make);

        return $files;
    }

    private static function headerServerKey(
        string $name,
    ): string {
        $key = strtoupper(str_replace('-', '_', $name));

        return $key === 'CONTENT_TYPE' || $key === 'CONTENT_LENGTH' ? $key : 'HTTP_' . $key;
    }

    /**
     * @return array<string, string>
     */
    private static function jsonHeaders(): array
    {
        return [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ];
    }
}
