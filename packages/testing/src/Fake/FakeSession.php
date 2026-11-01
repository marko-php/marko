<?php

declare(strict_types=1);

namespace Marko\Testing\Fake;

use Marko\Session\Contracts\SessionInterface;
use Marko\Session\Flash\FlashBag;
use Marko\Session\Flash\SessionFlashData;
use Random\RandomException;

class FakeSession implements SessionInterface
{
    public private(set) bool $started = false;

    /**
     * Whether arm() prepared the session to start on first access.
     */
    public private(set) bool $armed = false;

    public private(set) bool $regenerated = false;

    public private(set) bool $destroyed = false;

    public private(set) bool $saved = false;

    public private(set) bool $discarded = false;

    /** @var array<string, mixed> */
    private array $data = [];

    /** @var array<string, mixed> */
    private array $startedData = [];

    private string $id = '';

    private ?FlashBag $flashBag = null;

    /**
     * Each start() stands for a new request loading the session, so it ages
     * next-request flash data exactly as Session::start() does.
     */
    public function start(): void
    {
        $this->started = true;
        $this->startedData = $this->data;
        $this->data = SessionFlashData::age($this->data);
    }

    public function arm(): void
    {
        $this->armed = true;
    }

    public function isAvailable(): bool
    {
        return $this->started || $this->armed;
    }

    public function isModified(): bool
    {
        return $this->regenerated || $this->data !== $this->startedData;
    }

    public function discard(): void
    {
        $this->discarded = true;
        $this->armed = false;
        $this->started = false;
    }

    public function get(
        string $key,
        mixed $default = null,
    ): mixed {
        $this->startIfArmed();

        return $this->data[$key] ?? $default;
    }

    public function set(
        string $key,
        mixed $value,
    ): void {
        $this->startIfArmed();

        $this->data[$key] = $value;
    }

    public function has(
        string $key,
    ): bool {
        $this->startIfArmed();

        return array_key_exists($key, $this->data);
    }

    public function remove(
        string $key,
    ): void {
        $this->startIfArmed();

        unset($this->data[$key]);
    }

    public function clear(): void
    {
        $this->startIfArmed();

        $this->data = [];
    }

    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        $this->startIfArmed();

        return $this->data;
    }

    /**
     * @throws RandomException
     */
    public function regenerate(
        bool $deleteOldSession = true,
    ): void {
        $this->startIfArmed();

        $this->regenerated = true;
        $this->id = bin2hex(random_bytes(16));
    }

    public function destroy(): void
    {
        $this->destroyed = true;
        $this->armed = false;
        $this->data = [];
    }

    /**
     * @throws RandomException
     */
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
        $this->startIfArmed();

        if ($this->flashBag === null) {
            $this->flashBag = new FlashBag($this->data);
        }

        return $this->flashBag;
    }

    public function save(): void
    {
        $this->saved = true;
        $this->armed = false;
    }

    /**
     * Mirrors Session: an armed session starts on first data access. Unlike
     * Session, an unarmed fake never throws, so existing tests can use it
     * without going through the middleware.
     */
    private function startIfArmed(): void
    {
        if ($this->armed && !$this->started) {
            $this->start();
        }
    }
}
