# Task 007: Guard Rails for Worker-Unsafe Packages

**Status**: completed
**Depends on**: 001
**Retry count**: 0

## Description
Detect packages that cannot work correctly under a long-running worker and either refuse to boot or warn, with messages that explain the problem and the fix. This is where the RoadRunner-specific knowledge of what is unsafe lives — which is the main reason this is a separate package rather than a core change.

This task needs only the package skeleton — it inspects the module registry and does not touch the bridges or the accept loop. Task 004 calls into it; that direction of dependency does not require this task to wait.

## Context
- **`marko/sse` — refuse to boot, but with an escape hatch.** `StreamingResponse::send()` (`packages/sse/src/StreamingResponse.php:44,57`) uses `ob_end_flush()` and `flush()` and streams for the life of the connection. That is fundamentally incompatible with a request/response worker model.
- **`marko/debugbar` — warn, do not refuse.** It reads `$_SERVER` directly in five places (`Debugbar.php:517`, `Controller/ProfilerController.php:92`, `Collectors/RequestCollector.php:18-39`, `Collectors/InertiaCollector.php:127`), holds a `Debugbar::$current` static, and its `module.php` boot callback calls `Debugbar::boot()` which does `ob_start()` (line 113) — permanently, since boot runs once. It is a dev-only tool, so warn loudly and continue rather than blocking.
- Follow framework principle #1 (loud errors): every message states what is wrong, why it matters in worker mode, and what to do about it. A bare "incompatible package" string is not acceptable.
- Detection should be based on what is actually installed/registered rather than a hardcoded string match on class names where a real signal is available — `ModuleRepositoryInterface` is registered in the container by `Application::initialize()` (`packages/core/src/Application.php:203`) and exposes the resolved module manifests. Prefer that over `class_exists()`.
- Guard rails run once at boot, before the accept loop — not per request.
- The exception type should live in this package's `Exceptions/` directory following the convention in sibling packages.

### Presence of a package is a blunt signal — it needs an override

`marko/sse` being installed does not mean the app serves SSE routes under this worker. An app may install it for a route it serves through a separate FPM pool, or as a transitive dependency, or for an endpoint it has since retired. Hard-refusing with no way out contradicts the framework's own stated position: *"Opinionated, not restrictive. Every 'no' comes with a 'yes, this way instead.'"* A user with a legitimate reason would be forced to uninstall a package to start a server.

Requirements:
- Refuse by **default** when `marko/sse` is installed. The default must be the safe one.
- Provide a documented config escape hatch (e.g. `roadrunner.acknowledged_unsafe_packages`) that downgrades the refusal to a warning for a named package. The refusal message must name the exact config key and value that would allow the boot — that is what makes it a "yes, this way instead" rather than a wall.
- Read it through `ConfigRepositoryInterface` with a config file shipped in `packages/roadrunner/config/`, per the framework's "config files are the single source of truth" rule. No hardcoded defaults in the checker.
- The **real** protection is not this check. Task 003's bridge throws when a `StreamingResponse` reaches it, per request, with no override. Say so in the warning so an operator who overrides knows exactly what they will get: a loud 500 on the SSE route, not a silently truncated stream.

## Requirements (Test Descriptions)
- [x] `it refuses to boot when the sse package is installed`
- [x] `it explains why sse cannot work in worker mode when refusing`
- [x] `it names the config override in the refusal message`
- [x] `it boots with a warning when the sse package is explicitly acknowledged in config`
- [x] `it warns but continues when the debugbar package is installed`
- [x] `it boots without complaint when no unsafe package is installed`
- [x] `it reads installed modules from the module repository rather than class existence`
- [x] `it runs guard rail checks once rather than per request`

## Acceptance Criteria
- All requirements have passing tests
- Every message names the package, the reason, the remedy, and the override
- A default config file ships in `packages/roadrunner/config/` and no default is hardcoded in the checker
- Code follows code standards

## Implementation Notes

