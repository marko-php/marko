<?php

declare(strict_types=1);

namespace Marko\Roadrunner\Tests;

use Closure;
use Marko\Core\Module\ModuleManifest;
use Marko\Core\Module\ModuleRepositoryInterface;
use Marko\Core\Path\ProjectPaths;
use Marko\Roadrunner\Binary\BinaryLocator;
use Marko\Roadrunner\Exceptions\RoadRunnerException;
use Marko\Roadrunner\Tests\Support\RoadRunnerServerProcess;
use Marko\Roadrunner\Tests\Support\SharedRoadRunnerServer;
use Marko\Routing\Http\Request;
use Nyholm\Psr7\ServerRequest;
use Psr\Http\Message\ServerRequestInterface;
use Random\RandomException;
use RuntimeException;
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
 * Build a PSR-7 ServerRequest against the fixture app's demo routes,
 * carrying the given session cookie value under the fixture's configured
 * cookie name — the PSR-7-level equivalent of inProcessHarnessRequest(),
 * for driving WorkerRequestHandler (which only accepts PSR-7 requests)
 * against the same fixture application.
 */
function inProcessHarnessPsr7Request(
    string $method,
    string $uri,
    string $sessionId,
): ServerRequestInterface {
    return (new ServerRequest($method, 'https://example.test' . $uri))
        ->withCookieParams([inProcessHarnessSessionCookieName() => $sessionId]);
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
 * Locates the real `rr` server binary the same way `rr:serve` does, rooted
 * at the monorepo (not the fixture app — the binary is a project-wide dev
 * tool, installed once at the repo root via `vendor/bin/rr get-binary`).
 *
 * `spiral/roadrunner-cli`'s own Composer bin stub is also named `rr` and
 * also lives under `vendor/bin/`, one of {@see BinaryLocator}'s candidate
 * paths — so once that package is required (as the nightly workflow does),
 * a developer who has not yet run `get-binary` has an executable at
 * `vendor/bin/rr` that is the *downloader*, not the server. Confirmed here
 * by checking that `-v` reports the real server's own version banner
 * ("rr version ..."), never roadrunner-cli's ("RoadRunner CLI ..."),
 * before trusting the located path — otherwise every e2e test would try to
 * "serve" through the wrong binary and time out instead of skipping.
 */
function locateRoadRunnerBinary(): ?string
{
    $binary = (new BinaryLocator(new ProjectPaths(monorepoRootPath())))->locate();

    if ($binary === null) {
        return null;
    }

    $version = trim((string) shell_exec(escapeshellarg($binary) . ' -v 2>&1'));

    return str_starts_with($version, 'rr version') ? $binary : null;
}

/**
 * The exact explanation `rr:serve` itself gives a developer when the binary
 * is missing, reused here so the end-to-end suite's skip message and the
 * command's own error message can never drift apart.
 */
function roadRunnerSkipReason(): string
{
    $exception = RoadRunnerException::binaryNotFound();

    return $exception->getMessage() . ' ' . $exception->getSuggestion();
}

/**
 * The one real `rr serve` process shared by every end-to-end test in the
 * file that calls this — see {@see SharedRoadRunnerServer}. Every caller is
 * expected to have already skipped when {@see locateRoadRunnerBinary}
 * returns null, so reaching this function with no binary available is a
 * test-authoring mistake rather than an expected runtime state.
 *
 * @throws RandomException|RuntimeException
 */
function sharedRoadRunnerServer(): RoadRunnerServerProcess
{
    $binary = locateRoadRunnerBinary();

    if ($binary === null) {
        throw new RuntimeException(
            'sharedRoadRunnerServer() requires the RoadRunner binary; callers must skip when '
                . 'locateRoadRunnerBinary() returns null instead of reaching this point.',
        );
    }

    return SharedRoadRunnerServer::get($binary, inProcessHarnessFixturePath());
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
