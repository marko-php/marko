<?php

declare(strict_types=1);

namespace Marko\Cache\Tests\Support;

use Marko\Cache\Contracts\LockStoreInterface;
use Marko\Cache\Lock\LockScope;

/**
 * A minimal in-memory LockStoreInterface for the marko/cache unit tests, which
 * cannot depend on a driver package. It ignores expiry and records the calls
 * Lock makes, so the tests check what Lock delegates. The real stores run the
 * shared LockStoreContract suite instead.
 */
class RecordingLockStore implements LockStoreInterface
{
    /** @var array<string, string> Lock name => owner */
    public array $owners = [];

    /** @var list<array{0: string, 1: list<int|string>}> */
    public array $calls = [];

    public function __construct(
        private readonly LockScope $scope = LockScope::Process,
    ) {}

    public function acquire(
        string $name,
        string $owner,
        int $seconds,
    ): bool {
        $this->calls[] = ['acquire', [$name, $owner, $seconds]];

        if (isset($this->owners[$name])) {
            return false;
        }

        $this->owners[$name] = $owner;

        return true;
    }

    public function release(
        string $name,
        string $owner,
    ): bool {
        $this->calls[] = ['release', [$name, $owner]];

        if (($this->owners[$name] ?? null) !== $owner) {
            return false;
        }

        unset($this->owners[$name]);

        return true;
    }

    public function forceRelease(
        string $name,
    ): void {
        $this->calls[] = ['forceRelease', [$name]];

        unset($this->owners[$name]);
    }

    public function currentOwner(
        string $name,
    ): ?string {
        return $this->owners[$name] ?? null;
    }

    public function scope(): LockScope
    {
        return $this->scope;
    }
}
