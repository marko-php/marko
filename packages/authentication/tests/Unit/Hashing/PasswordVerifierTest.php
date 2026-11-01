<?php

declare(strict_types=1);

use Marko\Authentication\Contracts\PasswordHasherInterface;
use Marko\Authentication\Contracts\PasswordVerifierInterface;
use Marko\Authentication\Hashing\BcryptPasswordHasher;
use Marko\Authentication\Hashing\PasswordVerifier;
use Marko\Testing\Fake\FakeAuthenticatable;

/**
 * Records every call so a test can prove the verifier does nothing but check.
 */
function passwordVerifierTestHasher(): PasswordHasherInterface
{
    return new class () implements PasswordHasherInterface
    {
        /** @var list<string> */
        public array $calls = [];

        public function hash(
            string $password,
        ): string {
            $this->calls[] = 'hash';

            return 'hashed:' . $password;
        }

        public function verify(
            string $password,
            string $hash,
        ): bool {
            $this->calls[] = "verify:$password:$hash";

            return $hash === 'hashed:' . $password;
        }

        public function needsRehash(
            string $hash,
        ): bool {
            $this->calls[] = 'needsRehash';

            return false;
        }

        public function verifyDummy(
            string $password,
        ): void {
            $this->calls[] = "verifyDummy:$password";
        }
    };
}

it('implements PasswordVerifierInterface', function (): void {
    expect(new PasswordVerifier(passwordVerifierTestHasher()))->toBeInstanceOf(PasswordVerifierInterface::class);
});

it('accepts the user\'s password and rejects any other', function (): void {
    $hasher = new BcryptPasswordHasher(cost: 4);
    $user = new FakeAuthenticatable(id: 1, password: $hasher->hash('secret'));
    $verifier = new PasswordVerifier($hasher);

    expect($verifier->verify($user, 'secret'))->toBeTrue()
        ->and($verifier->verify($user, 'wrong'))->toBeFalse();
});

it('only checks the hash, with no other side effects', function (): void {
    $hasher = passwordVerifierTestHasher();
    $user = new FakeAuthenticatable(id: 1, password: 'hashed:secret');

    $result = new PasswordVerifier($hasher)->verify($user, 'secret');

    expect($result)->toBeTrue()
        ->and($hasher->calls)->toBe(['verify:secret:hashed:secret']);
});

it('runs a dummy hash check and returns false for a null user', function (): void {
    $hasher = passwordVerifierTestHasher();

    $result = new PasswordVerifier($hasher)->verify(null, 'secret');

    expect($result)->toBeFalse()
        ->and($hasher->calls)->toBe(['verifyDummy:secret']);
});
