<?php

declare(strict_types=1);

namespace Marko\Authentication\Tests\Unit\Guard;

use Marko\Authentication\Event\FailedLoginEvent;
use Marko\Authentication\Event\LoginEvent;
use Marko\Authentication\Event\LogoutEvent;
use Marko\Authentication\Guard\SessionGuard;
use Marko\Testing\Fake\FakeAuthenticatable;
use Marko\Testing\Fake\FakeEventDispatcher;
use Marko\Testing\Fake\FakeSession;
use Marko\Testing\Fake\FakeUserProvider;

test('it dispatches LoginEvent on successful login', function (): void {
    $session = new FakeSession();
    $session->start();
    $provider = new FakeUserProvider();
    $dispatcher = new FakeEventDispatcher();
    $user = new FakeAuthenticatable(id: 42);

    $guard = new SessionGuard(
        session: $session,
        provider: $provider,
        name: 'web',
        eventDispatcher: $dispatcher,
    );

    $guard->login($user);

    expect($dispatcher->dispatched)->toHaveCount(1);
    $event = $dispatcher->dispatched[0];
    assert($event instanceof LoginEvent);
    expect($event->user)->toBe($user);
});

test('it dispatches LoginEvent on successful attempt', function (): void {
    $session = new FakeSession();
    $session->start();
    $user = new FakeAuthenticatable(id: 42);
    $provider = new FakeUserProvider([42 => $user]);
    $dispatcher = new FakeEventDispatcher();

    $guard = new SessionGuard(
        session: $session,
        provider: $provider,
        name: 'web',
        eventDispatcher: $dispatcher,
    );

    $result = $guard->attempt(['email' => 'test@example.com', 'password' => 'secret']);

    expect($result)->toBeTrue()
        ->and($dispatcher->dispatched)->toHaveCount(1);
    $event = $dispatcher->dispatched[0];
    assert($event instanceof LoginEvent);
    expect($event->user)->toBe($user);
});

test('it dispatches LogoutEvent on logout', function (): void {
    $user = new FakeAuthenticatable(id: 42);
    $session = new FakeSession();
    $session->set('auth_web_user_id', 42);
    $provider = new FakeUserProvider([42 => $user]);
    $dispatcher = new FakeEventDispatcher();

    $guard = new SessionGuard(
        session: $session,
        provider: $provider,
        name: 'web',
        eventDispatcher: $dispatcher,
    );

    // Ensure user is logged in first
    expect($guard->check())->toBeTrue();

    // Logout
    $guard->logout();

    expect($dispatcher->dispatched)->toHaveCount(1);
    $event = $dispatcher->dispatched[0];
    assert($event instanceof LogoutEvent);
    expect($event->user)->toBe($user);
});

test('it dispatches FailedLoginEvent on failed attempt', function (): void {
    $session = new FakeSession();
    $session->start();
    $user = new FakeAuthenticatable(id: 42);
    // User found but credentials invalid
    $provider = new FakeUserProvider([42 => $user], fn () => false);
    $dispatcher = new FakeEventDispatcher();

    $guard = new SessionGuard(
        session: $session,
        provider: $provider,
        name: 'web',
        eventDispatcher: $dispatcher,
    );

    $result = $guard->attempt(['email' => 'test@example.com', 'password' => 'wrong']);

    expect($result)->toBeFalse()
        ->and($dispatcher->dispatched)->toHaveCount(1);
    $event = $dispatcher->dispatched[0];
    assert($event instanceof FailedLoginEvent);
    expect($event->credentials)->toBe(['email' => 'test@example.com']);
});

test('it includes guard name in events', function (): void {
    $session = new FakeSession();
    $session->start();
    $provider = new FakeUserProvider();
    $dispatcher = new FakeEventDispatcher();
    $user = new FakeAuthenticatable(id: 42);

    $guard = new SessionGuard(
        session: $session,
        provider: $provider,
        name: 'admin',
        eventDispatcher: $dispatcher,
    );

    $guard->login($user);

    expect($dispatcher->dispatched)->toHaveCount(1);
    $event = $dispatcher->dispatched[0];
    assert($event instanceof LoginEvent);
    expect($event->guard)->toBe('admin');
});

test('it includes remember flag in LoginEvent', function (): void {
    $session = new FakeSession();
    $session->start();
    $provider = new FakeUserProvider();
    $dispatcher = new FakeEventDispatcher();
    $user = new FakeAuthenticatable(id: 42);

    $guard = new SessionGuard(
        session: $session,
        provider: $provider,
        name: 'web',
        eventDispatcher: $dispatcher,
    );

    // Test with remember = false
    $guard->login($user);

    expect($dispatcher->dispatched)->toHaveCount(1);
    $event = $dispatcher->dispatched[0];
    assert($event instanceof LoginEvent);
    expect($event->remember)->toBeFalse();

    // Reset
    $dispatcher->clear();
    $guard->logout();

    // Clear logout event
    $dispatcher->clear();

    // Test with remember = true
    $guard->login($user, remember: true);

    expect($dispatcher->dispatched)->toHaveCount(1);
    $event = $dispatcher->dispatched[0];
    assert($event instanceof LoginEvent);
    expect($event->remember)->toBeTrue();
});

test('event dispatching is optional (no error if dispatcher missing)', function (): void {
    $session = new FakeSession();
    $session->start();
    $user = new FakeAuthenticatable(id: 42);
    $provider = new FakeUserProvider([42 => $user]);

    // Create guard without event dispatcher
    $guard = new SessionGuard(
        session: $session,
        provider: $provider,
        name: 'web',
        // No eventDispatcher provided
    );

    // These should not throw any errors
    $guard->login($user);
    expect($guard->check())->toBeTrue();

    $guard->logout();
    expect($guard->check())->toBeFalse();

    $result = $guard->attempt(['email' => 'test@example.com', 'password' => 'secret']);
    expect($result)->toBeTrue();

    // Also test failed attempt
    $session2 = new FakeSession();
    $session2->start();
    $provider2 = new FakeUserProvider([42 => $user], fn () => false);
    $guard2 = new SessionGuard(
        session: $session2,
        provider: $provider2,
        name: 'web',
    );

    $result2 = $guard2->attempt(['email' => 'test@example.com', 'password' => 'wrong']);
    expect($result2)->toBeFalse();
});
