# Task 006: Per-Request Reset Lifecycle

**Status**: pending
**Depends on**: 004, 005
**Retry count**: 0

## Description
Wire a per-request reset for everything task 005's spike confirmed leaks. This is the security-critical part of the package: a missed reset means one user's session or identity bleeds into another user's request.

## Context
- Read `packages/docs-markdown/docs/packages/roadrunner-state-leaks.md` (task 005's deliverable) FIRST. Wire what it found and nothing else — do not add speculative resets for services the spike cleared.
- #150 task 009 already made `Session` and `SessionGuard` request-scoped at the source, and both implement `ResettableInterface`. This task **invokes** those seams from the worker; it does not reimplement them.
- The reset runs inside the accept loop, between requests, in the worker request handler from task 004. Reset **before** each request rather than after, so a request that throws or a worker killed mid-request cannot leave the next one with stale state.
- `ResettableInterface` lives in `marko/core` and is delivered by #150 task 008. Prefer discovering resettables through it over hardcoding a list: #150 task 011 adds a `Container` accessor returning already-resolved instances, so the worker can filter those for `ResettableInterface` and reset them generically.
- That accessor must never force instantiation, and neither must this reset — resetting a service that was never resolved for this request would construct it needlessly.
- Keep a loud fallback: if a service known to need resetting is absent from the resolved set when it should be present, fail rather than silently skipping it.
- This plan changes NOTHING outside `packages/roadrunner/`.
- Resetting must be loud on failure: if a service that should be resettable cannot be reset, fail the request rather than silently serving stale state. Silent degradation here is a cross-user data leak.
- Keep the reset ordering deterministic and documented — some resets may depend on others.

### The container cannot tell you what has been resolved

`Marko\Core\Container\Container` exposes only `get()`, `has()` and `instance()`. `has()` returns `isset($this->bindings[$id]) || class_exists($id)` (line 61) — it is true for **any class that exists**, so it is useless as an "already instantiated" probe, and `$instances` is private with no accessor.

Consequences the implementation must respect:
- The reset list is an **explicit, enumerated list** derived from the spike findings. There is no way to iterate resolved singletons, and adding one is a core change this plan forbids.
- Calling `$container->get(X)` to reset X **instantiates X** if it was not already resolved. For `SessionInterface` that is harmless. For a database connection it means opening a connection on every request, including requests that never touch the database. Reset only what is cheap to resolve, or what the spike proved is already instantiated at boot.

### `database-readwrite` specifics — verified

- `resetStickyState()` lives on the concrete `Marko\Database\ReadWrite\Connection\ReadWriteConnection` (line 133), **not** on `ConnectionInterface`. Detect with a `class_exists()`-guarded `instanceof` on the resolved `ConnectionInterface`, not with a package-installed check.
- `database-readwrite/module.php:44-45` registers the connection via `Container::instance()` inside a `boot` callback that returns early unless `config('database.driver') === 'readwrite'`. So it is already instantiated at boot when active, and absent entirely when not — a `$container->get(ConnectionInterface::class)` is safe when `marko/database` is installed, and must be skipped when it is not.
- Plugin interception generates subclasses at runtime; `instanceof` still holds, a `get_class() === ...` comparison would not.

## Requirements (Test Descriptions)
- [ ] `it resets every service identified by the spike between requests`
- [ ] `it isolates session state between two sequential requests`
- [ ] `it isolates the authenticated user between two sequential requests`
- [ ] `it resets read write sticky state between requests`
- [ ] `it skips the read write reset when the database package is not installed`
- [ ] `it does not instantiate a service that the request never used`
- [ ] `it resets before the request rather than after`
- [ ] `it still resets after a request throws`
- [ ] `it fails the request loudly when a reset cannot be performed`
- [ ] `it performs resets in a deterministic order`

## Acceptance Criteria
- All requirements have passing tests
- Every leak in the spike findings has a corresponding reset and test, or a recorded reason why it needs none
- The reset target list is explicit and documented; no attempt is made to enumerate container instances
- No file under `packages/core/` is modified
- Code follows code standards

## Implementation Notes
