---
title: marko/routing
description: Attribute-based routing with automatic conflict detection — define routes on controller methods, not in separate files.
---

Routes live on the methods they handle. Conflicts are caught at boot time with clear error messages. Override vendor routes cleanly via [Preferences](/docs/packages/core/), or disable them explicitly with `#[DisableRoute]`.

## Installation

```bash
composer require marko/routing
```

## Configuration

`url` is the base URL used for absolute URLs from the [URL generator](#named-routes-and-url-generation). It reads `APP_URL`. `signing` holds the key for [signed URLs](#signed-urls). `file_offload` hands [file responses](#file-and-streamed-responses) to the web server; it is off unless you set it:

```php title="config/routing.php"
use Marko\Config\Env;

return [
    'url' => Env::string('APP_URL', ''),
    'signing' => [
        'key' => Env::string('URL_SIGNING_KEY', ''),                   // required to sign or validate; at least 32 bytes
        'previous_keys' => Env::list('URL_SIGNING_PREVIOUS_KEYS', []), // retired keys that still validate
        'ignored_parameters' => [],                                    // query parameters left out of the signature
    ],
    'file_offload' => [
        'header' => Env::string('ROUTING_FILE_OFFLOAD_HEADER', ''), // 'X-Sendfile', 'X-Accel-Redirect' or '' (off)
        'prefix_map' => [], // local path prefix => server path prefix
    ],
];
```

```bash title=".env"
APP_URL=https://example.com
URL_SIGNING_KEY=  # generate with: php -r "echo bin2hex(random_bytes(32));"
```

The base URL is never guessed from the request's `Host` header: that header is client-controlled, and it does not exist in CLI commands or queue jobs.

## Usage

### Defining Routes

Add route attributes to controller methods:

```php title="ProductController.php"
use Marko\Routing\Attributes\Get;
use Marko\Routing\Attributes\Post;
use Marko\Routing\Http\Response;

class ProductController
{
    #[Get('/products')]
    public function index(): Response
    {
        return new Response('Product list');
    }

    #[Get('/products/{id}')]
    public function show(
        int $id,
    ): Response {
        return new Response("Product $id");
    }

    #[Post('/products')]
    public function store(): Response
    {
        return new Response('Created', 201);
    }
}
```

### Binding Request Input

Controller method arguments are bound from the route path, a `Request` type hint, and the container (for class and interface types). Request input --- the query string and the body --- reaches a parameter only when it opts in with an attribute:

| Attribute | Reads from |
|---|---|
| `#[FromQuery]` | The query string |
| `#[FromBody]` | The JSON body for JSON requests, form data otherwise. Never the query string |
| `#[FromInput]` | The body, falling back to the query string (the same lookup as `Request::input()`) |

```php
use Marko\Routing\Attributes\FromBody;
use Marko\Routing\Attributes\FromQuery;

#[Get('/shows/{id}/export')]
public function export(
    int $id,                                  // route path
    #[FromQuery] bool $includeDeleted = false, // ?includeDeleted=1
    #[FromQuery('per_page')] int $perPage = 25, // read a differently named key
): Response {
    // ...
}

#[Post('/shows')]
public function store(#[FromBody] string $title): Response
{
    // ...
}
```

A parameter without an attribute keeps its default value even when the request carries a field of the same name, so `?includeDeleted=1` cannot switch on an option the controller never exposed. A required scalar that is neither a route parameter nor attributed is a programming error: the router throws `RouteException` naming the parameter.

Values are converted strictly to the declared type. `int` and `float` use `FILTER_VALIDATE_INT` and `FILTER_VALIDATE_FLOAT`; `bool` uses `FILTER_VALIDATE_BOOL`, so `"1"`, `"true"`, `"on"` and `"yes"` are `true` and `"0"`, `"false"`, `"off"` and `"no"` are `false`; `string` and `array` accept only strings (or JSON numbers) and arrays. A value that does not fit --- `?page=abc` for an `int`, `?name[]=x` for a `string` --- is answered with a `400` (`HttpException::badRequest()`), never a `TypeError`. Route path values are converted the same way, so `/shows/abc` for `int $id` is a `400` too. A required attributed parameter the request does not carry is a `400` via `InvalidRouteParameterException`; an optional one keeps its default.

### Available Methods

```php
#[Get('/path')]
#[Post('/path')]
#[Put('/path')]
#[Patch('/path')]
#[Delete('/path')]
#[Head('/path')]
#[Options('/path')]
```

You rarely need `#[Head]` or `#[Options]` --- the router answers both automatically (see [HEAD and OPTIONS](#head-and-options)). Declare them only when a route needs custom behaviour.

### Route Parameters

A `{name}` placeholder matches one path segment (anything except `/`). Two variants change what it matches:

| Placeholder | Matches | Example |
|---|---|---|
| `{id}` | One segment | `/shows/{id}` matches `/shows/42` and `/shows/abc` |
| `{id:\d+}` | A value matching the regex | `/shows/{id:\d+}` matches `/shows/42`, not `/shows/abc` |
| `{path*}` | The rest of the path, slashes included | `/docs/{path*}` matches `/docs/guides/routing` with `path` = `guides/routing` |

```php title="DocsController.php"
#[Get('/shows/{id:\d+}')]
public function show(int $id): Response { /* ... */ }

#[Get('/archive/{year:\d{4}}/{slug:[a-z0-9-]+}')]
public function archived(int $year, string $slug): Response { /* ... */ }

#[Get('/docs/{path*}')]
public function page(string $path): Response { /* ... */ }
```

A value that fails a constraint doesn't match the route, so the router tries the next route (and answers 404 if none matches). Constraints are PCRE patterns without delimiters. Use non-capturing groups such as `{format:(?:json|xml)}`. A catch-all needs at least one character, so `/docs/{path*}` doesn't match `/docs`; add a separate `#[Get('/docs')]` route for that.

These mistakes fail at boot with a `RouteException` that names the path:

- an invalid regex (`{id:[0-9}`)
- a capturing group in a constraint (`{id:(\d+)}`)
- a catch-all that is not the whole final segment (`/docs/{path*}/edit`, `/files/v-{path*}`)
- a parameter name that is not an identifier, or the same name used twice in one path

### Parameter Values and Filesystem Paths

The router matches the raw, still-encoded request path and URL-decodes each parameter value afterwards. That means `%2F` passes a segment pattern like `{file}` and only becomes `/` once decoded, and a web server that passes the path through unnormalised (nginx's `$request_uri`, `curl --path-as-is`) can deliver literal `../` segments. To stop those values from walking a filesystem path, a route doesn't match when a decoded value breaks these rules:

| Parameter | Rejected after decoding |
|---|---|
| `{name}` and `{name:regex}` | a `/` (including `%2F`), a value that is exactly `.` or `..`, a NUL byte |
| `{path*}` | a `.` or `..` segment (`a/../b`, `./a`, `%2E%2E/x`), a NUL byte |

A rejected value is treated like a failed constraint: the router tries the next route and answers 404 if none matches. Dots inside a value are fine (`report.pdf`, `.env`, `v1..2`, `.well-known/x`). A catch-all may contain `/` by design --- spanning segments is what it is for --- so `/assets/{path*}` still matches `/assets/css/app.css` (and `/assets/css%2Fapp.css`) with `path` = `css/app.css`. A value that needs a `/` must use a catch-all.

These checks keep values from climbing out of a directory, but they don't make a value a safe filename on their own. When a parameter becomes part of a filesystem path, join it with `SafePath::join()`, which normalises the path and refuses anything that escapes the base directory:

```php title="AssetController.php"
use Marko\Routing\File\LocalFileSource;
use Marko\Routing\Http\FileResponse;
use Marko\Routing\SafePath;

#[Get('/assets/{path*}')]
public function show(Request $request, string $path): FileResponse
{
    $file = SafePath::join('/var/www/app/public/assets', $path);

    if (!is_file($file)) {
        throw HttpException::notFound();
    }

    return FileResponse::fromSource($request, new LocalFileSource($file));
}
```

`SafePath::join($base, $relative)` drops empty and `.` segments and resolves `..` against the segments before it, so `css/../app.css` becomes `<base>/app.css`. It throws `UnsafePathException` when `$relative` is absolute, contains a NUL byte or climbs above `$base`. The exception implements `HttpExceptionInterface` and renders as a `404` with `{"message": "Not Found"}`; the rejected path is only in the exception message, for your logs. The check is lexical and never touches the filesystem, so a symlink inside the base directory can still point outside it.

### Route Precedence

Which route handles a URL never depends on registration order, module order or file layout. For each HTTP method the router tries routes in this order:

1. **Static paths** (no parameters), matched by exact lookup. `/shows/live` always beats `/shows/{id}`.
2. **Catch-all paths come last.** A route with `{path*}` is tried only after every other dynamic route.
3. **Dynamic paths with more static segments.** `/a/{x}/c` (2 static segments) beats `/a/{x}/{y}` (1).
4. **More constrained parameters.** At equal static segments, `/shows/{id:\d+}` is tried before `/shows/{slug}`, so `/shows/42` reaches the first and `/shows/the-wire` falls through to the second.
5. **Dynamic paths with a longer static prefix** (the text before the first `{`). `/api/{version}/list` beats `/{tenant}/users/list`.
6. **Registration order** breaks any remaining tie.

A trailing slash is ignored (`/shows/live/` matches `/shows/live`). `marko route:list` prints routes in this effective order.

### Named Routes and URL Generation

Give a route a `name` so links and redirects don't hard-code its path:

```php title="ShowController.php"
#[Get('/shows/{id:\d+}', name: 'shows.show')]
public function show(int $id): Response { /* ... */ }
```

Inject `UrlGeneratorInterface` to build URLs. There is no global `route()` helper:

```php title="ShowController.php"
use Marko\Routing\UrlGeneratorInterface;

class ShowController
{
    public function __construct(
        private UrlGeneratorInterface $urlGenerator,
    ) {}

    #[Post('/shows')]
    public function store(): Response
    {
        $show = $this->showService->create(/* ... */);

        return Response::redirect($this->urlGenerator->route('shows.show', ['id' => $show->id]));
    }
}
```

```php
$urlGenerator->route('shows.show', ['id' => 42]);                  // /shows/42
$urlGenerator->route('shows.show', ['id' => 42, 'tab' => 'cast']); // /shows/42?tab=cast
$urlGenerator->route('docs', ['path' => 'guides/routing']);        // /docs/guides/routing
$urlGenerator->route('shows.show', ['id' => 42], absolute: true);  // https://example.com/shows/42
```

- Values are URL-encoded with `rawurlencode()`. In a catch-all, each segment is encoded and the slashes are kept.
- Parameters the path doesn't use are appended as a query string.
- `absolute: true` prefixes the [configured](#configuration) base URL.

Each of these throws a `UrlGenerationException`:

- an unknown route name (the message suggests close matches)
- a missing or empty parameter
- a non-scalar parameter value
- a value that fails the parameter's constraint
- a value the router would not match back (see [Parameter Values and Filesystem Paths](#parameter-values-and-filesystem-paths)): a `/`, `.`, `..` or NUL byte in a normal parameter, or a `.`/`..` segment or NUL byte in a catch-all
- an absolute URL when no base URL is configured

Templates get the same generator through `route()` in [Latte](/docs/packages/view-latte/) and [Twig](/docs/packages/view-twig/).

Route names must be unique across the application. A duplicate name throws `RouteConflictException` at boot, naming both `Controller::action()` locations.

### Signed URLs

A signed URL can't be tampered with, and can expire. Use one for links that leave the app: email verification, one-click unsubscribe or approve links, time-limited share links. Inject `UrlSignerInterface`:

```php title="NewsletterMailer.php"
use DateTimeImmutable;
use Marko\Routing\Signing\UrlSignerInterface;

class NewsletterMailer
{
    public function __construct(
        private UrlSignerInterface $urlSigner,
    ) {}

    public function unsubscribeLink(int $subscriberId): string
    {
        return $this->urlSigner->signedRoute('newsletter.unsubscribe', ['id' => $subscriberId]);
        // https://example.com/newsletter/7/unsubscribe?signature=3f1c...
    }

    public function shareLink(string $token): string
    {
        return $this->urlSigner->signedRoute(
            'share.show',
            ['token' => $token],
            expiresAt: new DateTimeImmutable('+24 hours'),
        );
        // https://example.com/share/abc?expires=1793534400&signature=9a2e...
    }
}
```

Protect the route with `ValidSignatureMiddleware`:

```php title="NewsletterController.php"
use Marko\Routing\Middleware\ValidSignatureMiddleware;

#[Get('/newsletter/{id:\d+}/unsubscribe', name: 'newsletter.unsubscribe')]
#[Middleware(ValidSignatureMiddleware::class)]
public function unsubscribe(int $id): Response { /* ... */ }
```

A missing, tampered or expired signature throws `InvalidSignatureException`, an `HttpException` that renders as 403 with the message `This link is invalid.` or `This link has expired.`. To check a signature yourself, call `$urlSigner->hasValidSignature($request)` (returns a `bool`) or `$urlSigner->assertValidSignature($request)` (throws).

- `signedRoute()` builds an absolute URL by default. With `absolute: false` it returns a path, signed for the host of `APP_URL`, so it only validates there.
- `sign($url, $expiresAt)` signs a URL you built yourself, e.g. on a tenant's own host: `$urlSigner->sign('https://acme.example.com/share/abc')`. The URL must not already carry `signature` or `expires`, or a `#fragment`.
- The HMAC-SHA256 signature covers the scheme, host, path, query string and expiry. A link signed for one host doesn't validate on another. Query parameters are sorted first, so reordering them doesn't break a link; adding, removing or changing one does. Signatures are compared with `hash_equals()`.
- Query parameters listed in `signing.ignored_parameters` are left out of the signature, so a link still validates when an email client or tracker appends them (`['utm_source', 'utm_medium', 'fbclid']`). `#[Middleware]` takes class names only, so this list is global.
- Expiry is checked against `Psr\Clock\ClockInterface` from [marko/clock](/docs/packages/clock/), so tests can freeze time with `FakeClock`.
- The request's scheme comes from the server's `HTTPS` / `REQUEST_SCHEME` variables and its host from the `Host` header; `X-Forwarded-*` headers are ignored. Behind a proxy that terminates TLS, have the web server set `HTTPS=on` (e.g. `fastcgi_param HTTPS on;`), or `https://` links fail to validate.

Templates get `signedRoute()` in [Latte](/docs/packages/view-latte/) and [Twig](/docs/packages/view-twig/), with the same arguments.

#### The signing key

`signing.key` (`URL_SIGNING_KEY`) is required and must be at least 32 bytes. Signing or validating with an empty or short key throws `UrlSigningException`; the signer never falls back to another key. Use a dedicated key rather than `ENCRYPTION_KEY`: one key, one purpose.

To rotate the key without breaking links already sent, move the old key to `URL_SIGNING_PREVIOUS_KEYS` (comma-separated) and set a new `URL_SIGNING_KEY`. New links are always signed with the current key. Links signed with a previous key keep validating until you remove it.

```bash title=".env"
URL_SIGNING_KEY=new-key...
URL_SIGNING_PREVIOUS_KEYS=old-key...
```

### Route Prefixes

`#[RoutePrefix]` on a controller prefixes every route the class declares. The optional `namePrefix` is prepended to each route name:

```php title="app/api/src/Controller/ShowController.php"
use Marko\Routing\Attributes\RoutePrefix;

#[RoutePrefix('/api/v1', namePrefix: 'api.v1.')]
class ShowController
{
    #[Get('/shows', name: 'shows.index')]       // GET /api/v1/shows, name api.v1.shows.index
    public function index(): Response { /* ... */ }

    #[Get('/shows/{id:\d+}', name: 'shows.show')] // GET /api/v1/shows/{id:\d+}, name api.v1.shows.show
    public function show(int $id): Response { /* ... */ }
}
```

Slashes are normalised when joining, so `#[RoutePrefix('/api/')]` with `#[Get('shows')]` gives `/api/shows`, and `#[Get('/')]` gives `/api`. A prefix must start with `/`; anything else throws at boot. Unnamed routes stay unnamed.

The prefix belongs to the class that declares the method. A [`#[Preference]`](#overriding-vendor-routes) subclass without its own `#[RoutePrefix]` keeps the parent's prefix, including on methods it overrides. If the subclass declares its own prefix, that prefix applies only to the methods the subclass declares. Inherited routes keep the parent's prefix.

### Unmatched Requests: 404 and 405

When a request matches no route, the router responds with:

- **`405 Method Not Allowed`** when the path matches a route of another method. The `Allow` header lists the methods that would work, e.g. `Allow: GET, HEAD, OPTIONS`.
- **`404 Not Found`** otherwise.

Both are thrown as `HttpException` and rendered by `ExceptionRenderer` --- JSON when the client asks for it, a minimal HTML page otherwise (see [Errors and HTTP Exceptions](#errors-and-http-exceptions)).

#### Which middleware runs

Only global middleware marked `#[RunsOnUnmatched]` runs for unmatched requests (404, 405 and the automatic OPTIONS response). Route middleware (`#[Middleware]`) never runs, because there is no route. Every other global middleware is skipped. That means no session start, no CSRF check, no authentication and no authorization. A scanner hitting `/wp-login.php` creates no session, and a `POST` to a typo'd URL gets a 404, not a CSRF 419.

| Global middleware | Runs on unmatched requests |
|---|---|
| `CorsMiddleware` ([`marko/cors`](/docs/packages/cors/)) | Yes --- preflights and CORS headers on 404/405 |
| `SessionMiddleware` (`marko/session-file`, `marko/session-database`) | No |
| `SecurityHeadersMiddleware` ([`marko/security`](/docs/packages/security/)) | Yes --- security headers on 404/405 |
| `CsrfMiddleware` ([`marko/security`](/docs/packages/security/)) | No |
| Authentication and `AuthorizationMiddleware` | No |
| `TokenRequestMiddleware` ([`marko/authentication-token`](/docs/packages/authentication-token/)) | No --- nothing authenticates a token without a route |
| `LayoutMiddleware`, `PageCacheMiddleware` | No (they only act on matched routes anyway) |
| Your own global middleware | No, unless it declares `#[RunsOnUnmatched]` |

Because no session and no authentication run, the 404 and 405 pages are rendered without a session or an authenticated user. A custom `ExceptionRenderer` or error template must not depend on them: calling `$guard->check()` or reading the session there throws, since nothing prepared the session. For the same reason `QueuedCookiesMiddleware` ([`marko/authentication`](/docs/packages/authentication/)) does not run either: only the session guard queues cookies, and it never runs on an unmatched request.

To run your own global middleware on 404/405 responses (request logging, security headers), opt in on the class:

```php title="app/web/src/Http/Middleware/SecurityHeadersMiddleware.php"
use Marko\Routing\Attributes\RunsOnUnmatched;
use Marko\Routing\Middleware\MiddlewareInterface;

#[RunsOnUnmatched]
class SecurityHeadersMiddleware implements MiddlewareInterface
{
    // ...
}
```

The attribute is read from the class listed under `globalMiddleware`, not from a `#[Preference]` that replaces it. Opt in only for middleware that does not depend on a route or on session state. In middleware that opts in, `$request->controller()` and `$request->action()` are `null`.

Because session and auth middleware do not run, a custom error page (an `ExceptionRenderer` Preference) must not read the session, the logged-in user, flash messages or a CSRF token when rendering a 404 or 405. The session is not started there, so the read throws `SessionNotStartedException` and the 404 becomes a 500.

### HEAD and OPTIONS

- **HEAD**: when no `#[Head]` route matches, the GET route for the same path handles the request. The response keeps its status, headers and cookies, but the router always removes the body of a response to a HEAD request (including 404/405 pages). `Response::withoutBody()` does this and preserves the concrete response class; `send()` then skips `sendBody()` on a [streamed response](#file-and-streamed-responses), so a file is never read and an SSE stream is never opened.
- **OPTIONS**: when no `#[Options]` route matches but the path matches other routes, the router answers `204 No Content` with an `Allow` header (always including `HEAD` when `GET` is allowed, and `OPTIONS`). This automatic response goes through the global middleware marked `#[RunsOnUnmatched]`, so [`marko/cors`](/docs/packages/cors/) turns a browser preflight into a full CORS response. An OPTIONS request to an unknown path gets a 404.

### Adding Middleware

```php title="AdminController.php"
use Marko\Routing\Attributes\Middleware;

class AdminController
{
    #[Get('/admin/dashboard')]
    #[Middleware(AuthMiddleware::class)]
    public function dashboard(): Response
    {
        return new Response('Admin dashboard');
    }
}
```

Middleware classes implement `MiddlewareInterface`:

```php title="AuthMiddleware.php"
use Marko\Routing\Http\Request;
use Marko\Routing\Http\Response;
use Marko\Routing\Middleware\MiddlewareInterface;

class AuthMiddleware implements MiddlewareInterface
{
    public function handle(
        Request $request,
        callable $next,
    ): Response {
        if (!$this->isAuthenticated($request)) {
            return new Response('Unauthorized', 401);
        }

        return $next($request);
    }
}
```

`#[Middleware]` on a class applies to every route in the class, and to the routes a subclass (such as a `#[Preference]`) inherits. Class-level middleware and exclusions are collected from the class and all its ancestors.

### Skipping Middleware

Modules register **global** middleware that runs on every route. `marko/session-file` and `marko/session-database` register the session middleware, for example. `#[WithoutMiddleware]` removes middleware from one route, or from every route in a class. It accepts a class name or an array, and it works on global and route middleware alike:

```php title="app/api/src/Controller/WebhookController.php"
use Marko\Routing\Attributes\WithoutMiddleware;
use Marko\Session\Middleware\SessionMiddleware;

#[WithoutMiddleware(SessionMiddleware::class)]
class WebhookController
{
    #[Post('/webhooks/stripe')]
    public function stripe(): Response { /* ... */ }
}
```

With [`marko/security`](/docs/packages/security/) installed, `CsrfMiddleware` is global as well, and a webhook receiver like this one excludes it alongside the session: `#[WithoutMiddleware([SessionMiddleware::class, CsrfMiddleware::class])]`.

The route's stack is global middleware, then route middleware, then the global middleware marked `#[RunsInnermost]` (see [Innermost global middleware](#innermost-global-middleware)), minus the excluded classes. Unmatched requests (404/405) have no route to exclude anything; they run only the global middleware marked `#[RunsOnUnmatched]` (see [Which middleware runs](#which-middleware-runs)).

If a route excludes middleware that is neither global nor on the route, boot fails with a `RouteException`. That catches a typo in the class name, and it catches a driver package that isn't installed. A silent no-op would leave the middleware running. The [stateless API recipe](/docs/packages/session/#stateless-routes) shows the session case in full.

### Innermost Global Middleware

Global middleware normally wraps every route middleware. A global middleware that turns the controller's result into the final response (layout rendering in [`marko/layout`](/docs/packages/layout/), for example) needs the opposite: it should run directly around the controller, so route middleware sees, and can decorate, the final response. Mark it `#[RunsInnermost]`:

```php title="app/web/src/Http/Middleware/WrapInFrameMiddleware.php"
use Marko\Routing\Attributes\RunsInnermost;
use Marko\Routing\Middleware\MiddlewareInterface;

#[RunsInnermost]
class WrapInFrameMiddleware implements MiddlewareInterface
{
    // ...
}
```

For a matched route the stack becomes: global middleware, route middleware, then the `#[RunsInnermost]` global middleware in their declaration order, then the controller. A route middleware that denies the request (an auth redirect, a 403) answers before the innermost middleware runs, and headers or cookies a route middleware adds to the response returned by `$next()` are kept. `#[WithoutMiddleware]` excludes innermost middleware like any other. As with `#[RunsOnUnmatched]`, the attribute is read from the class listed under `globalMiddleware`, not from a `#[Preference]` that replaces it.

### Decorating Responses

Middleware that adds headers, cookies, or changes the status code should decorate the response returned by `$next()` rather than construct a new one:

```php title="SecurityHeadersMiddleware.php"
use Marko\Routing\Http\Request;
use Marko\Routing\Http\Response;
use Marko\Routing\Middleware\MiddlewareInterface;

class SecurityHeadersMiddleware implements MiddlewareInterface
{
    public function handle(
        Request $request,
        callable $next,
    ): Response {
        $response = $next($request);

        return $response->withHeader('X-Frame-Options', 'DENY');
    }
}
```

`withHeader()`, `withHeaders()`, `withStatus()`, and `withCookie()` each return a clone of the response, preserving its concrete class. Rebuilding a response instead, e.g. `new Response($response->body(), $response->statusCode(), $headers)`, silently discards subclass identity --- a `StreamingResponse` returned by an SSE endpoint would be downgraded to a plain `Response` and its stream would never send. Always decorate, never rebuild.

### Setting Cookies

Attach a cookie to a response with `withCookie()`:

```php
use Marko\Routing\Attributes\Get;
use Marko\Routing\Http\Cookie;
use Marko\Routing\Http\Response;

#[Get('/login')]
public function login(): Response
{
    return Response::json(['ok' => true])
        ->withCookie(new Cookie(
            name: 'session',
            value: $sessionId,
            expires: time() + 3600,
            path: '/',
            secure: true,
            httpOnly: true,
            sameSite: 'Lax',
        ));
}
```

`expires: null` or `expires: 0` omits the `Expires` attribute entirely, producing a browser-session cookie instead of a persistent one. Cookie values are `rawurlencode()`d automatically, so a value containing `;` cannot inject a second attribute. An invalid cookie name throws `CookieException`, as does `sameSite: 'None'` without `secure: true` --- browsers silently drop such cookies, so Marko fails loudly instead.

To give a cookie a lifetime instead of a timestamp, pass `maxAge` in seconds. Browsers count it from when they receive the cookie, so it is unaffected by clock skew between server and client, and it wins over `Expires` when both are sent:

```php
// Remember the user for 30 days
$response = $response->withCookie(new Cookie(
    name: 'remember',
    value: $token,
    path: '/',
    secure: true,
    httpOnly: true,
    sameSite: 'Lax',
    maxAge: 60 * 60 * 24 * 30,
));

// Delete the cookie: Max-Age=0 expires it immediately
$response = $response->withCookie(new Cookie(name: 'remember', path: '/', maxAge: 0));
```

`maxAge` emits only `Max-Age=`; no `Expires` is derived from it. Pass `expires` as well if you need to support clients that predate `Max-Age`. A zero or negative `maxAge` is sent as `Max-Age=0`, which deletes the cookie.

Read cookies sent by the client with `Request::cookie()`, which mirrors `query()` and `post()`:

```php
$sessionId = $request->cookie('session');
```

### Reading JSON Bodies

A request whose `Content-Type` is `application/json` (or any `+json` type, such as `application/vnd.api+json`) has its body decoded once, when the `Request` is built. Read it with `json()`, using dot-notation for nested keys:

```php
use Marko\Routing\Attributes\Post;
use Marko\Routing\Http\Request;
use Marko\Routing\Http\Response;

#[Post('/api/users')]
public function store(Request $request): Response
{
    $email = $request->json('user.email');
    $roles = $request->json('user.roles', []);

    return Response::json(['email' => $email], 201);
}
```

JSON fields bind to controller parameters marked `#[FromBody]` (or `#[FromInput]`), the same way form fields do:

```php
#[Post('/api/shows')]
public function create(#[FromBody] string $title, #[FromBody] int $count): Response
{
    // POST {"title": "Live at Five", "count": 7}
    return Response::json(['title' => $title, 'count' => $count], 201);
}
```

`input()` reads from whichever body the request carries (the JSON body for JSON requests, form data otherwise) and falls back to the query string, so one controller can serve both kinds of client:

```php
$title = $request->input('title', 'Untitled');
```

A malformed JSON body throws `MalformedJsonException` when it is read, never silently returning an empty payload. It implements `HttpExceptionInterface`, so the pipeline renders it as a `400` wherever it is thrown --- while binding controller parameters or inside your controller. `wantsJson()` checks the `Accept` header, so you can pick a response format for the client:

```php
if ($request->wantsJson()) {
    return Response::json(['ok' => true]);
}
```

### Handling File Uploads

Uploaded files arrive as `Marko\Routing\Http\UploadedFile` objects, keyed like the form fields that sent them. `file()` returns one file, `files()` returns a list for multi-file inputs (`name="photos[]"`), and nested fields use dot-notation (`user.avatar`):

```php
use Marko\Routing\Attributes\Post;
use Marko\Routing\Http\Request;
use Marko\Routing\Http\Response;

#[Post('/profile/avatar')]
public function upload(Request $request): Response
{
    $avatar = $request->file('avatar');

    if ($avatar === null || !$avatar->isValid()) {
        return new Response('Upload an image', 422);
    }

    $extension = $avatar->guessExtension() ?? 'bin';
    $avatar->moveTo("/var/app/storage/avatars/" . bin2hex(random_bytes(16)) . ".$extension");

    return new Response('Saved', 201);
}
```

The client filename and media type are untrusted: `mimeType()` and `guessExtension()` inspect the file contents with `finfo` instead. Inputs submitted without a file are left out, so `hasFile()` is `false` for them. An upload that failed (for example, larger than `upload_max_filesize`) is still present but not valid; moving it, or moving any file twice, throws `UploadedFileException` naming the reason. Calling `file()` on a multi-file input also throws --- use `files()` there.

To check type and size with 422 errors in the same shape as the rest of the form, use the file rules in [`marko/validation`](/docs/packages/validation/#validating-file-uploads) (`file`, `image`, `mimes`, `mimetypes`, `max_size`, `min_size`) instead of hand-written checks. `UploadedFile` implements `Marko\Core\Contracts\UploadedFileInterface`, the contract those rules depend on.

To store an upload through [`marko/media`](/docs/packages/media/), build a `Marko\Media\Value\UploadedFile` from it:

```php
use Marko\Media\Value\UploadedFile as MediaUpload;

$media = $mediaManager->upload(new MediaUpload(
    name: $avatar->clientFilename(),
    tmpPath: $avatar->tempPath(),
    mimeType: $avatar->mimeType(),
    size: $avatar->size(),
    extension: $avatar->guessExtension() ?? 'bin',
));
```

### File and Streamed Responses

`Response` holds its body as a string. For a large file or a body generated on the fly, return a response that implements `Marko\Routing\Http\StreamedResponseInterface` instead: its `body()` is `''`, and `Response::send()` emits the headers and then calls `sendBody()`, which writes the body in chunks. Runtimes and middleware check for the interface, so [`marko/page-cache`](/docs/packages/page-cache/) never caches one and [`marko/roadrunner`](/docs/packages/roadrunner/#file-downloads-and-streamed-responses) collects its body into a PSR-7 stream instead of reading the empty `body()`.

#### Serving a file

`FileResponse::fromSource()` takes the request and a `FileSourceInterface`. It streams the file in 64 KB chunks without loading it into memory, and answers the request's `Range` and validator headers:

```php title="VideoController.php"
use Marko\Routing\Attributes\Get;
use Marko\Routing\File\LocalFileSource;
use Marko\Routing\Http\ContentDisposition;
use Marko\Routing\Http\FileResponse;
use Marko\Routing\Http\Request;
use Marko\Routing\SafePath;

#[Get('/videos/{name}')]
public function show(Request $request, string $name): FileResponse
{
    $source = new LocalFileSource(SafePath::join('/var/app/storage/videos', $name));

    return FileResponse::fromSource($request, $source);
}

#[Get('/videos/{name}/download')]
public function download(Request $request, string $name): FileResponse
{
    $source = new LocalFileSource(SafePath::join('/var/app/storage/videos', $name));

    return FileResponse::fromSource($request, $source)
        ->withDisposition(ContentDisposition::attachment($source->filename()));
}
```

Always build a request-derived path with [`SafePath::join()`](#parameter-values-and-filesystem-paths). `LocalFileSource` throws `FileResponseException` when the file is missing, is a directory or is unreadable; check `is_file()` first and throw `HttpException::notFound()` when a missing file is the client's problem.

| Request | Response |
| --- | --- |
| Plain `GET` | `200` with the whole file, `Content-Length`, `Content-Type` (detected with `finfo`), `Last-Modified`, a weak `ETag` (size and mtime) and `Accept-Ranges: bytes` |
| `Range: bytes=0-99`, `bytes=500-` or `bytes=-500` (suffix) | `206 Partial Content` with `Content-Range: bytes 0-99/1234` and that slice. Browsers need this to seek in a `<video>`, and Safari won't play video without it |
| A range past the end of the file, or a malformed one | `416 Range Not Satisfiable` with `Content-Range: bytes */1234` (`RangeNotSatisfiableException`) |
| Several ranges (`bytes=0-99,200-299`) | `200` with the whole file. `multipart/byteranges` is not supported |
| `If-None-Match` with the current ETag, or `If-Modified-Since` not older than the file | `304 Not Modified`, no body |
| `Range` with an `If-Range` that no longer matches | `200` with the whole file |

`If-None-Match` wins over `If-Modified-Since`. `If-Range` needs a strong match, so an `If-Range` ETag never matches the weak ETag and the client gets the whole file; an `If-Range` date equal to `Last-Modified` allows the range. `Range` is only honoured on `GET`. Override the detected type with `->withHeader('Content-Type', 'video/mp4')`.

#### Files on a filesystem disk

`FilesystemFileSource` serves a file from any [`marko/filesystem`](/docs/packages/filesystem/) disk (local, S3) through `readStream()`. marko/routing only suggests marko/filesystem, so install it and a driver first:

```bash
composer require marko/filesystem marko/filesystem-local
```

```php
use Marko\Routing\File\FilesystemFileSource;

$source = new FilesystemFileSource($this->filesystemManager->disk('videos'), $path);

return FileResponse::fromSource($request, $source);
```

On a stream that cannot seek, a range is reached by reading and discarding the bytes before it. Implement `FileSourceInterface` yourself for any other storage.

#### Content-Disposition

`ContentDisposition` builds and parses the header. `ContentDisposition::inline($filename)` and `ContentDisposition::attachment($filename)` take an optional filename and throw `ContentDispositionException` for an empty name or one containing CR, LF, NUL, `/` or `\`. `toHeaderValue()` gives the RFC 6266 form: a non-ASCII name is sent as an ASCII fallback plus `filename*=UTF-8''...`:

```php
ContentDisposition::attachment('Résumé.pdf')->toHeaderValue();
// attachment; filename="R_sum_.pdf"; filename*=UTF-8''R%C3%A9sum%C3%A9.pdf
```

`ContentDisposition::fromHeader($value)` parses a header back (preferring `filename*`), and `type()` returns `DispositionType::Inline` or `DispositionType::Attachment`. On a plain `Response`, use `->withHeader('Content-Disposition', $disposition->toHeaderValue())`.

#### Offloading files to the web server

In production, let the web server send the bytes. Set `routing.file_offload` and build file responses with `FileResponseFactory`, which applies it:

```php title="config/routing.php"
'file_offload' => [
    'header' => 'X-Accel-Redirect',
    'prefix_map' => ['/var/app/storage/videos/' => '/protected-videos/'],
],
```

```php
use Marko\Routing\Http\FileResponseFactory;

public function __construct(
    private FileResponseFactory $fileResponseFactory,
) {}

#[Get('/videos/{name}')]
public function show(Request $request, string $name): FileResponse
{
    $source = new LocalFileSource(SafePath::join('/var/app/storage/videos', $name));

    return $this->fileResponseFactory->fromSource($request, $source);
}
```

With offload on, a `LocalFileSource` response carries `Content-Type`, `Last-Modified` and the offload header with an empty body; the server handles `Range` and `Content-Length`. The prefix map replaces the longest matching local prefix (`/var/app/storage/videos/intro.mp4` becomes `/protected-videos/intro.mp4`, an nginx `internal` location). `X-Accel-Redirect` requires a prefix map; with `X-Sendfile` and an empty map the local path is sent as is. A file outside every mapped prefix throws `FileResponseException`, as does any other header name. Offload is never auto-detected, and other file sources (a remote disk has no local path) are always streamed by PHP. `FileResponse::fromSource()` called without a `FileOffload` never offloads.

#### Streaming generated content

`StreamedResponse` takes a callback that receives the `OutputStreamer` and writes chunks as it produces them:

```php
use Marko\Routing\Http\OutputStreamer;
use Marko\Routing\Http\StreamedResponse;

#[Get('/reports/orders.csv')]
public function export(): StreamedResponse
{
    return new StreamedResponse(
        callback: function (OutputStreamer $outputStreamer): void {
            $outputStreamer->write("id,total\n");

            foreach ($this->orderRepository->cursor() as $order) {
                if (!$outputStreamer->write("$order->id,$order->total\n")) {
                    return; // the client disconnected
                }
            }
        },
        headers: ['Content-Type' => 'text/csv'],
    );
}
```

`OutputStreamer::prepare()` (called before the body) drains every output buffer, enables implicit flush and lifts the time limit; `write()` echoes and flushes one chunk and returns `false` once `connection_aborted()` reports the client gone. The callback is not called for HEAD requests. In tests, read the body with [`TestResponse::streamedContent()`](/docs/packages/testing/#streamed-and-file-responses).

#### Writing your own streamed response

Extend `AbstractStreamedResponse` and implement `sendBody()`, writing through `$this->outputStreamer()`. A class that implements `StreamedResponseInterface` directly also needs `withOutputStreamer()`, which tests use to swap in a streamer that leaves output buffers alone. Subclasses that still override `send()` keep working, but move the body into `sendBody()` so HEAD handling, page-cache and RoadRunner see the response as streamed.

### Overriding Vendor Routes

Use [Preferences](/docs/packages/core/) to replace a vendor's controller:

```php title="MyPostController.php"
use Marko\Core\Attributes\Preference;
use Marko\Routing\Attributes\Get;
use Vendor\Blog\PostController;

#[Preference(replaces: PostController::class)]
class MyPostController extends PostController
{
    #[Get('/blog')]  // Your route takes over
    public function index(): Response
    {
        return new Response('My custom blog');
    }
}
```

Inherited routes keep everything the parent declared: path, `#[RoutePrefix]`, name, class-level `#[Middleware]` and `#[WithoutMiddleware]`. They dispatch to your class.

### Disabling Routes

Explicitly remove an inherited route:

```php title="MyPostController.php"
use Marko\Routing\Attributes\DisableRoute;

#[Preference(replaces: PostController::class)]
class MyPostController extends PostController
{
    #[DisableRoute]  // Removes /blog/{slug} route
    public function show(
        string $slug,
    ): Response {
        // Method still exists but has no route
    }
}
```

### Route Conflicts

If two modules define the same route, Marko throws `RouteConflictException` at boot:

```
Route conflict detected for GET /products

Defined in:
  - Vendor\Catalog\ProductController::index()
  - App\Store\ProductController::list()

Resolution: Use #[Preference] to extend one controller,
or use #[DisableRoute] to remove one route.
```

Two routes with the same name fail the same way. The error lists both `Controller::action()` locations.

### Errors and HTTP Exceptions

Throw `HttpException` from a controller or middleware to respond with an error status. There is no global `abort()` helper --- throw the exception explicitly:

```php title="app/blog/src/Controller/PostController.php"
use Marko\Routing\Exceptions\HttpException;

#[Get('/posts/{id}')]
public function show(int $id): Response
{
    $post = $this->postRepository->findById($id)
        ?? throw HttpException::notFound('Post not found.');

    return Response::json(['title' => $post->title]);
}
```

Named constructors cover the common cases: `badRequest()`, `unauthorized()`, `forbidden()`, `notFound()`, `methodNotAllowed(['GET', 'HEAD'])` (sets `Allow`), `conflict()`, and `tooManyRequests(retryAfter: 60)` (sets `Retry-After`). For anything else use the constructor, which accepts any `400`--`599` status plus headers and extra client-safe data:

```php
throw new HttpException(
    statusCode: 409,
    message: 'That slug is already taken.',
    data: ['field' => 'slug'],
);
```

The message is client-facing: it is sent as `message` in the response body. When omitted it defaults to the status reason phrase (`Not Found`, `Conflict`, ...).

#### Where errors are rendered

The middleware pipeline catches any exception implementing `Marko\Core\Exceptions\HttpExceptionInterface` **at the depth it was thrown** and turns it into a `Response`. Every middleware outside that point still runs, so CORS headers, security headers, session saving and similar decorations apply to error responses too. (A 404 or 405 from the router itself only passes through the global middleware marked `#[RunsOnUnmatched]`; see [Which middleware runs](#which-middleware-runs).) This works the same under PHP-FPM and [RoadRunner](/docs/packages/roadrunner/).

Any other throwable is not caught: it propagates out of `Router::handle()` to the installed error handler ([`marko/errors-simple`](/docs/packages/errors-simple/) or [`marko/errors-advanced`](/docs/packages/errors-advanced/)), which renders a `500`.

#### Framework exceptions

These framework exceptions implement `HttpExceptionInterface`, so they render without any glue code:

| Exception | Status | Body |
|---|---|---|
| `Marko\Routing\Exceptions\InvalidRouteParameterException` | `400` | `{"message": "Missing required parameter 'id' of type 'int'"}` |
| `Marko\Routing\Exceptions\UnsafePathException` | `404` | `{"message": "Not Found"}` (never the rejected path) |
| `Marko\Security\Exceptions\CsrfTokenMismatchException` | `419` | `{"message": "CSRF token mismatch."}` |
| `Marko\Validation\Exceptions\ValidationException` | `422` | `{"message": "The given data was invalid.", "errors": {"email": ["..."]}}` |
| `Marko\Database\Exceptions\EntityNotFoundException` | `404` | `{"message": "Not found."}` (never the entity class or ID) |
| `Marko\Database\Exceptions\UniqueConstraintViolationException` | `409` | `{"message": "Conflict."}` (never the constraint, SQL or values) |
| `Marko\Database\Exceptions\ForeignKeyConstraintViolationException` | `409` | `{"message": "Conflict."}` (never the constraint, SQL or values) |
| `Marko\Authorization\Exceptions\AuthorizationException` | `403` | `{"message": "Forbidden."}` (never the ability or resource) |

Your own exceptions can implement the interface too --- useful for domain exceptions in packages that should not depend on `marko/routing`:

```php
use Marko\Core\Exceptions\HttpExceptionInterface;

class SubscriptionExpiredException extends RuntimeException implements HttpExceptionInterface
{
    public function getStatusCode(): int
    {
        return 402;
    }

    public function getHeaders(): array
    {
        return [];
    }

    public function getResponseData(): array
    {
        return ['message' => 'Your subscription has expired.'];
    }
}
```

Only `getResponseData()` reaches the client. The exception message, file, and trace are never rendered, in any environment.

#### JSON or HTML

`ExceptionRenderer` renders JSON when the request's `Accept` header contains `application/json` or a `+json` type (for example `application/problem+json`), or when there is no `Accept` header and the `Content-Type` is JSON. The body is the response data with a `message` key guaranteed. Otherwise it renders a minimal HTML page showing the status and message. The exception's headers are added in both cases.

#### HTTP exception responders

A responder answers some HTTP exceptions with a response of its own instead of the error page --- for example [marko/forms](/docs/packages/forms/) redirects a failed HTML form back with its errors flashed. Implement `HttpExceptionResponderInterface` and mark the class with `#[HttpExceptionResponder]`; it is discovered in any module's `src/`. Return a `Response` to handle the exception, or `null` to pass:

```php title="app/billing/src/Http/SubscriptionExpiredResponder.php"
use App\Billing\Exceptions\SubscriptionExpiredException;
use Marko\Core\Exceptions\HttpExceptionInterface;
use Marko\Routing\Attributes\HttpExceptionResponder;
use Marko\Routing\Http\HttpExceptionResponderInterface;
use Marko\Routing\Http\Request;
use Marko\Routing\Http\Response;

#[HttpExceptionResponder(priority: 10)]
class SubscriptionExpiredResponder implements HttpExceptionResponderInterface
{
    public function respond(
        HttpExceptionInterface $exception,
        Request $request,
    ): ?Response {
        if (!$exception instanceof SubscriptionExpiredException || $request->wantsJson()) {
            return null;
        }

        return Response::redirect('/billing', 303);
    }
}
```

`ExceptionRenderer` asks the responders highest `priority` first (default `0`; equal priorities keep module load order); the first non-null response wins, and when every responder passes it renders as usual. Responders run where the exception is rendered, so their response still flows back out through every middleware. Each responder is built once, through the container, on the first HTTP exception, so it can inject its dependencies. A class with the attribute that does not implement the interface throws `HttpExceptionResponderException` at boot. Responders are stored in the [route cache](#route-cache) along with the routes.

A `#[Preference]` for `ExceptionRenderer` that declares its own constructor must accept the `HttpExceptionResponderRegistry` and pass it to `parent::__construct()`, or responders are skipped.

#### Custom error pages

Replace `ExceptionRenderer` with a `#[Preference]` to render branded pages. Override `renderHtml()` to change only the HTML output, `renderJson()` for JSON, or `render()` for both:

```php title="app/web/src/Http/BrandedExceptionRenderer.php"
use Marko\Core\Attributes\Preference;
use Marko\Routing\Http\ExceptionRenderer;
use Marko\Routing\Http\Response;

#[Preference(replaces: ExceptionRenderer::class)]
class BrandedExceptionRenderer extends ExceptionRenderer
{
    protected function renderHtml(
        int $statusCode,
        array $data,
    ): Response {
        return Response::html(
            "<h1>Oops ($statusCode)</h1><p>" . htmlspecialchars($data['message']) . '</p>',
            $statusCode,
        );
    }
}
```

### Route Cache

Without a cache, every boot finds routes by reading, tokenizing and loading every PHP file under every module's `src/` --- controllers or not. `marko/routing` declares `RouteCacheContributor` as a [discovery cache contributor](/docs/packages/core/#adding-a-section-to-the-discovery-cache), so `marko discovery:cache` also stores every route: method, path (with any `#[RoutePrefix]` applied), controller, action, middleware, name and `#[WithoutMiddleware]` exclusions, including routes a `#[Preference]` inherits from the controller it replaces. Its `HttpExceptionResponderCacheContributor` stores the discovered [HTTP exception responders](#http-exception-responders) the same way.

A production boot from the cache rebuilds the same routes in the same order, so [precedence](#route-precedence), names and URL generation are unchanged. No source file is scanned, and a controller class loads only when a request matches one of its routes. Excluded middleware is still checked against each route's stack at boot.

In a development environment (`APP_ENV=local`, `development` or `dev`) routes are always discovered live, so a new or changed route attribute takes effect on the next request. In production, run `marko discovery:cache` on every deploy --- see [Deploying to production](/docs/packages/core/#deploying-to-production). Until you do, a route added to an existing controller is not served.

## CLI

Requires [`marko/cli`](/docs/packages/cli/) for the `marko` binary.

### Listing Routes

See all registered routes:

```bash
marko route:list
```

```
METHOD  PATH                    NAME               ACTION                     MIDDLEWARE
GET     /                       home               HelloController::index
GET     /blog                   blog.index         PostController::index
GET     /api/v1/shows/{id:\d+}  api.v1.shows.show  ShowController::show       -SessionMiddleware
GET     /blog/{id}                                 PostController::show
POST    /webhooks/stripe                           WebhookController::stripe  VerifySignature, -SessionMiddleware
```

Paths include any `#[RoutePrefix]`. The MIDDLEWARE column lists route middleware, then excluded middleware prefixed with `-`. Routes are grouped by method (`GET`, `HEAD`, `POST`, `PUT`, `PATCH`, `DELETE`, `OPTIONS`, then any others) and listed in the order the router tries them --- see [Route Precedence](#route-precedence). Automatic HEAD and OPTIONS responses are not listed.

Filter by HTTP method or path:

```bash
marko route:list --method=POST
marko route:list --path=products
marko route:list --method=GET --path=blog
```

## API Reference

### Route Attributes

```php
#[Get(path: '/path', middleware: [], name: null)]
#[Post(path: '/path', name: 'route.name')]
#[Put(path: '/path')]
#[Patch(path: '/path')]
#[Delete(path: '/path')]
#[Head(path: '/path')]
#[Options(path: '/path')]
#[DisableRoute]
#[Middleware(MiddlewareClass::class)]               // class or method; class-string or array
#[WithoutMiddleware(MiddlewareClass::class)]        // class or method; class-string or array
#[RoutePrefix(prefix: '/api', namePrefix: 'api.')] // class only
#[RunsOnUnmatched]                                  // middleware class only; runs on 404/405/automatic OPTIONS
#[RunsInnermost]                                    // global middleware class only; runs after route middleware
```

### UrlGeneratorInterface

```php
interface UrlGeneratorInterface
{
    /** @throws UrlGenerationException */
    public function route(string $name, array $parameters = [], bool $absolute = false): string;
}
```

Bound to `UrlGenerator` as a singleton.

### UrlSignerInterface

```php
interface UrlSignerInterface
{
    public function signedRoute(string $name, array $parameters = [], ?DateTimeImmutable $expiresAt = null, bool $absolute = true): string;
    public function sign(string $url, ?DateTimeImmutable $expiresAt = null): string;
    public function hasValidSignature(Request $request): bool;
    /** @throws InvalidSignatureException */
    public function assertValidSignature(Request $request): void;
}
```

Bound to `HmacUrlSigner` as a singleton. `InvalidSignatureException::invalid()` and `::expired()` build the two 403 errors, and `isExpired()` tells them apart.

### RouteCollection

```php
class RouteCollection
{
    public function named(string $name): ?RouteDefinition;
    public function names(): array;
    public function inMatchOrder(): array;
    public function byMethod(string $method): array;
    public function all(): array;
}
```

### RouteDefinition

Each route is a `readonly` value object. Every constructor argument is a string or a list of strings (`method`, `path`, `controller`, `action`, `middleware`, `name`, `withoutMiddleware`). The path already includes any prefix. Everything else (`parameters`, `constraints`, `catchAll`, `regex`) is derived from the path.

```php
public function satisfiesConstraint(string $parameter, string $value): bool;
public function acceptsValue(string $parameter, string $value): bool; // the decoded-value rules the matcher and URL generator apply
```

### SafePath

```php
public static function join(string $base, string $relative): string; // throws UnsafePathException (404) on an absolute path, NUL byte or escape
```

### Request

```php
class Request
{
    public function method(): string;
    public function path(): string;
    public function query(?string $key = null, mixed $default = null): mixed;
    public function post(?string $key = null, mixed $default = null): mixed;
    public function cookie(?string $key = null, mixed $default = null): mixed;
    public function body(): string;
    public function isJson(): bool;
    public function wantsJson(): bool;
    public function json(?string $key = null, mixed $default = null): mixed;
    public function input(?string $key = null, mixed $default = null): mixed;
    public function file(string $key): ?UploadedFile;
    public function files(?string $key = null): array;
    public function hasFile(string $key): bool;
    public function header(string $name, ?string $default = null): ?string;
    public function headers(): array;
    public function server(string $key): ?string;
    public function ip(): ?string;
    public function withRoute(string $controller, string $action): self;
    public function controller(): ?string;
    public function action(): ?string;
    public static function fromGlobals(): self;
}
```

`ip()` returns `REMOTE_ADDR` from the server bag (equivalent to `server('REMOTE_ADDR')`). `cookie()` reads from the request's `$_COOKIE` bag and mirrors the signature of `query()` and `post()`. `withRoute()` returns a new immutable `Request` with the matched controller class and action method attached; `controller()` and `action()` retrieve them. The router attaches route context before invoking middleware, which allows middleware (such as `AdminAuthMiddleware`) to inspect which controller method is handling the request. `withRoute()` carries the uploaded files and the decoded JSON body through unchanged.

| Method | Description |
| --- | --- |
| `isJson()` | `true` when `Content-Type` is `application/json` or ends in `+json` (e.g. `application/vnd.api+json`) |
| `wantsJson()` | `true` when the `Accept` header lists `application/json` or a `+json` type |
| `json($key, $default)` | The decoded JSON body, or one value from it by dot-notation key (`user.email`). Returns `[]` / `$default` for non-JSON requests and empty bodies. Throws `MalformedJsonException` for a malformed body |
| `input($key, $default)` | The JSON body for JSON requests, form data otherwise, then the query string. With no key, the query string merged with the body (body values win) |
| `file($key)` | One `UploadedFile`, or `null`. Dot-notation for nested fields. Throws `UploadedFileException` when the field holds several files |
| `files($key)` | With no key, every uploaded file keyed like its form field. With a key, that field's files as a list |
| `hasFile($key)` | Whether at least one file was uploaded for the field (check `isValid()` before using it) |

The constructor accepts a `files` argument (`array<string, UploadedFile|array>`), so a request with uploads can be built without superglobals. `fromGlobals()` normalizes `$_FILES`, including PHP's inverted `name[]` / `name[key]` layout for multi-file and nested inputs, and leaves out inputs submitted without a file (`UPLOAD_ERR_NO_FILE`).

### UploadedFile

```php
use Marko\Routing\Http\UploadedFile; // implements Marko\Core\Contracts\UploadedFileInterface

public function __construct(
    string $tempPath,
    string $clientFilename,
    string $clientMediaType,
    int $size,
    int $error = UPLOAD_ERR_OK,
)

public function clientFilename(): string;
public function clientMediaType(): string;
public function size(): int;
public function error(): int;
public function tempPath(): string;
public function isValid(): bool;
public function isMoved(): bool;
public function moveTo(string $targetPath): void;
public function stream(): mixed; // resource
public function contents(): string;
public function mimeType(): string;
public function guessExtension(): ?string;
```

`clientFilename()` and `clientMediaType()` are whatever the client sent --- never use them as a storage path or to decide what a file is. `mimeType()` detects the real type from the contents with `finfo`, and `guessExtension()` maps it to an extension (`null` when unknown). `isValid()` is `true` only for a successful upload (`UPLOAD_ERR_OK`) that has not been moved yet. `moveTo()` uses `move_uploaded_file()` under a web SAPI (PHP-FPM, Apache) and `rename()` elsewhere (CLI, tests, RoadRunner workers), and can be called once. Moving twice, moving a failed upload, moving into a missing directory, or reading a moved file throws `UploadedFileException` with the reason and a fix (for a failed upload, the `UPLOAD_ERR_*` name and the `php.ini` setting to change).

### Response

```php
class Response
{
    public function __construct(
        string $body = '',
        int $statusCode = 200,
        array $headers = [],
    );

    public function body(): string;
    public function statusCode(): int;
    public function headers(): array;
    public function cookies(): array;
    public function headerLines(): array;
    public function send(): void;
    public static function json(mixed $data, int $statusCode = 200): self;
    public static function html(string $html, int $statusCode = 200): self;
    public static function redirect(string $url, int $statusCode = 302): self;
    public function withHeader(string $name, string $value): static;
    public function withHeaders(array $headers): static;
    public function withStatus(int $statusCode): static;
    public function withCookie(Cookie $cookie): static;
    public function withoutBody(): static;
    public function isBodyOmitted(): bool;
}
```

`withoutBody()` empties the body and marks it omitted, keeping status, headers, cookies and the concrete class; the router applies it to every HEAD response. `send()` emits the status and headers through the protected `sendHeaders()` (each regular header replaces any earlier one of the same name, while every cookie gets its own `Set-Cookie` header, so several cookies on one response all reach the client), then calls `sendBody()` for a `StreamedResponseInterface` whose body is not omitted, or echoes `body()` otherwise.

`cookies()` returns the `Cookie` instances attached to the response; `headerLines()` returns the raw `"Name: value"` lines followed by one `Set-Cookie:` line per cookie, without making any SAPI calls --- `send()` uses it internally, and it's also useful for testing. `withHeader()` and `withHeaders()` merge into the existing headers (`withHeaders()` merges its argument over the current set). `withCookie()` replaces an existing cookie that matches on `(name, path, domain)`, or appends a new one otherwise. Every `with*()` method is marked `#[\NoDiscard]` and returns a clone via PHP's `clone` operator rather than `new static(...)`, so a `Response` subclass such as `StreamingResponse` survives decoration intact --- see [Decorating Responses](#decorating-responses). `Response` is deliberately not a `readonly class` for this reason: immutability is enforced by API design (private properties, no setters) rather than the `readonly` keyword.

`json()`, `html()`, and `redirect()` construct with `new self()`, not `new static()`, since a subclass such as `StreamingResponse` has a different constructor signature and cannot accept `(body:, statusCode:, headers:)`.

### StreamedResponseInterface and AbstractStreamedResponse

```php
use Marko\Routing\Http\AbstractStreamedResponse;
use Marko\Routing\Http\StreamedResponseInterface;

interface StreamedResponseInterface
{
    public function sendBody(): void;                                           // write the body to output
    public function withOutputStreamer(OutputStreamer $outputStreamer): static; // copy that writes through another streamer
}

abstract class AbstractStreamedResponse extends Response implements StreamedResponseInterface
{
    public function __construct(int $statusCode = 200, array $headers = []);
    public function withOutputStreamer(OutputStreamer $outputStreamer): static;
    protected function outputStreamer(): OutputStreamer;
}
```

### OutputStreamer

```php
use Marko\Routing\Http\OutputStreamer;

public function prepare(): void;              // drain output buffers, implicit flush, no time limit
public function write(string $chunk): bool;   // echo + flush; false once the client disconnected
public function connectionAborted(): bool;
```

### StreamedResponse

```php
use Marko\Routing\Http\StreamedResponse;

public function __construct(callable $callback, int $statusCode = 200, array $headers = []); // callable(OutputStreamer): void
```

### FileResponse and FileResponseFactory

```php
use Marko\Routing\Http\FileResponse;
use Marko\Routing\Http\FileResponseFactory;

// FileResponse extends AbstractStreamedResponse
public static function fromSource(Request $request, FileSourceInterface $fileSource, ?FileOffload $fileOffload = null): FileResponse; // throws RangeNotSatisfiableException (416)
public function withDisposition(ContentDisposition $contentDisposition): static;
public function fileSource(): FileSourceInterface;
public function byteRange(): ?ByteRange;     // the range being served, null for a full body

// FileResponseFactory applies routing.file_offload
public function fromSource(Request $request, FileSourceInterface $fileSource): FileResponse;
```

### File Sources

```php
use Marko\Routing\File\FileSourceInterface;
use Marko\Routing\File\FilesystemFileSource;
use Marko\Routing\File\LocalFileSource;

interface FileSourceInterface
{
    public function size(): int;
    public function lastModified(): int;
    public function mimeType(): string;
    public function filename(): string;
    public function stream(int $offset = 0, ?int $length = null): iterable; // iterable<string>
}

new LocalFileSource(string $path);                                      // throws FileResponseException when missing/unreadable; path(): string
new FilesystemFileSource(FilesystemInterface $filesystem, string $path); // needs marko/filesystem
```

Both read through `Marko\Routing\File\ResourceChunkReader`, which reads a byte range of a stream resource in 64 KB chunks and closes it.

### ByteRange and ConditionalRequest

```php
use Marko\Routing\Http\ByteRange;
use Marko\Routing\Http\ConditionalRequest;

public static function fromHeader(?string $range, int $size): ?ByteRange; // null: no header, other unit, multi-range
public function start(): int;
public function end(): int;                      // inclusive
public function length(): int;
public function contentRange(int $size): string; // "bytes 0-99/1234"

public static function fromRequest(Request $request): ConditionalRequest;
public static function etagFor(int $size, int $lastModified): string; // W/"<size hex>-<mtime hex>"
public static function httpDate(int $timestamp): string;
public function isNotModified(string $etag, int $lastModified): bool;
public function allowsRange(string $etag, int $lastModified): bool;
```

### ContentDisposition

```php
use Marko\Routing\Http\ContentDisposition;
use Marko\Routing\Http\DispositionType;

public static function inline(?string $filename = null): ContentDisposition;
public static function attachment(?string $filename = null): ContentDisposition;
public static function fromHeader(string $value): ContentDisposition;
public function type(): DispositionType;       // DispositionType::Inline or DispositionType::Attachment
public function filename(): ?string;
public function toHeaderValue(): string;
```

### FileOffload

```php
use Marko\Routing\Http\FileOffload;

public function __construct(string $header, array $prefixMap = []); // 'X-Sendfile' or 'X-Accel-Redirect'
public function header(): string;
public function headerValueFor(string $path): string;
```

`RoutingConfig::fileOffload()` returns the configured `FileOffload`, or `null` when `routing.file_offload.header` is empty.

### Cookie

```php
use Marko\Routing\Http\Cookie;

public function __construct(
    string $name,
    string $value = '',
    ?int $expires = null,
    ?string $path = null,
    ?string $domain = null,
    bool $secure = false,
    bool $httpOnly = false,
    ?string $sameSite = null,
    ?int $maxAge = null,
)

public function name(): string;
public function value(): string;
public function expires(): ?int;
public function path(): ?string;
public function domain(): ?string;
public function secure(): bool;
public function httpOnly(): bool;
public function sameSite(): ?string;
public function maxAge(): ?int;
public function toSetCookieString(): string;
```

`value()` returns the raw value, before the URL-encoding applied in the `Set-Cookie` line.

`expires: null` or `expires: 0` omits the `Expires` attribute, producing a browser-session cookie. `maxAge` (seconds) adds `Max-Age=` after `Expires`; `maxAge()` returns it as given, and a zero or negative value is sent as `Max-Age=0`, which deletes the cookie. No `Expires` is derived from `maxAge`. The value passed to `toSetCookieString()` is `rawurlencode()`d. The constructor throws `CookieException` for an invalid cookie name (control characters, whitespace, or separator characters such as `( ) < > @ , ; : \ " / [ ] ? = { }`), and also throws when `sameSite` is `'None'` without `secure: true`.

### MiddlewareInterface

```php
interface MiddlewareInterface
{
    public function handle(Request $request, callable $next): Response;
}
```

### HttpException

```php
use Marko\Routing\Exceptions\HttpException;

public function __construct(
    int $statusCode,             // 400-599, otherwise InvalidArgumentException
    string $message = '',        // defaults to the reason phrase
    array $headers = [],
    array $data = [],            // extra client-safe body fields
    string $context = '',
    string $suggestion = '',
    ?Throwable $previous = null,
)

public static function badRequest(string $message = ''): self;
public static function unauthorized(string $message = ''): self;
public static function forbidden(string $message = ''): self;
public static function notFound(string $message = ''): self;
public static function methodNotAllowed(array $allowedMethods, string $message = ''): self;
public static function conflict(string $message = ''): self;
public static function tooManyRequests(?int $retryAfter = null, string $message = ''): self;
```

`HttpException` implements `Marko\Core\Exceptions\HttpExceptionInterface`:

```php
interface HttpExceptionInterface extends Throwable
{
    public function getStatusCode(): int;
    public function getHeaders(): array;       // array<string, string>
    public function getResponseData(): array;  // client-safe; `message` is the human-readable text
}
```

### ExceptionRenderer

```php
use Marko\Routing\Http\ExceptionRenderer;

public function __construct(?HttpExceptionResponderRegistry $responders = null);
public function render(HttpExceptionInterface $exception, Request $request): Response; // asks the responders first
public function wantsJson(Request $request): bool;
protected function renderJson(int $statusCode, array $data): Response;
protected function renderHtml(int $statusCode, array $data): Response;
```

`Marko\Routing\Http\HttpStatus::reasonPhrase(int $statusCode): string` returns the standard reason phrase (`419` → `Page Expired`).

### HttpExceptionResponderInterface

```php
use Marko\Routing\Http\HttpExceptionResponderInterface;

public function respond(HttpExceptionInterface $exception, Request $request): ?Response; // null passes
```

Register an implementation with `#[HttpExceptionResponder(priority: 0)]` (`Marko\Routing\Attributes\HttpExceptionResponder`).

### HttpExceptionResponderRegistry

The discovered responders, highest priority first. `RoutingBootstrapper` registers it in the container.

```php
use Marko\Routing\Http\HttpExceptionResponderRegistry;

public function responders(): array; // list<HttpExceptionResponderInterface>
public function respond(HttpExceptionInterface $exception, Request $request): ?Response;
```

### Parameter Resolution

The router binds each controller method parameter from, in order: a `Request` type hint → an input attribute (`#[FromQuery]`, `#[FromBody]`, `#[FromInput]`) → a route path param of the same name → the container, for class and interface types → the default value → `null` for a nullable type. Request input never binds a parameter without an attribute (see [Binding Request Input](#binding-request-input)). Values are converted strictly to `int`, `float`, `bool`, `string` or `array`, and a value that does not fit renders a `400`. A required attributed parameter with no value throws `InvalidRouteParameterException`, and a malformed JSON body throws `MalformedJsonException`; both implement `HttpExceptionInterface` and the pipeline renders them as a `400` response (see [Errors and HTTP Exceptions](#errors-and-http-exceptions)). Route path literals containing dots or other regex metacharacters are matched literally (via `preg_quote`). The raw path is matched first and each parameter value is URL-decoded once afterwards; a decoded value with a `/` (outside a catch-all), a `.`/`..` segment or a NUL byte does not match (see [Parameter Values and Filesystem Paths](#parameter-values-and-filesystem-paths)).
