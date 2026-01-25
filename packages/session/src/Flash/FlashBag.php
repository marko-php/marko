<?php

declare(strict_types=1);

namespace Marko\Session\Flash;

class FlashBag
{
    private const string FLASH_KEY = '_flash';

    /**
     * @var array<string, array<string>>
     */
    private array $messages = [];

    /**
     * @param array<string, mixed> $sessionData Reference to session data
     */
    public function __construct(
        private array &$sessionData,
    ) {
        $this->loadMessages();
    }

    /**
     * Add a flash message.
     */
    public function add(
        string $type,
        string $message,
    ): void {
        if (!isset($this->messages[$type])) {
            $this->messages[$type] = [];
        }

        $this->messages[$type][] = $message;
        $this->syncToSession();
    }

    /**
     * Set flash messages for a type (replaces existing).
     *
     * @param array<string> $messages
     */
    public function set(
        string $type,
        array $messages,
    ): void {
        $this->messages[$type] = $messages;
        $this->syncToSession();
    }

    /**
     * Get and clear flash messages for a type.
     *
     * @param array<string> $default
     * @return array<string>
     */
    public function get(
        string $type,
        array $default = [],
    ): array {
        $messages = $this->messages[$type] ?? $default;
        unset($this->messages[$type]);
        $this->syncToSession();

        return $messages;
    }

    /**
     * Get flash messages without clearing.
     *
     * @param array<string> $default
     * @return array<string>
     */
    public function peek(
        string $type,
        array $default = [],
    ): array {
        return $this->messages[$type] ?? $default;
    }

    /**
     * Get all flash messages and clear.
     *
     * @return array<string, array<string>>
     */
    public function all(): array
    {
        $messages = $this->messages;
        $this->messages = [];
        $this->syncToSession();

        return $messages;
    }

    /**
     * Check if flash messages exist for a type.
     */
    public function has(
        string $type,
    ): bool {
        return isset($this->messages[$type]) && count($this->messages[$type]) > 0;
    }

    /**
     * Clear all flash messages.
     *
     * @return array<string, array<string>> The cleared messages
     */
    public function clear(): array
    {
        $messages = $this->messages;
        $this->messages = [];
        $this->syncToSession();

        return $messages;
    }

    private function loadMessages(): void
    {
        $this->messages = $this->sessionData[self::FLASH_KEY] ?? [];
    }

    private function syncToSession(): void
    {
        if ($this->messages === []) {
            unset($this->sessionData[self::FLASH_KEY]);
        } else {
            $this->sessionData[self::FLASH_KEY] = $this->messages;
        }
    }
}
