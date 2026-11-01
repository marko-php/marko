<?php

declare(strict_types=1);

use Marko\Authentication\AuthenticatableInterface;
use Marko\Authentication\Contracts\PasswordVerifierInterface;
use Marko\Authentication\Hashing\BcryptPasswordHasher;
use Marko\Authentication\Hashing\PasswordVerifier;
use Marko\Authentication\Validation\CurrentPassword;
use Marko\Testing\Fake\FakeAuthenticatable;
use Marko\Testing\Fake\FakeGuard;
use Marko\Validation\Contracts\RuleInterface;
use Marko\Validation\Validation\Validator;

/**
 * A verifier that records each call and answers with a fixed result.
 */
class RecordingPasswordVerifier implements PasswordVerifierInterface
{
    /** @var list<array{?AuthenticatableInterface, string}> */
    public array $calls = [];

    public function __construct(
        private readonly bool $result,
    ) {}

    public function verify(
        ?AuthenticatableInterface $user,
        string $password,
    ): bool {
        $this->calls[] = [$user, $password];

        return $this->result;
    }
}

function signedInGuard(): FakeGuard
{
    $hasher = new BcryptPasswordHasher(cost: 4);
    $guard = new FakeGuard();
    $guard->setUser(new FakeAuthenticatable(id: 7, password: $hasher->hash('secret')));

    return $guard;
}

function bcryptVerifier(): PasswordVerifier
{
    return new PasswordVerifier(new BcryptPasswordHasher(cost: 4));
}

it('is a validation rule', function (): void {
    expect(new CurrentPassword(new FakeGuard(), bcryptVerifier()))->toBeInstanceOf(RuleInterface::class);
});

it('passes for the authenticated user\'s password', function (): void {
    $rule = new CurrentPassword(signedInGuard(), bcryptVerifier());

    expect($rule->passes('current_password', 'secret', []))->toBeTrue();
});

it('fails for a wrong password', function (): void {
    $rule = new CurrentPassword(signedInGuard(), bcryptVerifier());

    expect($rule->passes('current_password', 'wrong', []))->toBeFalse()
        ->and($rule->message('current_password', 'wrong'))->toBe('The current_password is incorrect.');
});

it('fails with no authenticated user', function (): void {
    $rule = new CurrentPassword(new FakeGuard(), bcryptVerifier());

    expect($rule->passes('current_password', 'secret', []))->toBeFalse();
});

it('fails with no authenticated user through the verifier, without calling the hasher directly', function (): void {
    $verifier = new RecordingPasswordVerifier(result: true);
    $rule = new CurrentPassword(new FakeGuard(), $verifier);

    expect($rule->passes('current_password', 'secret', []))->toBeFalse()
        ->and($verifier->calls)->toBe([[null, 'secret']]);
});

it('checks the user of the guard it was built with', function (): void {
    $verifier = new RecordingPasswordVerifier(result: true);
    $adminGuard = new FakeGuard(name: 'admin');
    $admin = new FakeAuthenticatable(id: 99);
    $adminGuard->setUser($admin);

    $rule = new CurrentPassword($adminGuard, $verifier);

    expect($rule->passes('current_password', 'secret', []))->toBeTrue()
        ->and($verifier->calls)->toBe([[$admin, 'secret']]);
});

it('fails for a non-string value without verifying it', function (): void {
    $verifier = new RecordingPasswordVerifier(result: true);
    $rule = new CurrentPassword(signedInGuard(), $verifier);

    expect($rule->passes('current_password', ['secret'], []))->toBeFalse()
        ->and($verifier->calls)->toBe([]);
});

it('reports the error through the validator', function (): void {
    $rule = new CurrentPassword(signedInGuard(), bcryptVerifier());

    $errors = new Validator()->validate(
        ['current_password' => 'wrong'],
        ['current_password' => ['required', $rule]],
    );

    expect($errors->get('current_password'))->toBe(['The current_password is incorrect.']);
});
