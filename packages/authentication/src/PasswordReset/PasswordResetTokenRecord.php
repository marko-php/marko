<?php

declare(strict_types=1);

namespace Marko\Authentication\PasswordReset;

use DateTimeImmutable;

readonly class PasswordResetTokenRecord
{
    public function __construct(
        public string $tokenHash,
        public DateTimeImmutable $createdAt,
    ) {}
}
