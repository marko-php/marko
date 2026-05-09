# Task 013: README for marko/page-cache

**Status**: completed
**Depends on**: 002, 003, 004, 005, 006, 007, 008, 009
**Retry count**: 0

## Description
Write `packages/page-cache/README.md` per the Package README Standards in `.claude/code-standards.md`. Interface package: describe what the contract defines, note that you need a driver, show the typical usage with `#[Cacheable]`.

## Context
- Related files:
  - `packages/cache/README.md` (template — interface package README)
  - `.claude/code-standards.md` "Package README Standards" section
- Required sections:
  - Title + One-liner
  - Overview (2–4 sentences)
  - Installation
  - Usage (lead with `#[Cacheable]` example on a controller; mention global middleware registration)
  - Customization (if applicable — Preferences for `CacheabilityChecker` to add custom rules)
  - API Reference (signatures of `PageCacheInterface`, `Cacheable` attribute, key value objects)

## Requirements (Non-Test Acceptance Checklist)

This task has NO executable tests. The checkboxes below are review-checked acceptance criteria, not Pest test descriptions. The TDD worker should NOT attempt to write tests for them — they are checked manually during review.

- [ ] README exists at `packages/page-cache/README.md`
- [ ] All standard sections present (Title, Overview, Installation, Usage, Customization, API Reference)
- [ ] Code examples follow Marko code standards (strict types, readonly, constructor promotion, etc.)
- [ ] Mentions that a driver package (e.g., `marko/page-cache-file`) is required
- [ ] One-liner states benefit upfront (e.g., "Cache full HTTP responses to make pages render in microseconds")
- [ ] References the docs URL pattern used by other packages (e.g., `https://marko.build/docs/packages/page-cache/`)
- [ ] Documents that `PageCacheMiddleware` is auto-registered via `Application::GLOBAL_MIDDLEWARE` (no manual wiring needed)
- [ ] Documents that responses with `Set-Cookie` headers (including non-sensitive analytics cookies) are NOT cached in v1 — known limitation, not a bug

## Acceptance Criteria
- All checkbox requirements met
- README is concise — no fluff, no marketing prose, code speaks for itself
- Examples compile mentally — interface names match real code, attributes used correctly

## Implementation Notes
(Left blank — filled in by programmer during implementation)
