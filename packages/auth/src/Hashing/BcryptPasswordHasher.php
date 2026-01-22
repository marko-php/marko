<?php

declare(strict_types=1);

namespace Marko\Auth\Hashing;

use Marko\Auth\Contracts\PasswordHasherInterface;

class BcryptPasswordHasher implements PasswordHasherInterface
{
    private int $cost;

    public function __construct(
        ?int $cost = null,
    ) {
        $this->cost = $cost ?? 12;
    }

    public function hash(
        string $password,
    ): string {
        return password_hash($password, PASSWORD_BCRYPT, ['cost' => $this->cost]);
    }

    public function verify(
        string $password,
        string $hash,
    ): bool {
        return password_verify($password, $hash);
    }

    public function needsRehash(
        string $hash,
    ): bool {
        return password_needs_rehash($hash, PASSWORD_BCRYPT, ['cost' => $this->cost]);
    }
}
