<?php

declare(strict_types=1);

namespace Marko\Blog\Tests\Mocks;

use Marko\Session\Contracts\SessionInterface;
use Marko\Session\Flash\FlashBag;

class MockSession implements SessionInterface
{
    /** @var array<string, array<string>> */
    public array $flashMessages = [];

    public bool $startCalled = false;

    /** @var array<string, mixed> */
    private array $data = [];

    public bool $started {
        get => true;
    }

    public function start(): void
    {
        $this->startCalled = true;
    }

    public function get(
        string $key,
        mixed $default = null,
    ): mixed {
        return $this->data[$key] ?? $default;
    }

    public function set(
        string $key,
        mixed $value,
    ): void {
        $this->data[$key] = $value;
    }

    public function has(
        string $key,
    ): bool {
        return isset($this->data[$key]);
    }

    public function remove(
        string $key,
    ): void {
        unset($this->data[$key]);
    }

    public function clear(): void
    {
        $this->data = [];
    }

    public function all(): array
    {
        return $this->data;
    }

    public function regenerate(bool $deleteOldSession = true): void {}

    public function destroy(): void
    {
        $this->data = [];
    }

    public function getId(): string
    {
        return 'test-session-id';
    }

    public function setId(string $id): void {}

    public function flash(): FlashBag
    {
        $flashData = ['_flash' => $this->flashMessages];

        return new FlashBag($flashData);
    }

    public function save(): void {}
}
