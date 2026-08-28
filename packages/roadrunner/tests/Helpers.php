<?php

declare(strict_types=1);

namespace Marko\Roadrunner\Tests;

use Closure;
use Marko\Core\Module\ModuleManifest;
use Marko\Core\Module\ModuleRepositoryInterface;
use Marko\Routing\Http\Request;
use Throwable;

/**
 * Create a test implementation of ModuleRepositoryInterface backed by a
 * fixed list of module manifests, with an optional spy callback invoked
 * every time all() is queried.
 *
 * @param array<ModuleManifest> $modules
 */
function createModuleRepository(
    array $modules,
    ?Closure $onAll = null,
): ModuleRepositoryInterface {
    return new class ($modules, $onAll) implements ModuleRepositoryInterface
    {
        public function __construct(
            private readonly array $modules,
            private readonly ?Closure $onAll,
        ) {}

        public function all(): array
        {
            if ($this->onAll !== null) {
                ($this->onAll)();
            }

            return $this->modules;
        }
    };
}

/**
 * Invoke a callback and return the Throwable it raises, or null if it
 * completes without throwing. Used to assert on exception content without
 * a try/catch block in each test.
 */
function catchThrowable(
    Closure $callback,
): ?Throwable {
    try {
        $callback();
    } catch (Throwable $throwable) {
        return $throwable;
    }

    return null;
}

/**
 * Absolute path to the fixture Marko project consumed by
 * InProcessRequestHarness: a real vendor/modules/app tree wiring
 * marko/session, marko/session-file and marko/authentication.
 */
function inProcessHarnessFixturePath(): string
{
    return __DIR__ . '/Fixtures/app';
}

/**
 * The session cookie name configured in the fixture app's config/session.php.
 */
function inProcessHarnessSessionCookieName(): string
{
    return 'marko_session';
}

/**
 * A syntactically valid session id (Session::validateId requires 32-128
 * alphanumeric-or-hyphen characters) for driving requests with distinct
 * cookies through the harness.
 */
function inProcessHarnessSessionId(): string
{
    return bin2hex(random_bytes(20));
}

/**
 * Build a Request against the fixture app's demo routes.
 *
 * @param array<string, string> $cookies
 */
function inProcessHarnessRequest(
    string $method,
    string $uri,
    array $cookies = [],
): Request {
    return new Request(
        server: [
            'REQUEST_METHOD' => $method,
            'REQUEST_URI' => $uri,
        ],
        cookies: $cookies,
    );
}

/**
 * Absolute path to the monorepo root (four levels above this file:
 * tests/ -> roadrunner/ -> packages/ -> repo root).
 */
function monorepoRootPath(): string
{
    return dirname(__DIR__, 3);
}

/**
 * Parse a module.php file's `singletons` declaration into the short
 * (unqualified) identifiers it registers, used to mechanically cross-check
 * the state-leak findings document against every singleton actually
 * declared in the monorepo, rather than trusting a hand-maintained list.
 *
 * Handles both list form (`[Foo::class]`) and keyed form
 * (`[Interface::class => Concrete::class]` or `[Interface::class => Closure]`)
 * — the identifier is the string key when present, the string value
 * otherwise.
 *
 * @return list<string>
 */
function moduleSingletonIdentifiers(
    string $moduleFile,
): array {
    /** @var array{singletons?: array<int|string, mixed>} $config */
    $config = require $moduleFile;
    $singletons = $config['singletons'] ?? [];
    $identifiers = [];

    foreach ($singletons as $key => $value) {
        $identifier = is_string($key) ? $key : $value;

        if (!is_string($identifier)) {
            continue;
        }

        $identifiers[] = str_contains($identifier, '\\')
            ? substr($identifier, strrpos($identifier, '\\') + 1)
            : $identifier;
    }

    return array_values(array_unique($identifiers));
}
