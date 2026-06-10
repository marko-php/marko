# Task 001: DiscoveryEnvironment reader and shipped config defaults

**Status**: pending
**Depends on**: [none]
**Retry count**: 0

> PREREQUISITE: This plan must land AFTER `tier2-high-correctness/003-classfileparser-tokenizer` is merged. See `_plan.md` Prerequisite note.

## Description
Add `DiscoveryEnvironment`, a core-owned reader that exposes the discovery-cache
settings (`enabled`, `environment`, `cachePath`) by reading `$_ENV` directly. This is
the boot-time config source for the cache gate. Also add the shipped
`packages/core/config/discovery.php` defaults file.

**HARD CONSTRAINT — dependency direction.** `marko/core` requires only `php` +
`psr/container`. `DiscoveryEnvironment` MUST NOT import or reference anything from
`Marko\Config` (no `ConfigRepositoryInterface`, no `ConfigNotFoundException`). The
reason: `marko/config` `require`s `marko/core` (not the reverse), and
`ConfigRepositoryInterface` is only bound by `marko/config`'s `module.php` binding
closure, which fires lazily AFTER all discovery has run. The boot-time gate therefore
cannot use it. `DiscoveryEnvironment` reads `$_ENV` directly — this is the deliberate,
sanctioned bootstrap-layer exception (the same layer where core already invokes
`EnvLoader` and where the architecture doc explicitly allows `module.php` boot
callbacks to read `$_ENV['APP_ENV']`). It IS the config-translation layer for the
bootstrap path.

The shipped `config/discovery.php` exists ONLY so that, when `marko/config` is
installed, the same values are visible/overridable through `ConfigRepositoryInterface`
for the CLI commands (which run after full boot). Core's boot never reads this file.

## Context
- Related files:
  - `/Users/markshust/Sites/marko/packages/core/config/discovery.php` (NEW — first core config file)
  - `/Users/markshust/Sites/marko/packages/core/src/Discovery/DiscoveryEnvironment.php` (NEW)
  - `/Users/markshust/Sites/marko/packages/core/tests/Unit/Discovery/DiscoveryEnvironmentTest.php` (NEW)
- Patterns to follow:
  - `packages/page-cache/config/page-cache.php` — `return [ 'key' => $_ENV['VAR'] ?? default, ... ]`. Mirror this style for `config/discovery.php`. (Do NOT copy `PageCacheConfig` — that class wraps `ConfigRepositoryInterface`, which core cannot use.)
  - `Application::initialize()` line ~114 — `if (class_exists(EnvLoader::class)) { (new EnvLoader())->load($basePath); }` is the existing precedent for env handling in core's bootstrap layer. By the time `DiscoveryEnvironment` is read, `$_ENV` is already populated.
- Standards: strict types, constructor promotion, no final, no magic methods, full type declarations, `readonly` where appropriate.

## Implementation detail
- `DiscoveryEnvironment` getters and defaults (read from `$_ENV`):
  - `enabled(): bool` ← `DISCOVERY_CACHE_ENABLED`, default `true`. Treat the string values `'0'`, `'false'`, `''` (case-insensitive `false`) as `false`; everything else truthy. Document the exact coercion in the test.
  - `environment(): string` ← `APP_ENV`, default `'production'`.
  - `cachePath(): string` ← `DISCOVERY_CACHE_PATH`, default `'storage/cache/discovery.php'` (relative — resolved against `ProjectPaths::$base` by `DiscoveryCache`, Task 002).
- `config/discovery.php` returns `['enabled' => ..., 'environment' => ..., 'cache_path' => ...]` reading the SAME three `$_ENV` keys with the SAME defaults so the two layers never disagree.

## Requirements (Test Descriptions)
- [ ] `it returns enabled() true by default and false when DISCOVERY_CACHE_ENABLED is "0", "false", or empty`
- [ ] `it returns environment() from APP_ENV and defaults to production when APP_ENV is unset`
- [ ] `it returns cachePath() from DISCOVERY_CACHE_PATH and defaults to storage/cache/discovery.php`
- [ ] `it reads no value from marko/config and has no Marko\Config import (boot-time reader is config-package-free)`
- [ ] `the shipped config/discovery.php returns an array with enabled, environment, and cache_path keys matching the DiscoveryEnvironment defaults when no env vars are set`

## Acceptance Criteria
- All requirements have passing tests
- `DiscoveryEnvironment` contains zero references to `Marko\Config\*`
- Code follows code standards
- No decrease in test coverage

## Implementation Notes
(Left blank - filled in by programmer during implementation)
