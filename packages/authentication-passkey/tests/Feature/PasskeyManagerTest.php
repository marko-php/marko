<?php

declare(strict_types=1);

use Marko\Authentication\Exceptions\PasswordConfirmationRequiredException;
use Marko\Authentication\Exceptions\UnauthenticatedException;
use Marko\AuthenticationPasskey\Event\PasskeyDeletedEvent;
use Marko\AuthenticationPasskey\Exceptions\InvalidPasskeyNameException;
use Marko\AuthenticationPasskey\Exceptions\PasskeyNotFoundException;
use Marko\AuthenticationPasskey\Tests\Fixtures\PasskeyHarness;
use Marko\AuthenticationPasskey\Tests\Fixtures\SoftwareAuthenticator;

beforeEach(function (): void {
    $this->harness = new PasskeyHarness();
    $this->laptop = $this->harness->registerPasskey(new SoftwareAuthenticator(), $this->harness->ada, 'Laptop');
    $this->phone = $this->harness->registerPasskey(new SoftwareAuthenticator(), $this->harness->ada, 'Phone');
    $this->gracesKey = $this->harness->registerPasskey(new SoftwareAuthenticator(), $this->harness->grace, 'YubiKey');
    $this->harness->guard()->login($this->harness->ada);
    $this->harness->eventDispatcher->clear();
});

it('lists the signed-in user\'s passkeys, oldest first', function (): void {
    expect(array_map(fn ($passkey) => $passkey->name, $this->harness->manager()->forCurrentUser()))
        ->toBe(['Laptop', 'Phone']);
});

it('renames one of the user\'s passkeys', function (): void {
    $this->harness->manager()->rename($this->laptop->credentialId, ' Work laptop ');

    expect($this->harness->repository->findByCredentialId($this->laptop->credentialId)->name)->toBe('Work laptop')
        ->and(fn () => $this->harness->manager()->rename($this->laptop->credentialId, ''))
        ->toThrow(InvalidPasskeyNameException::class);
});

it('reports another user\'s passkey as not found', function (): void {
    expect(fn () => $this->harness->manager()->rename($this->gracesKey->credentialId, 'Mine'))
        ->toThrow(PasskeyNotFoundException::class);

    $this->harness->passwordConfirmation()->markConfirmed();

    expect(fn () => $this->harness->manager()->delete($this->gracesKey->credentialId))
        ->toThrow(PasskeyNotFoundException::class)
        ->and($this->harness->repository->findByCredentialId($this->gracesKey->credentialId))->not->toBeNull();
});

it('refuses to delete without a recent password confirmation', function (): void {
    expect(fn () => $this->harness->manager()->delete($this->laptop->credentialId))
        ->toThrow(PasswordConfirmationRequiredException::class)
        ->and($this->harness->repository->findByCredentialId($this->laptop->credentialId))->not->toBeNull();
});

it('deletes a passkey after confirmation and dispatches PasskeyDeletedEvent', function (): void {
    $this->harness->passwordConfirmation()->markConfirmed();

    $this->harness->manager()->delete($this->laptop->credentialId);

    $event = $this->harness->eventDispatcher->dispatched(PasskeyDeletedEvent::class)[0];

    expect($this->harness->repository->findByCredentialId($this->laptop->credentialId))->toBeNull()
        ->and($event->user)->toBe($this->harness->ada)
        ->and($event->credential->credentialId)->toBe($this->laptop->credentialId)
        ->and($event->guard)->toBe('session');
});

it('needs a signed-in user', function (): void {
    $this->harness->guard()->logout();

    expect(fn () => $this->harness->manager()->forCurrentUser())->toThrow(UnauthenticatedException::class);
});
