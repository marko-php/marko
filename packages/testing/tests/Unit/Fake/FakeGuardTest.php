<?php

declare(strict_types=1);

use Marko\Testing\Exceptions\AssertionFailedException;
use Marko\Testing\Fake\FakeAuthenticatable;
use Marko\Testing\Fake\FakeGuard;

it('starts with no authenticated user', function (): void {
    $guard = new FakeGuard();

    expect($guard->user())->toBeNull()
        ->and($guard->check())->toBeFalse()
        ->and($guard->guest())->toBeTrue()
        ->and($guard->id())->toBeNull();
});

it('tracks user state via login and logout', function (): void {
    $guard = new FakeGuard();
    $user = new FakeAuthenticatable(id: 42);

    $guard->login($user);

    expect($guard->user())->toBe($user)
        ->and($guard->check())->toBeTrue()
        ->and($guard->guest())->toBeFalse()
        ->and($guard->id())->toBe(42);

    $guard->logout();

    expect($guard->user())->toBeNull()
        ->and($guard->check())->toBeFalse()
        ->and($guard->guest())->toBeTrue()
        ->and($guard->id())->toBeNull();
});

it('records attempt calls with credentials', function (): void {
    $guard = new FakeGuard();

    $guard->attempt(['email' => 'test@example.com', 'password' => 'secret']);
    $guard->attempt(['email' => 'other@example.com', 'password' => 'pass']);

    expect($guard->attempts)->toHaveCount(2)
        ->and($guard->attempts[0])->toBe(['email' => 'test@example.com', 'password' => 'secret'])
        ->and($guard->attempts[1])->toBe(['email' => 'other@example.com', 'password' => 'pass']);
});

it('returns configurable attempt result', function (): void {
    $guard = new FakeGuard(attemptResult: false);

    $result = $guard->attempt(['email' => 'test@example.com', 'password' => 'wrong']);

    expect($result)->toBeFalse();

    $guard->setAttemptResult(true);

    $result = $guard->attempt(['email' => 'test@example.com', 'password' => 'correct']);

    expect($result)->toBeTrue();
});

it('tracks logout calls', function (): void {
    $guard = new FakeGuard();
    $user = new FakeAuthenticatable();

    $guard->login($user);

    expect($guard->logoutCalled)->toBeFalse();

    $guard->logout();

    expect($guard->logoutCalled)->toBeTrue();
});

it('returns configured guard name', function (): void {
    $guard = new FakeGuard(name: 'api');

    expect($guard->getName())->toBe('api');
});

it('uses default name test when none provided', function (): void {
    $guard = new FakeGuard();

    expect($guard->getName())->toBe('test');
});

it('resets all state on clear', function (): void {
    $guard = new FakeGuard();
    $user = new FakeAuthenticatable();

    $guard->login($user);
    $guard->attempt(['email' => 'test@example.com', 'password' => 'secret']);
    $guard->logout();

    $guard->clear();

    expect($guard->user())->toBeNull()
        ->and($guard->attempts)->toBe([])
        ->and($guard->logoutCalled)->toBeFalse();
});

it('asserts authenticated when user is set', function (): void {
    $guard = new FakeGuard();
    $user = new FakeAuthenticatable();
    $guard->login($user);

    expect(fn () => $guard->assertAuthenticated())->not->toThrow(AssertionFailedException::class);
});

it('throws when asserting authenticated with no user', function (): void {
    $guard = new FakeGuard();

    expect(fn () => $guard->assertAuthenticated())->toThrow(AssertionFailedException::class);
});

it('asserts guest when no user is set', function (): void {
    $guard = new FakeGuard();

    expect(fn () => $guard->assertGuest())->not->toThrow(AssertionFailedException::class);
});

it('throws when asserting guest with user set', function (): void {
    $guard = new FakeGuard();
    $user = new FakeAuthenticatable();
    $guard->login($user);

    expect(fn () => $guard->assertGuest())->toThrow(AssertionFailedException::class);
});

it('asserts attempted when attempts were made', function (): void {
    $guard = new FakeGuard();
    $guard->attempt(['email' => 'test@example.com', 'password' => 'secret']);

    expect(fn () => $guard->assertAttempted())->not->toThrow(AssertionFailedException::class);
});

it('asserts attempted with callback filter', function (): void {
    $guard = new FakeGuard();
    $guard->attempt(['email' => 'test@example.com', 'password' => 'secret']);

    expect(fn () => $guard->assertAttempted(
        fn (array $credentials) => $credentials['email'] === 'test@example.com',
    ))->not->toThrow(AssertionFailedException::class);

    expect(fn () => $guard->assertAttempted(
        fn (array $credentials) => $credentials['email'] === 'other@example.com',
    ))->toThrow(AssertionFailedException::class);
});

it('throws when asserting attempted with no attempts', function (): void {
    $guard = new FakeGuard();

    expect(fn () => $guard->assertAttempted())->toThrow(AssertionFailedException::class);
});

it('asserts not attempted when no attempts exist', function (): void {
    $guard = new FakeGuard();

    expect(fn () => $guard->assertNotAttempted())->not->toThrow(AssertionFailedException::class);
});

it('throws when asserting not attempted after attempt', function (): void {
    $guard = new FakeGuard();
    $guard->attempt(['email' => 'test@example.com', 'password' => 'secret']);

    expect(fn () => $guard->assertNotAttempted())->toThrow(AssertionFailedException::class);
});

it('asserts logged out when logout was called', function (): void {
    $guard = new FakeGuard();
    $user = new FakeAuthenticatable();
    $guard->login($user);
    $guard->logout();

    expect(fn () => $guard->assertLoggedOut())->not->toThrow(AssertionFailedException::class);
});

it('throws when asserting logged out without logout call', function (): void {
    $guard = new FakeGuard();

    expect(fn () => $guard->assertLoggedOut())->toThrow(AssertionFailedException::class);
});

it('provides toHaveAttempted expectation', function (): void {
    $guard = new FakeGuard();
    $guard->attempt(['email' => 'test@example.com', 'password' => 'secret']);

    expect($guard)->toHaveAttempted();
});

it('provides toBeAuthenticated expectation', function (): void {
    $guard = new FakeGuard();
    $user = new FakeAuthenticatable();
    $guard->login($user);

    expect($guard)->toBeAuthenticated();
});

it('rejects non-FakeGuard for toHaveAttempted', function (): void {
    $notAGuard = new stdClass();

    expect(fn () => expect($notAGuard)->toHaveAttempted())
        ->toThrow(InvalidArgumentException::class, 'Expected FakeGuard');
});

it('rejects non-FakeGuard for toBeAuthenticated', function (): void {
    $notAGuard = new stdClass();

    expect(fn () => expect($notAGuard)->toBeAuthenticated())
        ->toThrow(InvalidArgumentException::class, 'Expected FakeGuard');
});
