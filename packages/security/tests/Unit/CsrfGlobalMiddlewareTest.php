<?php

declare(strict_types=1);

namespace Marko\Security\Tests\Unit\CsrfGlobalMiddleware;

use Marko\Core\Container\Container;
use Marko\Core\Container\PreferenceRegistry;
use Marko\Routing\Http\Request;
use Marko\Routing\Http\Response;
use Marko\Routing\Middleware\MiddlewareInterface;
use Marko\Routing\RouteCollection;
use Marko\Routing\RouteDefinition;
use Marko\Routing\RouteMatcher;
use Marko\Routing\Router;
use Marko\Security\Contracts\CsrfTokenManagerInterface;
use Marko\Security\Middleware\CsrfMiddleware;
use Marko\Security\Middleware\RouteCsrfMiddleware;
use Marko\Security\Tests\Helpers;

class GlobalStoredTokenManager implements CsrfTokenManagerInterface
{
    public function get(): string
    {
        return 'stored-token';
    }

    public function validate(
        string $token,
    ): bool {
        return hash_equals('stored-token', $token);
    }

    public function regenerate(): string
    {
        return 'stored-token';
    }
}

/**
 * @noinspection PhpUnused - Actions invoked by the router
 */
class GlobalCsrfController
{
    public function show(): Response
    {
        return new Response('Shown');
    }

    public function update(): Response
    {
        return new Response('Updated');
    }

    public function webhook(): Response
    {
        return new Response('Received');
    }
}

/**
 * Stands in for an auth guard: a visitor whose session expired is sent to
 * the login page.
 */
class RedirectGuestMiddleware implements MiddlewareInterface
{
    public function handle(
        Request $request,
        callable $next,
    ): Response {
        return Response::redirect('/login');
    }
}

class PassThroughMiddleware implements MiddlewareInterface
{
    public function handle(
        Request $request,
        callable $next,
    ): Response {
        return $next($request);
    }
}

/**
 * A router wired the way marko/security's module.php wires it: CsrfMiddleware
 * is global and no route names it in #[Middleware].
 */
function globalCsrfRouter(): Router
{
    $routes = new RouteCollection();
    $routes->add(new RouteDefinition(
        method: 'GET',
        path: '/account',
        controller: GlobalCsrfController::class,
        action: 'show',
    ));
    $routes->add(new RouteDefinition(
        method: 'POST',
        path: '/account/email',
        controller: GlobalCsrfController::class,
        action: 'update',
    ));
    $routes->add(new RouteDefinition(
        method: 'POST',
        path: '/webhooks/stripe',
        controller: GlobalCsrfController::class,
        action: 'webhook',
        withoutMiddleware: [CsrfMiddleware::class],
    ));
    $routes->add(new RouteDefinition(
        method: 'POST',
        path: '/admin/settings',
        controller: GlobalCsrfController::class,
        action: 'update',
        middleware: [RedirectGuestMiddleware::class],
    ));
    $routes->add(new RouteDefinition(
        method: 'POST',
        path: '/profile/name',
        controller: GlobalCsrfController::class,
        action: 'update',
        middleware: [PassThroughMiddleware::class],
    ));
    $routes->add(new RouteDefinition(
        method: 'POST',
        path: '/contact',
        controller: GlobalCsrfController::class,
        action: 'update',
        middleware: [RouteCsrfMiddleware::class, RedirectGuestMiddleware::class],
        withoutMiddleware: [CsrfMiddleware::class],
    ));

    $container = new Container(new PreferenceRegistry());
    $container->instance(CsrfMiddleware::class, Helpers::createCsrfMiddleware(new GlobalStoredTokenManager()));
    $container->instance(RouteCsrfMiddleware::class, Helpers::createCsrfMiddleware(
        new GlobalStoredTokenManager(),
        middlewareClass: RouteCsrfMiddleware::class,
    ));
    $container->instance(GlobalCsrfController::class, new GlobalCsrfController());

    return new Router(
        matcher: new RouteMatcher($routes),
        container: $container,
        globalMiddleware: [CsrfMiddleware::class],
    );
}

/**
 * @param array<string, string> $headers
 */
function globalPost(
    string $uri,
    array $headers = [],
): Request {
    return new Request(server: array_merge([
        'REQUEST_METHOD' => 'POST',
        'REQUEST_URI' => $uri,
        'HTTP_ACCEPT' => 'application/json',
    ], $headers));
}

describe('CsrfMiddleware as global middleware', function (): void {
    it('rejects a POST without a token on a route that never opted in with 419', function (): void {
        $response = globalCsrfRouter()->handle(globalPost('/account/email'));

        expect($response->statusCode())->toBe(419);
    });

    it('accepts a POST carrying the token in the X-XSRF-TOKEN header', function (): void {
        $response = globalCsrfRouter()->handle(globalPost('/account/email', ['HTTP_X_XSRF_TOKEN' => 'stored-token']));

        expect($response->statusCode())->toBe(200)
            ->and($response->body())->toBe('Updated');
    });

    it('accepts a POST carrying the token in the X-CSRF-TOKEN header', function (): void {
        $response = globalCsrfRouter()->handle(globalPost('/account/email', ['HTTP_X_CSRF_TOKEN' => 'stored-token']));

        expect($response->statusCode())->toBe(200);
    });

    it('lets a route exempted with WithoutMiddleware accept a POST without a token', function (): void {
        $response = globalCsrfRouter()->handle(globalPost('/webhooks/stripe'));

        expect($response->statusCode())->toBe(200)
            ->and($response->body())->toBe('Received');
    });

    it('lets a route middleware answer a POST without a token before the token is checked', function (): void {
        $response = globalCsrfRouter()->handle(globalPost('/admin/settings'));

        expect($response->statusCode())->toBe(302)
            ->and($response->headers()['Location'] ?? null)->toBe('/login');
    });

    it('rejects a POST without a token once the route middleware lets it through', function (): void {
        $response = globalCsrfRouter()->handle(globalPost('/profile/name'));

        expect($response->statusCode())->toBe(419);
    });

    it('accepts a POST with a token on a route that has its own middleware', function (): void {
        $response = globalCsrfRouter()->handle(globalPost('/profile/name', ['HTTP_X_CSRF_TOKEN' => 'stored-token']));

        expect($response->statusCode())->toBe(200)
            ->and($response->body())->toBe('Updated');
    });

    it('checks the token first on a route that lists a CsrfMiddleware subclass', function (): void {
        $response = globalCsrfRouter()->handle(globalPost('/contact'));

        expect($response->statusCode())->toBe(419);
    });

    it('issues the XSRF-TOKEN cookie on a GET that resumes a session', function (): void {
        $response = globalCsrfRouter()->handle(new Request(
            server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/account'],
            cookies: Helpers::resumedSessionCookies(),
        ));

        expect($response->statusCode())->toBe(200)
            ->and(Helpers::findXsrfCookie($response)?->value())->toBe('stored-token');
    });
});
