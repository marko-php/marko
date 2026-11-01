<?php

declare(strict_types=1);

namespace Marko\Forms\Tests\Feature\ValidationRedirectRouting;

use Marko\Config\ConfigRepositoryInterface;
use Marko\Core\Container\Container;
use Marko\Core\Container\ContainerInterface;
use Marko\Core\Container\PreferenceRegistry;
use Marko\Core\Discovery\ClassFileParser;
use Marko\Core\Module\ModuleManifest;
use Marko\Forms\SessionFlashedFormState;
use Marko\Routing\Http\Request;
use Marko\Routing\Http\Response;
use Marko\Routing\Middleware\MiddlewareInterface;
use Marko\Routing\RouteCollection;
use Marko\Routing\RouteDefinition;
use Marko\Routing\Router;
use Marko\Routing\RoutingBootstrapper;
use Marko\Session\Contracts\FlashDataInterface;
use Marko\Session\Contracts\SessionInterface;
use Marko\Session\Flash\SessionFlashData;
use Marko\Testing\Fake\FakeConfigRepository;
use Marko\Testing\Fake\FakeSession;
use Marko\Validation\Exceptions\ValidationException;
use Marko\Validation\Validation\ValidationErrors;

class RegisterController
{
    public function store(): Response
    {
        throw ValidationException::withErrors(new ValidationErrors([
            'email' => ['The email field must be a valid email address.'],
        ]));
    }
}

/**
 * Stands in for an outer global middleware (SessionMiddleware, CORS) that must
 * still see the response built from the controller's exception.
 */
class StampsResponseMiddleware implements MiddlewareInterface
{
    public function handle(
        Request $request,
        callable $next,
    ): Response {
        return $next($request)->withHeader('X-Outer', 'seen');
    }
}

/**
 * Boot the real routing stack with marko/forms as a discovered module.
 */
function formsRouter(
    FakeSession $session,
): Router {
    $preferenceRegistry = new PreferenceRegistry();
    $container = new Container($preferenceRegistry);
    $container->instance(ContainerInterface::class, $container);
    $container->instance(SessionInterface::class, $session);
    $container->bind(FlashDataInterface::class, SessionFlashData::class);
    $container->instance(ConfigRepositoryInterface::class, new FakeConfigRepository([
        'forms.never_flash' => ['password'],
        'forms.fallback_redirect' => '/',
    ]));

    $router = new RoutingBootstrapper(
        modules: [new ModuleManifest(name: 'marko/forms', version: '1.0.0', path: dirname(__DIR__, 2))],
        container: $container,
        preferenceRegistry: $preferenceRegistry,
        classFileParser: new ClassFileParser(),
    )->boot([StampsResponseMiddleware::class]);

    $container->get(RouteCollection::class)->add(new RouteDefinition(
        method: 'POST',
        path: '/register',
        controller: RegisterController::class,
        action: 'store',
    ));

    return $router;
}

/**
 * @param array<string, string> $headers HTTP_* server keys
 */
function registerPost(
    array $headers = [],
): Request {
    return new Request(
        server: [
            'REQUEST_METHOD' => 'POST',
            'REQUEST_URI' => '/register',
            'HTTP_HOST' => 'example.com',
            ...$headers,
        ],
        post: ['name' => 'Ann', 'email' => 'not-an-email', 'password' => 'hunter2'],
    );
}

describe('a failed form post through Router::handle()', function (): void {
    it('redirects an HTML post back with the errors and old input flashed', function (): void {
        $session = new FakeSession();
        $session->start();

        $response = formsRouter($session)->handle(registerPost(['HTTP_REFERER' => 'https://example.com/register']));
        $session->save();
        $session->start();
        $state = new SessionFlashedFormState(new SessionFlashData($session), $session);

        expect($response->statusCode())->toBe(303)
            ->and($response->headers()['Location'])->toBe('/register')
            ->and($response->headers()['X-Outer'])->toBe('seen')
            ->and($state->errors()->all())->toBe(['email' => ['The email field must be a valid email address.']])
            ->and($state->old('name'))->toBe('Ann')
            ->and($state->old('email'))->toBe('not-an-email')
            ->and($state->old('password'))->toBeNull();
    });

    it('returns the 422 JSON response for a post that wants JSON', function (): void {
        $session = new FakeSession();
        $session->start();

        $response = formsRouter($session)->handle(registerPost(['HTTP_ACCEPT' => 'application/json']));
        $session->save();
        $session->start();
        $state = new SessionFlashedFormState(new SessionFlashData($session), $session);

        expect($response->statusCode())->toBe(422)
            ->and($response->headers()['X-Outer'])->toBe('seen')
            ->and(json_decode($response->body(), true)['errors'])
            ->toBe(['email' => ['The email field must be a valid email address.']])
            ->and($state->hasErrors())->toBeFalse();
    });
});
