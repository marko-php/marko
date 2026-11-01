<?php

declare(strict_types=1);

use Marko\Authentication\AuthenticatableInterface;
use Marko\Authentication\Config\AuthConfig;
use Marko\Authentication\EmailVerification\CurrentVerifiableUser;
use Marko\Authentication\Exceptions\AuthException;
use Marko\Authentication\Exceptions\EmailNotVerifiedException;
use Marko\Authentication\Exceptions\UnauthenticatedException;
use Marko\Authentication\Middleware\EnsureEmailVerifiedMiddleware;
use Marko\Authentication\Tests\Fixtures\VerifiableUser;
use Marko\Routing\Http\Request;
use Marko\Routing\Http\Response;
use Marko\Testing\Fake\FakeAuthenticatable;
use Marko\Testing\Fake\FakeConfigRepository;
use Marko\Testing\Fake\FakeGuard;

function emailVerifiedMiddleware(
    FakeGuard $guard,
): EnsureEmailVerifiedMiddleware {
    return new EnsureEmailVerifiedMiddleware(
        currentUser: new CurrentVerifiableUser($guard),
        authConfig: new AuthConfig(new FakeConfigRepository([
            'authentication.verification.notice_route' => '/email/verify',
        ])),
    );
}

function guardWithUser(
    ?AuthenticatableInterface $user,
): FakeGuard {
    $guard = new FakeGuard(name: 'session');
    $guard->setUser($user);

    return $guard;
}

function okNext(): Closure
{
    return fn (Request $request): Response => new Response('ok');
}

it('lets a verified user through', function (): void {
    $user = new VerifiableUser(id: 1, email: 'ada@example.com', emailVerifiedAt: new DateTimeImmutable());

    $response = emailVerifiedMiddleware(guardWithUser($user))->handle(new Request(), okNext());

    expect($response->body())->toBe('ok');
});

it('redirects an unverified browser request to the verification notice route', function (): void {
    $user = new VerifiableUser(id: 1, email: 'ada@example.com');

    $response = emailVerifiedMiddleware(guardWithUser($user))->handle(new Request(), okNext());

    expect($response->statusCode())->toBe(302)
        ->and($response->headers()['Location'])->toBe('/email/verify');
});

it('throws a 403 EmailNotVerifiedException for an unverified JSON request', function (): void {
    $user = new VerifiableUser(id: 1, email: 'ada@example.com');
    $request = new Request(server: ['HTTP_ACCEPT' => 'application/json']);

    try {
        emailVerifiedMiddleware(guardWithUser($user))->handle($request, okNext());
        $this->fail('Expected EmailNotVerifiedException');
    } catch (EmailNotVerifiedException $exception) {
        expect($exception->getStatusCode())->toBe(403)
            ->and($exception->getMessage())->toBe('Your email address is not verified.');
    }
});

it('throws a configuration error for a user that does not implement MustVerifyEmailInterface', function (): void {
    $middleware = emailVerifiedMiddleware(guardWithUser(new FakeAuthenticatable(id: 1)));

    expect(fn () => $middleware->handle(new Request(), okNext()))
        ->toThrow(AuthException::class, 'does not implement Marko\Authentication\MustVerifyEmailInterface');
});

it('throws UnauthenticatedException when no user is logged in', function (): void {
    $middleware = emailVerifiedMiddleware(guardWithUser(null));

    expect(fn () => $middleware->handle(new Request(), okNext()))->toThrow(UnauthenticatedException::class);
});
