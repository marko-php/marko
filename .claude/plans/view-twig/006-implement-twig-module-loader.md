# Task 006: Implement ModuleLoader for Twig

**Status**: pending
**Depends on**: 003
**Retry count**: 0

## Description
Create `ModuleLoader` implementing `Twig\Loader\LoaderInterface`. Resolves module-namespaced templates (`module::path/to/template`) via the shared `TemplateResolverInterface`. Mirrors the responsibility of view-latte's `ModuleLoader` but adapts to Twig's loader contract.

## Context
- Related files:
  - `packages/view-twig/src/ModuleLoader.php` (new)
  - `packages/view-twig/tests/ModuleLoaderTest.php` (new)
  - `packages/view-latte/src/ModuleLoader.php` (reference pattern, but Latte's interface differs)
- `Twig\Loader\LoaderInterface` requires:
  - `getSourceContext(string $name): \Twig\Source` — returns the template source wrapped in `Twig\Source($code, $name, $path)`
  - `getCacheKey(string $name): string` — return the resolved absolute path (unique per template)
  - `isFresh(string $name, int $time): bool` — return `filemtime($path) <= $time`
  - `exists(string $name): bool` — return true if `TemplateResolverInterface::resolve($name)` returns a path that file_exists, false otherwise (must NOT throw)
- Path resolution delegates to `TemplateResolverInterface::resolve($name)` (same approach as view-latte's loader)
- For `exists()`: the resolver throws `TemplateNotFoundException` when the template isn't found. Catch it and return false — Twig's `exists()` must be non-throwing per its contract.
- **Format enforcement (relative-include rejection):** Unlike Latte's `Loader`, Twig's `LoaderInterface` has no `getReferredName()` hook for normalizing include names — Twig passes include names verbatim to the loader. Enforce the `module::path` format at the entry of `getSourceContext()`, `getCacheKey()`, and `exists()`:
  - In `getSourceContext()` and `getCacheKey()`: if `!str_contains($name, '::')`, throw a clear `\Twig\Error\LoaderError` ("Template includes must use module namespace format (e.g., 'blog::post/list/item'). Got '$name'.") — this surfaces at compile/render time with the offending name.
  - In `exists()`: if `!str_contains($name, '::')`, return false (do not throw — keeps the contract intact). This is consistent with how `exists()` handles unknown templates.
- **File read errors:** When `getSourceContext()` resolves a path, the file should exist (resolver checks via `file_exists`). But guard against race conditions: if `file_get_contents()` returns `false`, throw a `\Twig\Error\LoaderError` with the path included.
- Extract a private `resolvePath(string $name): string` helper that calls `$this->resolver->resolve($name)` — mirrors view-latte's loader shape.

## Requirements (Test Descriptions)
- [ ] `it returns Twig Source containing the template content when getSourceContext is called`
- [ ] `it returns the resolved absolute path as the cache key`
- [ ] `it returns true from isFresh when file modification time is older than the given time`
- [ ] `it returns false from isFresh when file modification time is newer than the given time`
- [ ] `it returns true from exists when the resolver finds the template`
- [ ] `it returns false from exists when the resolver cannot find the template`
- [ ] `it does not throw from exists when the resolver throws TemplateNotFoundException`
- [ ] `it returns false from exists when the name lacks module-namespaced format`
- [ ] `it throws LoaderError from getSourceContext when the name lacks module-namespaced format`
- [ ] `it throws LoaderError from getCacheKey when the name lacks module-namespaced format`
- [ ] `it throws LoaderError from getSourceContext when the resolved file cannot be read`

## Acceptance Criteria
- `ModuleLoader` implements `\Twig\Loader\LoaderInterface`
- Constructor takes `TemplateResolverInterface` via constructor injection
- All four interface methods implemented correctly
- `exists()` is non-throwing per Twig's contract (returns false for unknown templates and for names lacking `::`)
- Relative includes / bare names (no `::`) produce a loud, actionable `\Twig\Error\LoaderError` from `getSourceContext()` and `getCacheKey()`
- File read failures in `getSourceContext()` surface as `\Twig\Error\LoaderError` with the offending path
- Code follows code standards

## Implementation Notes
(Left blank — filled in by programmer during implementation)
