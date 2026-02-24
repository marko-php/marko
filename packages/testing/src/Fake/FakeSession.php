<?php

declare(strict_types=1);

namespace Marko\Testing\Fake;

use Marko\Session\Contracts\SessionInterface;
use Marko\Session\Flash\FlashBag;

class FakeSession implements SessionInterface
{
    public private(set) bool $started = false;

    public private(set) bool $regenerated = false;

    public private(set) bool $destroyed = false;

    public private(set) bool $saved = false;

    /** @var array<string, mixed> */
    private array $data = [];

    private string $id = '';

    private ?FlashBag $flashBag = null;

    public function start(): void
    {
        $this->started = true;
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

    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return $this->data;
    }

    public function regenerate(
        bool $deleteOldSession = true,
    ): void {
        $this->regenerated = true;
        $this->id = bin2hex(random_bytes(16));
    }

    public function destroy(): void
    {
        $this->destroyed = true;
        $this->data = [];
    }

    public function getId(): string
    {
        if ($this->id === '') {
            $this->id = bin2hex(random_bytes(16));
        }

        return $this->id;
    }

    public function setId(
        string $id,
    ): void {
        $this->id = $id;
    }

    public function flash(): FlashBag
    {
        if ($this->flashBag === null) {
            $this->flashBag = new FlashBag($this->data);
        }

        return $this->flashBag;
    }

    public function save(): void
    {
        $this->saved = true;
    }
}
