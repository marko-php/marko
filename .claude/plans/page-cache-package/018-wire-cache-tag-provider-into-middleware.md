# Task 018: Wire `CacheTagProvider` Into `PageCacheMiddleware`

**Status**: complete
**Depends on**: 017
**Retry count**: 0

## Description

Make `PageCacheMiddleware` honor the new `provider` parameter on `#[Cacheable]`. When a provider class-string is present, resolve it from the container, invoke `tags(Request, Cacheable)`, and merge the result with the static `$attribute->tags` (augment behavior: provider tags are appended to static tags and the final list is deduplicated). When no provider is present, behavior is unchanged.

## Context

- Related files:
  - `packages/page-cache/src/Middleware/PageCacheMiddleware.php` — current constructor injects `PageCacheInterface` + `CacheabilityChecker`. Add `Marko\Core\Container\ContainerInterface` (used for provider resolution).
  - `packages/page-cache/src/CachePolicy.php` — value object; no change expected, just construction site updates
  - `packages/page-cache/src/Exceptions/PageCacheException.php` — add a static factory `invalidTagProvider(string $providerClass)` for the loud-error case. The constructor signature of `MarkoException` is `(string $message, string $context = '', string $suggestion = '', int $code = 0, ?Throwable $previous = null)` — pass `message`, `context`, and `suggestion` positionally or by name.
  - `packages/page-cache/tests/Unit/Middleware/PageCacheMiddlewareTest.php` — every existing test constructs `new PageCacheMiddleware($cache, $checker)` (two args). Adding a third constructor parameter is a breaking change for these construction sites and **all of them must be updated to pass a third `ContainerInterface` argument**. Introduce a small inline fake container (or use `Marko\Core\Container\Container` directly) in the existing helper functions so existing tests keep passing.
- Pattern to follow: `LayoutProcessor` already resolves classes via the container (`Marko\Core\Container\ContainerInterface`). Loud errors per `code-standards.md` exception standards (named factory methods on the exception class).

**Merge logic:**
```php
$staticTags = $cacheable->tags;
$dynamicTags = [];

if ($cacheable->provider !== null) {
    $provider = $this->container->get($cacheable->provider);

    if (!$provider instanceof CacheTagProviderInterface) {
        throw PageCacheException::invalidTagProvider($cacheable->provider);
    }

    $dynamicTags = $provider->tags($request, $cacheable);
}

$finalTags = array_values(array_unique([...$staticTags, ...$dynamicTags]));
```

Final `$finalTags` is passed to `new CachePolicy(ttl: $cacheable->ttl, tags: $finalTags)`.

**Important:** The provider is called only on cache miss (after `lookup()` returns null), to avoid the cost of running it on cache hits. Lookups are by request hash (method+path+query), independent of tags.

**Resolution errors:** If `$cacheable->provider` references a class that does not exist or whose dependencies cannot be autowired, the container itself throws (`BindingException` / `ReflectionException`). These are not wrapped — they surface as the normal autowiring error and identify the offending class. `PageCacheException::invalidTagProvider()` is only thrown when the class resolves successfully but does not implement `CacheTagProviderInterface`, since that is a contract violation rather than a missing-binding problem.

## Requirements (Test Descriptions)

- [x] `it stores cached response with only static tags when no provider is configured`
- [x] `it stores cached response with merged static and provider tags when provider is configured`
- [x] `it resolves the provider class from the container`
- [x] `it deduplicates tags when provider returns tags already present in the static list`
- [x] `it preserves the order of static tags before provider tags after deduplication`
- [x] `it throws PageCacheException when the resolved provider does not implement CacheTagProviderInterface`
- [x] `it does not invoke the provider on cache hits`
- [x] `it does not invoke the provider when the response is not cacheable`
- [x] existing middleware tests pass after the constructor signature change (no regression)

## Acceptance Criteria

- All requirements have passing tests
- `PageCacheException::invalidTagProvider()` factory follows the `message`/`context`/`suggestion` triple
- `PageCacheMiddleware` remains `readonly class`
- All existing middleware tests continue to pass (no regression)

## Implementation Notes

- Added `?string $provider = null` as the third constructor parameter to `Cacheable.php` (this was listed as completed in task 017 but the actual file had not been updated)
- Added `invalidTagProvider(string $providerClass)` static factory to `PageCacheException` following the message/context/suggestion triple pattern
- Updated `PageCacheMiddleware` constructor to accept `ContainerInterface` as a third required parameter (remains a `readonly class`)
- Added provider tag merging logic in `handle()`: resolves provider from container, validates it implements `CacheTagProviderInterface` (throws `PageCacheException::invalidTagProvider()` if not), merges tags with `array_values(array_unique([...$staticTags, ...$dynamicTags]))`
- Provider is invoked only on cache miss (after `lookup()` returns null), not on cache hits or uncacheable responses
- Updated all 8 existing middleware tests to pass `FakeContainer` as the 3rd constructor argument
- Added `FakeContainer` implementing `ContainerInterface` as an inline fake in the test file
- Added new controller methods `indexWithProvider`, `indexWithProviderOverlap`, `indexWithInvalidProvider` to `MiddlewareCacheableController` fixture
- `it stores cached response with only static tags when no provider is configured` passed immediately (correct behavior already existed; noted per TDD rules)
