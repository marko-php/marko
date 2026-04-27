<?php

declare(strict_types=1);

use Marko\Debugbar\Debugbar;

if (! function_exists('debugbar')) {
    /**
     * Get the current debugbar instance or add a message to it.
     *
     * @param array<string, mixed> $context
     */
    function debugbar(?string $message = null, string $level = 'info', array $context = []): ?Debugbar
    {
        $debugbar = Debugbar::current();

        if ($debugbar !== null && $message !== null) {
            $debugbar->addMessage($message, $level, $context);
        }

        return $debugbar;
    }
}
