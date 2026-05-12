# Task 022: Update `marko/page-cache` README — Document `CacheTagProvider` + `IdentityInterface`

**Status**: pending
**Depends on**: 017, 018, 019
**Retry count**: 0

## Description

Extend the existing `marko/page-cache` README with documentation for the two new extension points: `CacheTagProviderInterface` (dynamic per-request tags) and `IdentityInterface` (entity-side tag declaration). Follow the project's Package README Standards in `code-standards.md`.

## Context

- Related files:
  - `packages/page-cache/README.md` — existing README from task 013, extend it
- Pattern to follow: `code-standards.md` § "Package README Standards" — Title + One-Liner, Overview, Installation, Usage (lead with common case), Customization (extending), API Reference (signatures only).

**Sections to add or update:**

1. **Usage** — under the existing static-tags example, add a "Dynamic tags from the request" subsection showing `#[Cacheable(tags: ['products'], provider: ProductTagProvider::class)]` with a minimal `CacheTagProviderInterface` implementation. Note that provider tags augment static tags (append + dedupe), and that the provider is resolved via the DI container.

2. **Usage** — add an "Entity-driven invalidation" subsection that:
   - Shows an entity implementing `IdentityInterface`
   - Notes that the actual auto-purge requires installing `marko/page-cache-entity` (link to that package's README)
   - Explains why `IdentityInterface` lives in `marko/page-cache` (so entities depend on the cache contract, not the bridge package)

3. **API Reference** — add single-line signatures for:
   - `CacheTagProviderInterface::tags(Request $request, Cacheable $attribute): array<string>`
   - `IdentityInterface::getIdentities(): array<string>`
   - `Cacheable::__construct(int $ttl, array $tags = [], ?string $provider = null)`

**Style notes:**
- Keep prose minimal — code is the spec
- No emojis
- Single-line signatures in API Reference only; the Usage examples should be runnable as-is

## Requirements (Test Descriptions)

- [ ] `it documents CacheTagProviderInterface with a code example in the Usage section`
- [ ] `it documents the provider parameter on the Cacheable attribute`
- [ ] `it documents IdentityInterface with an entity example in the Usage section`
- [ ] `it points users to marko/page-cache-entity for entity auto-purge`
- [ ] `it lists CacheTagProviderInterface, IdentityInterface, and the new Cacheable signature in the API Reference`

## Acceptance Criteria

- All requirements have passing tests (e.g., string-presence assertions on the README content)
- README still passes the existing structural tests from task 013
- No formatting regressions in unchanged sections

## Implementation Notes

(Left blank - filled in by programmer during implementation)
