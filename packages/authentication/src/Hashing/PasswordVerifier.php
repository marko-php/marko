<?php

declare(strict_types=1);

namespace Marko\Authentication\Hashing;

use Marko\Authentication\AuthenticatableInterface;
use Marko\Authentication\Contracts\PasswordHasherInterface;
use Marko\Authentication\Contracts\PasswordVerifierInterface;

readonly class PasswordVerifier implements PasswordVerifierInterface
{
    public function __construct(
        private PasswordHasherInterface $passwordHasher,
    ) {}

    public function verify(
        ?AuthenticatableInterface $user,
        string $password,
    ): bool {
        if ($user === null) {
            $this->passwordHasher->verifyDummy($password);

            return false;
        }

        return $this->passwordHasher->verify($password, $user->getAuthPassword());
    }
}
