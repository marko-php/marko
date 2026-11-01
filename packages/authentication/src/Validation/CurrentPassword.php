<?php

declare(strict_types=1);

namespace Marko\Authentication\Validation;

use Marko\Authentication\Contracts\GuardInterface;
use Marko\Authentication\Contracts\PasswordVerifierInterface;
use Marko\Validation\Contracts\RuleInterface;

/**
 * Passes when the value is the password of the guard's authenticated user.
 *
 * The check goes through PasswordVerifierInterface, so with no authenticated user it
 * still costs one dummy hash check and fails. Build it with AuthRules::currentPassword().
 */
readonly class CurrentPassword implements RuleInterface
{
    public function __construct(
        private GuardInterface $guard,
        private PasswordVerifierInterface $passwordVerifier,
    ) {}

    public function passes(
        string $field,
        mixed $value,
        array $data,
    ): bool {
        if (!is_string($value)) {
            return false;
        }

        $user = $this->guard->user();

        // Verify first so a missing user still costs one (dummy) hash check
        $verified = $this->passwordVerifier->verify($user, $value);

        return $user !== null && $verified;
    }

    public function message(
        string $field,
        mixed $value,
    ): string {
        return "The $field is incorrect.";
    }
}
