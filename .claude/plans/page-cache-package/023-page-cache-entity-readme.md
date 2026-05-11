# Task 023: README for `marko/page-cache-entity`

**Status**: complete
**Depends on**: 020, 021
**Retry count**: 0

## Description

Write the README for the new `marko/page-cache-entity` bridge package. It's a thin integration package — the README's job is to show users how to opt in (implement `IdentityInterface` on their entities, install this package, done) and explain the moving parts so users can debug if a purge doesn't happen.

## Context

- Related files:
  - `packages/page-cache-entity/README.md` — new
- Pattern to follow: `code-standards.md` § "Package README Standards", plus the structure of `packages/page-cache-file/README.md` for a sibling-driver style.

**Sections to include:**

1. **Title + One-Liner** — "Auto-purge page cache tags when entities change."

2. **Overview** — 2–3 sentences. Observes `EntityCreated`/`EntityUpdated`/`EntityDeleted` from `marko/database`; for each entity that implements `IdentityInterface` (from `marko/page-cache`), invokes `PageCacheInterface::purgeTag()` for every tag returned by `getIdentities()`.

3. **Installation** — composer command.

4. **Usage** — single example: a `Product` entity implementing `IdentityInterface`, a route returning a product, the assertion that `$repository->save($product)` triggers automatic purge of the cached page tagged with `'product-' . $product->id`. No code wiring required after install — observers self-register via `#[Observer]` discovery.

5. **How it works** — short list:
   - Three observer classes (`PurgeOnEntityCreated`, `PurgeOnEntityUpdated`, `PurgeOnEntityDeleted`) auto-discovered via `#[Observer]`
   - Each delegates to `IdentityPurger::purge($entity)`
   - `IdentityPurger` no-ops on entities not implementing `IdentityInterface`
   - `purgeTag()` return values are ignored: drivers commonly return `true` even when no cached page used the tag yet (nothing to purge is not an error), and the rare `false` cases (I/O hiccups) are intentionally silent because surfacing them mid-save would be too noisy

6. **Customization** — note that users can write their own observers if they want different behavior (e.g., async purge); the bundled observers can be removed via a `#[Preference]` if necessary, but normally no customization is needed.

7. **API Reference** — single-line signatures:
   - `IdentityPurger::__construct(PageCacheInterface $pageCache)`
   - `IdentityPurger::purge(Entity $entity): void`

**Style notes:**
- Keep prose minimal
- No emojis
- Lead with the simplest case ("just install and implement the interface")

## Requirements (Test Descriptions)

- [x] `it has a README.md at packages/page-cache-entity/README.md`
- [x] `it documents installation via composer require marko/page-cache-entity`
- [x] `it documents implementing IdentityInterface on an entity`
- [x] `it explains the three observer classes and the IdentityPurger service`
- [x] `it lists the IdentityPurger signatures in the API Reference`

## Acceptance Criteria

- All requirements have passing tests (string-presence assertions on the README)
- README follows Package README Standards
- README matches the style of sibling package READMEs (`marko/page-cache-file`)

## Implementation Notes

- Added 5 README string-presence tests to `packages/page-cache-entity/tests/PackageStructureTest.php`
- Created `packages/page-cache-entity/README.md` with all required sections
- All 27 package tests pass; 7 pre-existing failures in other packages are unrelated
