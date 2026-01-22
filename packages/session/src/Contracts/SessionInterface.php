<?php

declare(strict_types=1);

namespace Marko\Session\Contracts;

use Marko\Session\Flash\FlashBag;

interface SessionInterface
{
    /**
     * Start the session.
     */
    public function start(): void;

    /**
     * Check if session has been started.
     */
    public function isStarted(): bool;

    /**
     * Get a value from the session.
     */
    public function get(
        string $key,
        mixed $default = null,
    ): mixed;

    /**
     * Set a value in the session.
     */
    public function set(
        string $key,
        mixed $value,
    ): void;

    /**
     * Check if session has a key.
     */
    public function has(string $key): bool;

    /**
     * Remove a value from the session.
     */
    public function remove(string $key): void;

    /**
     * Clear all session data.
     */
    public function clear(): void;

    /**
     * Get all session data.
     *
     * @return array<string, mixed>
     */
    public function all(): array;

    /**
     * Regenerate the session ID.
     *
     * @param bool $deleteOldSession Whether to delete the old session file
     */
    public function regenerate(bool $deleteOldSession = true): void;

    /**
     * Invalidate and destroy the session.
     */
    public function destroy(): void;

    /**
     * Get the session ID.
     */
    public function getId(): string;

    /**
     * Set the session ID (before start).
     */
    public function setId(string $id): void;

    /**
     * Get the flash bag for flash messages.
     */
    public function flash(): FlashBag;

    /**
     * Save session data and close.
     */
    public function save(): void;
}
