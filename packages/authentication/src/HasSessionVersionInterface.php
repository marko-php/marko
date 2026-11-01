<?php

declare(strict_types=1);

namespace Marko\Authentication;

/**
 * A user whose sessions on other devices can be ended (OtherDeviceLogoutInterface).
 *
 * The session guard copies the user's session version into the session at login and
 * rejects a session whose copy no longer matches. Ending other sessions stores a new
 * version on the user and saves it through UserPersisterInterface, so every session
 * still holding the old one is logged out on its next request, whichever session
 * driver stores it.
 *
 * Store the version in its own column with an empty-string default. An empty string
 * means the version was never changed, so sessions started before the column existed
 * stay valid until other devices are first logged out.
 */
interface HasSessionVersionInterface extends AuthenticatableInterface
{
    /**
     * The current session version, or an empty string when it was never changed.
     */
    public function getSessionVersion(): string;

    /**
     * Replace the session version. The guard saves the user through
     * UserPersisterInterface afterwards.
     */
    public function setSessionVersion(
        string $version,
    ): void;
}
