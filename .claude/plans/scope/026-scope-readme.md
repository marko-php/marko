# Task 026: `marko/scope` README

**Status**: pending
**Depends on**: 001, 002, 003, 004, 005, 006, 007, 008, 009, 010, 011, 012, 013, 014, 015, 016, 017, 032
**Retry count**: 0

## Description
Write the `marko/scope` README per `.claude/code-standards.md` § Package README Standards. Interface-package format: state what it defines, note it has no implementation, show type-hinting.

## Context
- Related files: `packages/scope/README.md` (new)
- Patterns to follow: `packages/database/README.md`, `packages/cache/README.md`. Use the section structure from code-standards: Title + One-Liner, Overview, Installation, Usage, Customization, API Reference.

## Requirements (Test Descriptions)
- [ ] `it has Title + One-Liner section stating the benefit`
- [ ] `it has Overview section of 2-4 sentences`
- [ ] `it has Installation section with composer command`
- [ ] `it has Usage section showing #[Scoped] attribute, ScopeContext, and ScopeResolver`
- [ ] `it has Usage section showing the per-entity ScopedOverridesEntity companion class declaration`
- [ ] `it has Usage section showing ScopedOrderBy QuerySpecification via the ScopedOrderByFactory`
- [ ] `it has a worked Product name scoped by locale example covering entity declaration, companion, config, write, read, and ordered query`
- [ ] `it has a worked Product price scoped by market example covering entity declaration, companion, config, write, read, and ordered query`
- [ ] `it has Customization section noting ScopeRegistryInterface as the DB-driven extension point`
- [ ] `it has API Reference listing the public interfaces and key services`
- [ ] `it has a Caveats section noting (a) ScopeContext is a mutable singleton requiring clearAll() between requests in long-running processes, (b) Repository::insertBatch does not support scoped entities, (c) the terminology overlap with marko/config's tenant scope parameter`

## Acceptance Criteria
- README exists at `packages/scope/README.md`.
- Examples follow code standards (strict_types, no magic, declared types).
- One-liner states "Scoped attributes for entities with multi-axis hierarchical fallback".
- Notes that no driver is bound by default — apps must install `marko/scope-mysql` or `marko/scope-pgsql`.

## Worked Examples (must appear in the Usage section)

### Example 1 — Product name scoped by `locale`
- Demonstrates a string-typed scoped attribute on a single axis with a small hierarchy (e.g. `en` → `en-US`/`en-GB`; `de` → `de-DE`/`de-AT`; `fr` → `fr-FR`/`fr-CA`).
- Shows the `Product` entity with `#[Scoped(axes: ['locale'])]` on `$name`.
- Shows the one-line `ProductScopedOverrides extends ScopedOverridesEntity` companion class.
- Shows `config/scope.php` declaring the `locale` axis and its hierarchy.
- Shows writing a default name, adding a German override via `ScopeResolver::setOverride`, and saving through the standard `Repository::save`.
- Shows reading: plain `$product->name` (column value) vs `$resolver->resolved($product, 'name')` resolving through `ScopeContext::in('locale', 'de-DE')` and walking up to `'de'`.
- Shows `Repository::matching($scopedOrderByFactory->create(Product::class, 'name', 'asc'))` and the emitted SQL fragment using a `COALESCE(scopes->'locale:de-DE'->>'name', scopes->'locale:de'->>'name', name)` pattern.

### Example 2 — Product price scoped by `market`
- Demonstrates a numeric-typed scoped attribute on a different axis (`market`) with a different shape of hierarchy (e.g. `global` root; `eu` with children `eu.de`, `eu.fr`; `us`; `apac` with child `apac.jp`).
- Shows the `Product` entity with `#[Scoped(axes: ['market'])]` on `$price` (typed as `float` or `string` for decimal — pick one and stay consistent within the example).
- Shows that the same `ProductScopedOverrides` companion supports both `name` and `price` overrides — one extender per parent entity, not one per scoped property.
- Shows `config/scope.php` adding the `market` axis alongside `locale`.
- Shows writing a global default price, adding a `market:eu` override (inherited by `eu.de` and `eu.fr`), and a per-country override at `market:eu.de`.
- Shows reading: resolution at `market:eu.fr` falling back from `eu.fr` → `eu` → column; resolution at `market:apac.jp` falling all the way through to the column when no market override exists.
- Shows ordering products by resolved price in the current market context.
- Briefly contrasts with Example 1 to make the "one axis per concern, not one axis for everything" point explicit (locale is *not* market — a French speaker can shop the EU market, etc.). No multi-axis declaration in these examples — that's a separate (linked) note in the Caveats / Advanced section.

## Implementation Notes
(Left blank — filled in during implementation.)
