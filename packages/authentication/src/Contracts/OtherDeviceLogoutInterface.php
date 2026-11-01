<?php

declare(strict_types=1);

namespace Marko\Authentication\Contracts;

use Marko\Authentication\AuthenticatableInterface;

/**
 * A guard that can sign a user out everywhere except the current device, for
 * example after a password change or reset.
 *
 * SessionGuard implements it. Stateless guards keep no sessions, so they do not:
 * check for this interface before calling it, or use AuthManager::logoutOtherDevices(),
 * which fails loudly for a guard without it.
 */
interface OtherDeviceLogoutInterface
{
    /**
     * End the user's other sessions and remember-me devices, keeping the current
     * session and remember token when they belong to this user.
     *
     * Remember-me tokens of other devices are always revoked. Other sessions are only
     * ended when the user implements HasSessionVersionInterface.
     */
    public function logoutOtherDevices(
        AuthenticatableInterface $user,
    ): void;
}
