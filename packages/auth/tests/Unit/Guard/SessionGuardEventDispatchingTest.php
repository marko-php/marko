<?php

declare(strict_types=1);

namespace Marko\Auth\Tests\Unit\Guard;

use Marko\Auth\Event\FailedLoginEvent;
use Marko\Auth\Event\LoginEvent;
use Marko\Auth\Event\LogoutEvent;
use Marko\Auth\Guard\SessionGuard;
use Marko\Auth\Tests\Integration\TestEventDispatcher;
use Marko\Auth\Tests\Integration\TestSession;
use Marko\Auth\Tests\Integration\TestUser;
use Marko\Auth\Tests\Integration\TestUserProvider;

test('it dispatches LoginEvent on successful login', function (): void {
    $session = new TestSession();
    $provider = new TestUserProvider();
    $dispatcher = new TestEventDispatcher();
    $user = new TestUser(id: 42);

    $guard = new SessionGuard(
        session: $session,
        provider: $provider,
        name: 'web',
        eventDispatcher: $dispatcher,
    );

    $guard->login($user);

    expect($dispatcher->events)->toHaveCount(1);
    $event = $dispatcher->events[0];
    assert($event instanceof LoginEvent);
    expect($event->user)->toBe($user);
});

test('it dispatches LoginEvent on successful attempt', function (): void {
    $session = new TestSession();
    $user = new TestUser(id: 42);
    $provider = new TestUserProvider(userByCredentials: $user, credentialsValid: true);
    $dispatcher = new TestEventDispatcher();

    $guard = new SessionGuard(
        session: $session,
        provider: $provider,
        name: 'web',
        eventDispatcher: $dispatcher,
    );

    $result = $guard->attempt(['email' => 'test@example.com', 'password' => 'secret']);

    expect($result)->toBeTrue()
        ->and($dispatcher->events)->toHaveCount(1);
    $event = $dispatcher->events[0];
    assert($event instanceof LoginEvent);
    expect($event->user)->toBe($user);
});

test('it dispatches LogoutEvent on logout', function (): void {
    $user = new TestUser(id: 42);
    $session = new TestSession();
    $session->set('auth_user_id', 42);
    $provider = new TestUserProvider(userById: $user);
    $dispatcher = new TestEventDispatcher();

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

    expect($dispatcher->events)->toHaveCount(1);
    $event = $dispatcher->events[0];
    assert($event instanceof LogoutEvent);
    expect($event->user)->toBe($user);
});

test('it dispatches FailedLoginEvent on failed attempt', function (): void {
    $session = new TestSession();
    $user = new TestUser(id: 42);
    // User found but credentials invalid
    $provider = new TestUserProvider(userByCredentials: $user);
    $dispatcher = new TestEventDispatcher();

    $guard = new SessionGuard(
        session: $session,
        provider: $provider,
        name: 'web',
        eventDispatcher: $dispatcher,
    );

    $result = $guard->attempt(['email' => 'test@example.com', 'password' => 'wrong']);

    expect($result)->toBeFalse()
        ->and($dispatcher->events)->toHaveCount(1);
    $event = $dispatcher->events[0];
    assert($event instanceof FailedLoginEvent);
    expect($event->credentials)->toBe(['email' => 'test@example.com']);
});

test('it includes guard name in events', function (): void {
    $session = new TestSession();
    $provider = new TestUserProvider();
    $dispatcher = new TestEventDispatcher();
    $user = new TestUser(id: 42);

    $guard = new SessionGuard(
        session: $session,
        provider: $provider,
        name: 'admin',
        eventDispatcher: $dispatcher,
    );

    $guard->login($user);

    expect($dispatcher->events)->toHaveCount(1);
    $event = $dispatcher->events[0];
    assert($event instanceof LoginEvent);
    expect($event->guard)->toBe('admin');
});

test('it includes remember flag in LoginEvent', function (): void {
    $session = new TestSession();
    $provider = new TestUserProvider();
    $dispatcher = new TestEventDispatcher();
    $user = new TestUser(id: 42);

    $guard = new SessionGuard(
        session: $session,
        provider: $provider,
        name: 'web',
        eventDispatcher: $dispatcher,
    );

    // Test with remember = false
    $guard->login($user);

    expect($dispatcher->events)->toHaveCount(1);
    $event = $dispatcher->events[0];
    assert($event instanceof LoginEvent);
    expect($event->remember)->toBeFalse();

    // Reset
    $dispatcher->clear();
    $guard->logout();

    // Clear logout event
    $dispatcher->clear();

    // Test with remember = true
    $guard->login($user, remember: true);

    expect($dispatcher->events)->toHaveCount(1);
    $event = $dispatcher->events[0];
    assert($event instanceof LoginEvent);
    expect($event->remember)->toBeTrue();
});

test('event dispatching is optional (no error if dispatcher missing)', function (): void {
    $session = new TestSession();
    $user = new TestUser(id: 42);
    $provider = new TestUserProvider(userByCredentials: $user, credentialsValid: true, userById: $user);

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
    $provider2 = new TestUserProvider(userByCredentials: $user);
    $guard2 = new SessionGuard(
        session: new TestSession(),
        provider: $provider2,
        name: 'web',
    );

    $result2 = $guard2->attempt(['email' => 'test@example.com', 'password' => 'wrong']);
    expect($result2)->toBeFalse();
});
