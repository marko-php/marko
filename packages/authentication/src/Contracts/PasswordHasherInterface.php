<?php

declare(strict_types=1);

namespace Marko\Authentication\Contracts;

interface PasswordHasherInterface
{
    public function hash(
        string $password,
    ): string;

    public function verify(
        string $password,
        string $hash,
    ): bool;

    public function needsRehash(
        string $hash,
    ): bool;
}
