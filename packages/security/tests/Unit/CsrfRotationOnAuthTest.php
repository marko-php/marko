<?php

declare(strict_types=1);

use Marko\Authentication\Event\LoginEvent;
use Marko\Authentication\Event\LogoutEvent;
use Marko\Authentication\Guard\SessionGuard;
use Marko\Core\Attributes\Observer;
use Marko\Core\Container\Container;
use Marko\Core\Event\EventDispatcher;
use Marko\Core\Event\ObserverDefinition;
use Marko\Core\Event\ObserverRegistry;
use Marko\Routing\Http\Request;
use Marko\Routing\Http\Response;
use Marko\Routing\Middleware\MiddlewareInterface;
use Marko\Routing\RouteCollection;
use Marko\Routing\RouteDefinition;
use Marko\Routing\RouteMatcher;
use Marko\Routing\Router;
use Marko\Security\Contracts\CsrfTokenManagerInterface;
use Marko\Security\CsrfTokenManager;
use Marko\Security\Middleware\CsrfMiddleware;
use Marko\Security\Observer\RotateCsrfTokenOnLogin;
use Marko\Security\Observer\RotateCsrfTokenOnLogout;
use Marko\Security\Tests\Helpers;
use Marko\Testing\Fake\FakeAuthenticatable;
use Marko\Testing\Fake\FakeEncryptor;
use Marko\Testing\Fake\FakeSession;
use Marko\Testing\Fake\FakeUserProvider;

function csrfRotationManager(
    FakeSession $session,
): CsrfTokenManager {
    return new CsrfTokenManager($session, new FakeEncryptor());
}

/**
 * A SessionGuard whose events reach the security package's CSRF observers,
 * wired the way observer discovery registers them.
 */
function csrfRotatingGuard(
    FakeSession $session,
    CsrfTokenManager $csrf,
): SessionGuard {
    $container = new Container();
    $container->instance(CsrfTokenManagerInterface::class, $csrf);

    $registry = new ObserverRegistry();
    $registry->register(new ObserverDefinition(
        observerClass: RotateCsrfTokenOnLogin::class,
        eventClass: LoginEvent::class,
    ));
    $registry->register(new ObserverDefinition(
        observerClass: RotateCsrfTokenOnLogout::class,
        eventClass: LogoutEvent::class,
    ));

    return new SessionGuard(
        session: $session,
        provider: new FakeUserProvider([42 => new FakeAuthenticatable(id: 42)]),
        name: 'web',
        eventDispatcher: new EventDispatcher($container, $registry),
    );
}

/**
 * Logs the visitor in on the way to the controller, the way a remember-me
 * cookie does when a route's auth middleware checks the guard.
 */
class LoginOnTheWayMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly SessionGuard $guard,
    ) {}

    public function handle(
        Request $request,
        callable $next,
    ): Response {
        $this->guard->login(new FakeAuthenticatable(id: 42));

        return $next($request);
    }
}

/**
 * @noinspection PhpUnused - Action invoked by the router
 */
class RotationCsrfController
{
    public function update(): Response
    {
        return new Response('Updated');
    }
}

describe('CSRF token rotation on login and logout', function (): void {
    it('observes LoginEvent and LogoutEvent', function (): void {
        $loginObserver = new ReflectionClass(RotateCsrfTokenOnLogin::class)
            ->getAttributes(Observer::class)[0]->newInstance();
        $logoutObserver = new ReflectionClass(RotateCsrfTokenOnLogout::class)
            ->getAttributes(Observer::class)[0]->newInstance();

        expect($loginObserver->event)->toBe(LoginEvent::class)
            ->and($logoutObserver->event)->toBe(LogoutEvent::class);
    });

    it('invalidates a CSRF token issued before login', function (): void {
        $session = new FakeSession();
        $session->start();
        $csrf = csrfRotationManager($session);
        $preLoginToken = $csrf->get();

        csrfRotatingGuard($session, $csrf)->login(new FakeAuthenticatable(id: 42));

        expect($csrf->validate($preLoginToken))->toBeFalse()
            ->and($csrf->get())->not->toBe($preLoginToken);
    });

    it('accepts the token a POST arrived with when route middleware logs the visitor in', function (): void {
        $session = Helpers::createResumedSession();
        $tokenFromThePage = csrfRotationManager($session)->get();
        $csrf = csrfRotationManager($session);

        $routes = new RouteCollection();
        $routes->add(new RouteDefinition(
            method: 'POST',
            path: '/account/email',
            controller: RotationCsrfController::class,
            action: 'update',
            middleware: [LoginOnTheWayMiddleware::class],
        ));
        $container = new Container();
        $container->instance(
            LoginOnTheWayMiddleware::class,
            new LoginOnTheWayMiddleware(csrfRotatingGuard($session, $csrf)),
        );
        $container->instance(CsrfMiddleware::class, Helpers::createCsrfMiddleware($csrf, $session));
        $container->instance(RotationCsrfController::class, new RotationCsrfController());
        $router = new Router(
            matcher: new RouteMatcher($routes),
            container: $container,
            globalMiddleware: [CsrfMiddleware::class],
        );

        $response = $router->handle(new Request(
            server: [
                'REQUEST_METHOD' => 'POST',
                'REQUEST_URI' => '/account/email',
                'HTTP_ACCEPT' => 'application/json',
                'HTTP_X_CSRF_TOKEN' => $tokenFromThePage,
            ],
            cookies: Helpers::resumedSessionCookies(),
        ));

        expect($response->statusCode())->toBe(200)
            ->and(Helpers::findXsrfCookie($response)?->value())->toBe($csrf->get())
            ->and($csrf->get())->not->toBe($tokenFromThePage);
    });

    it('invalidates the logged-in CSRF token on logout', function (): void {
        $session = new FakeSession();
        $session->start();
        $csrf = csrfRotationManager($session);
        $guard = csrfRotatingGuard($session, $csrf);
        $guard->login(new FakeAuthenticatable(id: 42));
        $loggedInToken = $csrf->get();

        $guard->logout();

        expect($csrf->validate($loggedInToken))->toBeFalse()
            ->and($csrf->get())->not->toBe($loggedInToken);
    });
});
