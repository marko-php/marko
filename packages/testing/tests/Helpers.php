<?php

declare(strict_types=1);

namespace Marko\Testing\Tests;

use Closure;
use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * The fixture project the TestClient tests boot: routes for JSON, forms,
 * redirects, cookies, sessions, auth and request-scoped state.
 */
function httpAppPath(): string
{
    return __DIR__ . '/fixtures/http-app';
}

/**
 * Remove the session files the fixture app wrote for this process (see the
 * fixture's config/session.php).
 */
function removeHttpAppSessions(): void
{
    $directory = sys_get_temp_dir() . '/marko-testing-http-app/' . getmypid();

    if (!is_dir($directory)) {
        return;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST,
    );

    foreach ($iterator as $file) {
        $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
    }

    rmdir($directory);
}

/**
 * The fixture project whose boot installs global handlers: the real
 * marko/errors-simple module wiring registers an error handler and an
 * exception handler from its boot callback.
 */
function errorsAppPath(): string
{
    return __DIR__ . '/fixtures/errors-app';
}

/**
 * PHP's error and exception handler stacks, bottom first, read without
 * changing them (PHP has no getter, so each handler is popped and pushed back).
 *
 * @return array{errorHandlers: list<mixed>, exceptionHandlers: list<mixed>}
 */
function globalHandlerStacks(): array
{
    $read = static function (Closure $set, Closure $restore): array {
        $handlers = [];

        while (($handler = $set(null)) !== null) {
            $restore();
            $handlers[] = $handler;
            $restore();
        }

        $restore();
        $handlers = array_reverse($handlers);

        foreach ($handlers as $handler) {
            $set($handler);
        }

        return $handlers;
    };

    return [
        'errorHandlers' => $read(set_error_handler(...), restore_error_handler(...)),
        'exceptionHandlers' => $read(set_exception_handler(...), restore_exception_handler(...)),
    ];
}

/**
 * The fixture project the TestDatabase tests boot: an app module that binds
 * an in-memory recording connection the way a driver package would.
 */
function databaseAppPath(): string
{
    return __DIR__ . '/fixtures/database-app';
}

/**
 * Run $callback with APP_ENV set to $name (MARKO_ENV cleared; null leaves
 * both unset), restoring both afterwards. AppEnvironment reads the
 * variables on every call.
 *
 * @template T
 * @param Closure(): T $callback
 * @return T
 */
function withAppEnv(
    ?string $name,
    Closure $callback,
): mixed {
    $saved = [];

    foreach (['APP_ENV', 'MARKO_ENV'] as $variable) {
        $saved[$variable] = [
            'env' => array_key_exists($variable, $_ENV) ? $_ENV[$variable] : null,
            'process' => getenv($variable),
        ];
        unset($_ENV[$variable]);
        putenv($variable);
    }

    if ($name !== null) {
        $_ENV['APP_ENV'] = $name;
        putenv("APP_ENV=$name");
    }

    try {
        return $callback();
    } finally {
        foreach ($saved as $variable => $values) {
            unset($_ENV[$variable]);
            putenv($variable);

            if ($values['env'] !== null) {
                $_ENV[$variable] = $values['env'];
            }

            if ($values['process'] !== false) {
                putenv("$variable={$values['process']}");
            }
        }
    }
}
