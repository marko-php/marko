<?php

declare(strict_types=1);

use Marko\Authentication\Contracts\UserPersisterInterface;
use Marko\Testing\Exceptions\AssertionFailedException;
use Marko\Testing\Fake\FakeAuthenticatable;
use Marko\Testing\Fake\FakeUserPersister;

it('implements UserPersisterInterface', function (): void {
    expect(new FakeUserPersister())->toBeInstanceOf(UserPersisterInterface::class);
});

it('records every persisted user in order, including repeats', function (): void {
    $persister = new FakeUserPersister();
    $first = new FakeAuthenticatable(id: 1);
    $second = new FakeAuthenticatable(id: 2);

    $persister->persist($first);
    $persister->persist($second);
    $persister->persist($first);

    expect($persister->persisted())->toBe([$first, $second, $first]);
});

it('starts with nothing persisted', function (): void {
    expect((new FakeUserPersister())->persisted())->toBe([]);
});

it('passes assertPersisted when the user was persisted', function (): void {
    $persister = new FakeUserPersister();
    $user = new FakeAuthenticatable(id: 7);
    $persister->persist($user);

    $persister->assertPersisted($user);

    expect(true)->toBeTrue();
});

it('matches a reloaded user by class and auth identifier in assertPersisted', function (): void {
    $persister = new FakeUserPersister();
    $persister->persist(new FakeAuthenticatable(id: '7'));

    $persister->assertPersisted(new FakeAuthenticatable(id: 7));

    expect(true)->toBeTrue();
});

it('fails assertPersisted when the user was not persisted', function (): void {
    $persister = new FakeUserPersister();
    $persister->persist(new FakeAuthenticatable(id: 1));

    expect(fn () => $persister->assertPersisted(new FakeAuthenticatable(id: 2)))
        ->toThrow(AssertionFailedException::class, FakeAuthenticatable::class . '#2');
});

it('fails assertPersisted when a different user class has the same identifier', function (): void {
    $persister = new FakeUserPersister();
    $persister->persist(new FakeAuthenticatable(id: 1));
    $other = new class (id: 1) extends FakeAuthenticatable {};

    expect(fn () => $persister->assertPersisted($other))
        ->toThrow(AssertionFailedException::class);
});

it('passes assertNothingPersisted when nothing was persisted', function (): void {
    (new FakeUserPersister())->assertNothingPersisted();

    expect(true)->toBeTrue();
});

it('fails assertNothingPersisted when a user was persisted', function (): void {
    $persister = new FakeUserPersister();
    $persister->persist(new FakeAuthenticatable());

    expect(fn () => $persister->assertNothingPersisted())
        ->toThrow(AssertionFailedException::class, 'Expected no persisted users');
});

it('forgets recorded users on clear', function (): void {
    $persister = new FakeUserPersister();
    $persister->persist(new FakeAuthenticatable());

    $persister->clear();

    expect($persister->persisted())->toBe([]);
});
