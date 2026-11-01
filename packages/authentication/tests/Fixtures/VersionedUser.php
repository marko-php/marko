<?php

declare(strict_types=1);

namespace Marko\Authentication\Tests\Fixtures;

use Marko\Authentication\HasSessionVersionInterface;
use Marko\Testing\Fake\FakeAuthenticatable;

/**
 * A user entity with a session version, so its sessions on other devices can be ended.
 */
class VersionedUser extends FakeAuthenticatable implements HasSessionVersionInterface
{
    private string $sessionVersion = '';

    public function getSessionVersion(): string
    {
        return $this->sessionVersion;
    }

    public function setSessionVersion(
        string $version,
    ): void {
        $this->sessionVersion = $version;
    }
}