- New class `Marko\Roadrunner\GuardRails\UnsafePackageChecker` (`packages/roadrunner/src/GuardRails/UnsafePackageChecker.php`), a `readonly class` constructor-injected with `ModuleRepositoryInterface` and `ConfigRepositoryInterface`. Public API is `check(): array` — returns a list of warning strings for acknowledged/dev-only unsafe packages, and throws `UnsafePackageException` for a refused package. It never touches `class_exists()`; detection reads `$moduleRepository->all()` once per call and matches on `ModuleManifest::$name`.
- New exception `Marko\Roadrunner\Exceptions\UnsafePackageException` (`packages/roadrunner/src/Exceptions/UnsafePackageException.php`) extends `MarkoException` with a single static factory `incompatibleWithWorkerMode(package, reason, configKey)`. The `message` explains what's wrong (streams for the life of the connection via `ob_end_flush()`/`flush()`, incompatible with a worker returning to its accept loop); the `suggestion` names the exact config key/value that overrides it (e.g. `roadrunner.acknowledged_unsafe_packages' => ['marko/sse']`) and states plainly what an operator gets by overriding: the boot-time check is only a courtesy, the per-request bridge still throws on a `StreamingResponse`, so it's a loud 500 on the SSE route rather than a silently truncated stream.
- `marko/sse`: refused by default; downgraded to a warning (also naming the per-request-500 caveat) when `marko/sse` appears in the `roadrunner.acknowledged_unsafe_packages` config array.
- `marko/debugbar`: always a warning, never a refusal (dev-only tool) — no config override needed since nothing is ever blocked.
- Config default ships at `packages/roadrunner/config/roadrunner.php` (`'acknowledged_unsafe_packages' => []`) — the checker itself has no hardcoded default and reads the key via `ConfigRepositoryInterface::getArray('roadrunner.acknowledged_unsafe_packages')` with no second-argument default, per the config-getter convention. The `roadrunner.` scope prefix matches `ConfigDiscovery`'s filename-as-scope behavior (`packages/config/src/ConfigDiscovery.php`), verified directly against `ConfigRepository::resolveKey()`'s dot-notation resolution — not assumed.
- `packages/roadrunner/composer.json`: added `marko/config` to `require` (the checker's production dependency) and `marko/testing` to `require-dev` (for `FakeConfigRepository` in tests).
- Test support: `packages/roadrunner/tests/Helpers.php` (registered in root `composer.json` `autoload-dev.files`, alphabetical position between `ratelimiter` and `security`) provides `createModuleRepository(array $modules, ?Closure $onAll = null)` — an anonymous `ModuleRepositoryInterface` stub with an optional spy callback — and `catchThrowable(Closure $callback): ?Throwable`, used to assert on exception message/suggestion content without a `try`/`catch` block per test.
- Tests live in `packages/roadrunner/tests/GuardRails/UnsafePackageCheckerTest.php`, one `describe('UnsafePackageChecker', ...)` block, 8 tests / 15 assertions.
- TDD notes on over-implementation: requirement 1 ("refuses to boot...") was driven red→green with a bare `check(): void` that unconditionally threw for `marko/sse`. Requirements 2 and 3 ("explains why", "names the config override") passed immediately once written, because the loud-errors exception design (message = reason, suggestion = remedy/override) was necessarily written in full for requirement 1 to be a real refusal rather than a bare string — there was no way to build a partial, unexplained refusal first. Requirements 6, 7 and 8 ("boots without complaint", "reads from the module repository not class existence", "runs once") also passed immediately: they are direct consequences of requirement 5's implementation (a single `array_map` over `$moduleRepository->all()`, matched by name, with no `class_exists()` anywhere) rather than gaps that needed separate code. Requirements 4 and 5 (config-downgrade for `marko/sse`; always-warn for `marko/debugbar`) were the two genuine red→green cycles — each failed for the expected reason (still-unconditional throw; package not yet recognized) before the fix.
- Verification: `./vendor/bin/pest -c phpunit.xml packages/roadrunner/tests/ --parallel` → 43 passed. `composer phpstan` → 0 errors (75 files analysed, up from 74 with `Exceptions/` and `GuardRails/` added). `./vendor/bin/phpcs --standard=phpcs.xml packages/roadrunner/{src,tests,config}` → clean. `./vendor/bin/php-cs-fixer fix packages/roadrunner` → fixed 2 files (`@throws` tag consolidation, import-group ordering — both on the auto-fixed list, re-verified with phpcs/pest afterward). Full suite: `./vendor/bin/pest -c phpunit.xml --parallel --exclude-group=integration-destructive` → 7024 passed, 0 failed (up from the 6986-passing baseline this task started from; the remaining delta is other in-flight roadrunner tasks' files already present in the working tree, not part of this task's diff).
