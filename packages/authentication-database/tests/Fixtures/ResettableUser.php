<?php

declare(strict_types=1);

namespace Marko\Authentication\Database\Tests\Fixtures;

use Marko\Authentication\CanResetPasswordInterface;
use Marko\Testing\Fake\FakeAuthenticatable;

/**
 * A user whose password hash a reset can replace.
 */
class ResettableUser extends FakeAuthenticatable implements CanResetPasswordInterface
{
    public function __construct(
        int|string $id,
        private string $passwordHash = 'old-hash',
    ) {
        parent::__construct(id: $id);
    }

    public function getAuthPassword(): string
    {
        return $this->passwordHash;
    }

    public function setAuthPassword(
        string $hash,
    ): void {
        $this->passwordHash = $hash;
    }

    public function getEmailForPasswordReset(): string
    {
        return 'user-' . $this->getAuthIdentifier() . '@example.com';
    }
}
