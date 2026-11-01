<?php

declare(strict_types=1);

namespace Marko\Testing\Fake;

use Marko\Authentication\AuthenticatableInterface;
use Marko\Authentication\Contracts\UserPersisterInterface;
use Marko\Testing\Exceptions\AssertionFailedException;

/**
 * Records every user it is asked to persist instead of saving it.
 *
 * Bind it to UserPersisterInterface (or name a subclass as a provider's persister) in a test,
 * then assert on what password reset, email verification, two-factor or logout-other-devices
 * saved. Users are matched by class + auth identifier, not object identity, so a reloaded
 * entity still matches the one the code under test persisted.
 */
class FakeUserPersister implements UserPersisterInterface
{
    /** @var list<AuthenticatableInterface> */
    private array $persisted = [];

    public function persist(
        AuthenticatableInterface $user,
    ): void {
        $this->persisted[] = $user;
    }

    /**
     * Every persisted user, in the order persist() was called. A user saved twice appears twice.
     *
     * @return list<AuthenticatableInterface>
     */
    public function persisted(): array
    {
        return $this->persisted;
    }

    /**
     * Whether a user of the same class and auth identifier was persisted.
     */
    public function wasPersisted(
        AuthenticatableInterface $user,
    ): bool {
        return array_any(
            $this->persisted,
            fn (AuthenticatableInterface $persisted): bool => $persisted::class === $user::class
                && (string) $persisted->getAuthIdentifier() === (string) $user->getAuthIdentifier(),
        );
    }

    /**
     * @throws AssertionFailedException
     */
    public function assertPersisted(
        AuthenticatableInterface $user,
    ): void {
        if (!$this->wasPersisted($user)) {
            throw AssertionFailedException::expectedContains('persisted users', self::describe($user));
        }
    }

    /**
     * @throws AssertionFailedException
     */
    public function assertNothingPersisted(): void
    {
        if ($this->persisted !== []) {
            throw AssertionFailedException::expectedEmpty('persisted users');
        }
    }

    public function clear(): void
    {
        $this->persisted = [];
    }

    /**
     * Names a user as [Class#identifier] for assertion messages.
     */
    public static function describe(
        AuthenticatableInterface $user,
    ): string {
        return '[' . $user::class . '#' . $user->getAuthIdentifier() . ']';
    }
}
