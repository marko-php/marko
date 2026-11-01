<?php

declare(strict_types=1);

use Marko\Authentication\Event\LoginEvent;
use Marko\Authentication\Event\LogoutEvent;
use Marko\Authentication\Observer\ForgetPasswordConfirmationOnLogin;
use Marko\Authentication\Observer\ForgetPasswordConfirmationOnLogout;
use Marko\Core\Attributes\Observer;
use Marko\Testing\Fake\FakeAuthenticatable;
use Marko\Testing\Fake\FakeSession;

it('observes the login and logout events', function (string $observer, string $event): void {
    $attributes = new ReflectionClass($observer)->getAttributes(Observer::class);

    expect($attributes)->toHaveCount(1)
        ->and($attributes[0]->newInstance()->event)->toBe($event);
})->with([
    [ForgetPasswordConfirmationOnLogin::class, LoginEvent::class],
    [ForgetPasswordConfirmationOnLogout::class, LogoutEvent::class],
]);

it('forgets the password confirmation on login', function (): void {
    $session = new FakeSession();
    $session->start();
    $session->set('auth.password_confirmed_at', 1_780_000_000);

    new ForgetPasswordConfirmationOnLogin($session)->handle(
        new LoginEvent(user: new FakeAuthenticatable(), guard: 'session'),
    );

    expect($session->has('auth.password_confirmed_at'))->toBeFalse();
});

it('forgets the password confirmation on logout', function (): void {
    $session = new FakeSession();
    $session->start();
    $session->set('auth.password_confirmed_at', 1_780_000_000);

    new ForgetPasswordConfirmationOnLogout($session)->handle(
        new LogoutEvent(user: new FakeAuthenticatable(), guard: 'session'),
    );

    expect($session->has('auth.password_confirmed_at'))->toBeFalse();
});
