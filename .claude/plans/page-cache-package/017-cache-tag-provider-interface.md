# Task 017: CacheTagProviderInterface + Add `provider` Param to `#[Cacheable]`

**Status**: complete
**Depends on**: none
**Retry count**: 0

## Description

Add a `CacheTagProviderInterface` to `marko/page-cache` for request-driven dynamic tag computation, and extend the `#[Cacheable]` attribute with an optional `provider` parameter (class-string). This task is purely additive — existing `#[Cacheable]` usage continues to work unchanged. The middleware wiring that actually invokes the provider is task 018.

## Context

- Related files:
  - `packages/page-cache/src/Contracts/PageCacheInterface.php` — sibling interface, reference for style
  - `packages/page-cache/src/Attributes/Cacheable.php` — currently `readonly class` with `int $ttl, array $tags = []`. Add `?string $provider = null` as the third constructor parameter.
  - `packages/page-cache/tests/Unit/Attributes/CacheableTest.php` — extend with coverage for the new `provider` parameter. Existing tests construct `new Cacheable(ttl: ..., tags: [...])` — adding `?string $provider = null` is backwards-compatible and existing tests must continue to pass without modification.
  - `packages/page-cache/tests/Unit/Contracts/` — create `CacheTagProviderInterfaceTest.php` for the new interface tests (namespace existence, method signature via reflection)
- Pattern to follow: All page-cache interfaces sit under `Marko\PageCache\Contracts\`. The new interface lives at `packages/page-cache/src/Contracts/CacheTagProviderInterface.php`.

**Interface shape:**
```php
namespace Marko\PageCache\Contracts;

use Marko\PageCache\Attributes\Cacheable;
use Marko\Routing\Http\Request;

interface CacheTagProviderInterface
{
    /** @return array<string> */
    public function tags(Request $request, Cacheable $attribute): array;
}
```

**Why pass `Cacheable` as an argument:** Lets providers read the static `$attribute->tags` if they want to make decisions based on the route's base tags, without coupling to the middleware.

**Why class-string on the attribute:** PHP attribute constructors only accept compile-time constants — a class-string is fine, an instance is not. The middleware (task 018) resolves the class via the container.

## Requirements (Test Descriptions)

- [x] `it defines CacheTagProviderInterface with a tags method taking Request and Cacheable and returning an array of strings`
- [x] `it places CacheTagProviderInterface under the Marko PageCache Contracts namespace`
- [x] `it extends the Cacheable attribute with an optional provider parameter defaulting to null`
- [x] `it constructs Cacheable without a provider for backwards compatibility`
- [x] `it constructs Cacheable with a provider class-string and exposes it as a public readonly property`

## Acceptance Criteria

- All requirements have passing tests
- `Cacheable` remains a `readonly class` (all three properties readonly)
- No middleware changes — wiring happens in task 018
- Code follows project standards (strict types, constructor property promotion, no `final`)

## Implementation Notes

- Created `packages/page-cache/src/Contracts/CacheTagProviderInterface.php` with `tags(Request $request, Cacheable $attribute): array` method
- Added optional `?string $provider = null` as third constructor parameter to `packages/page-cache/src/Attributes/Cacheable.php` — remains a `readonly class`
- The linter added a proper `use Marko\PageCache\Contracts\CacheTagProviderInterface;` import to `Cacheable.php` for the `@param` docblock type reference
- Created `packages/page-cache/tests/Unit/Contracts/CacheTagProviderInterfaceTest.php` for interface existence and method signature tests
- Extended `packages/page-cache/tests/Unit/Attributes/CacheableTest.php` with 3 new tests for provider parameter
- All existing tests continue to pass unchanged (backwards compatible)
