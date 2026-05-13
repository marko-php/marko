# Task 007: `ScopeContext` (request-scoped current scope)

**Status**: pending
**Depends on**: 002, 004, 006
**Retry count**: 0

## Description
Holds the current `Scope` per axis for the active request. Mutable, fluent (`->in($axis, $path)`), validated against the registry. Will be registered as a singleton in `module.php` so the same instance is shared across the request.

## Context
- Related files: `packages/scope/src/Context/ScopeContext.php` (new)
- Patterns to follow: Singleton service pattern; validation via injected `ScopeRegistryInterface`.

## Requirements (Test Descriptions)
- [ ] `it accepts a current scope for an axis via in and is fluent`
- [ ] `it returns the current scope path for a set axis via get`
- [ ] `it returns null from get for an unset axis`
- [ ] `it throws UnknownAxisException when in is called with an unknown axis`
- [ ] `it throws ScopeContextException when in is called with a path not in the axis hierarchy`
- [ ] `it clears a single axis via clear and all axes via clearAll`
- [ ] `it lists all axes currently set via activeAxes`

## Acceptance Criteria
- Constructor takes `ScopeRegistryInterface`.
- Internal state is `array<string, string>` (axis → path).
- Not `readonly class` — mutable state.
- Class docblock explicitly documents the lifecycle: mutable singleton, intended to be set up once per HTTP request / CLI command / queue job. In long-running PHP processes (FPM workers, queue daemons), the bootstrap layer MUST call `clearAll()` between requests/jobs to avoid cross-request leakage. Marked with `@noinspection` notes if the linter complains about mutable singletons.

## Implementation Notes
(Left blank — filled in during implementation.)
