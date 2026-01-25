<?php

declare(strict_types=1);

namespace Marko\Auth\Tests\Integration;

use Marko\Session\Contracts\SessionInterface;
use Marko\Session\Flash\FlashBag;

/**
 * Test session for integration testing.
 */
class TestSession implements SessionInterface
{
    public bool $started = true;

    public bool $regenerateCalled = false;

    /** @var array<string, mixed> */
    private array $storage = [];

    public function start(): void {}

    public function get(
        string $key,
        mixed $default = null,
    ): mixed {
        return $this->storage[$key] ?? $default;
    }

    public function set(
        string $key,
        mixed $value,
    ): void {
        $this->storage[$key] = $value;
    }

    public function has(
        string $key,
    ): bool {
        return isset($this->storage[$key]);
    }

    public function remove(
        string $key,
    ): void {
        unset($this->storage[$key]);
    }

    public function clear(): void
    {
        $this->storage = [];
    }

    public function all(): array
    {
        return $this->storage;
    }

    public function regenerate(
        bool $deleteOldSession = true,
    ): void {
        $this->regenerateCalled = true;
    }

    public function destroy(): void
    {
        $this->storage = [];
    }

    public function getId(): string
    {
        return 'test-session-id';
    }

    public function setId(string $id): void {}

    public function flash(): FlashBag
    {
        return new FlashBag($this->storage);
    }

    public function save(): void {}
}
