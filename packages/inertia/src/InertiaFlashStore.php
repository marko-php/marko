<?php

declare(strict_types=1);

namespace Marko\Inertia;

use Closure;
use Marko\Session\Contracts\SessionInterface;
use Throwable;

class InertiaFlashStore
{
    private const string SESSION_KEY = '_inertia_flash';

    public function __construct(
        private readonly ?Closure $sessionResolver = null,
    ) {}

    public function available(): bool
    {
        $session = $this->resolveSession();

        return $session !== null && $session->started;
    }

    public function flash(
        string|array $key,
        mixed $value = null,
    ): void {
        if (! $this->available()) {
            return;
        }

        $flash = $this->peek();

        if (is_array($key)) {
            foreach ($key as $flashKey => $flashValue) {
                if (! is_string($flashKey)) {
                    continue;
                }

                $flash[$flashKey] = $flashValue;
            }
        } else {
            $flash[$key] = $value;
        }

        $this->resolveSession()?->set(self::SESSION_KEY, $flash);
    }

    /**
     * @return array<string, mixed>
     */
    public function peek(): array
    {
        if (! $this->available()) {
            return [];
        }

        $value = $this->resolveSession()?->get(self::SESSION_KEY, []);

        return is_array($value) ? $value : [];
    }

    /**
     * @return array<string, mixed>
     */
    public function pull(): array
    {
        $flash = $this->peek();

        if ($flash !== [] && $this->available()) {
            $this->resolveSession()?->remove(self::SESSION_KEY);
        }

        return $flash;
    }

    private function resolveSession(): ?SessionInterface
    {
        if ($this->sessionResolver === null) {
            return null;
        }

        try {
            $session = ($this->sessionResolver)();
        } catch (Throwable) {
            return null;
        }

        return $session instanceof SessionInterface ? $session : null;
    }
}
