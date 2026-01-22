<?php

declare(strict_types=1);

namespace Marko\Auth\Hashing;

use Marko\Auth\Contracts\PasswordHasherInterface;

class BcryptPasswordHasher implements PasswordHasherInterface
{
    public const int DEFAULT_COST = 12;

    private int $cost;

    public function __construct(
        ?int $cost = null,
    ) {
        $this->cost = $cost ?? self::DEFAULT_COST;
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
