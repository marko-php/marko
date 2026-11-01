<?php

declare(strict_types=1);

namespace Marko\Authentication\Tests\Fixtures;

use Marko\Authentication\AuthenticatableInterface;
use Marko\Testing\Fake\FakeUserProvider;

/**
 * A user provider that finds users by the 'email' credential only.
 */
class EmailUserProvider extends FakeUserProvider
{
    /**
     * @param array<string, AuthenticatableInterface> $usersByEmail
     */
    public function __construct(
        private readonly array $usersByEmail,
    ) {
        parent::__construct();
    }

    public function retrieveByCredentials(
        array $credentials,
    ): ?AuthenticatableInterface {
        $email = $credentials['email'] ?? null;

        return is_string($email) ? ($this->usersByEmail[$email] ?? null) : null;
    }
}
