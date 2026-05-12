# Task 003: #[Cacheable] Attribute

**Status**: completed
**Depends on**: 001
**Retry count**: 0

## Description
Create the `#[Cacheable]` PHP attribute that controllers apply to action methods to opt them into page caching. The attribute carries `ttl` (seconds) and `tags` (array of strings).

## Context
- Related files:
  - `packages/routing/src/Attributes/Middleware.php` (template — readonly attribute with constructor)
  - `packages/core/src/Attributes/Command.php` (template for an attribute with named-parameter constructor)
- Patterns to follow:
  - `#[Attribute(Attribute::TARGET_METHOD)]`
  - `readonly class`
  - Constructor property promotion
  - Public properties so reflection can read them directly

## Requirements (Test Descriptions)
- [ ] `it stores ttl and tags as public properties`
- [ ] `it accepts an empty tags array by default`
- [ ] `it can be discovered via reflection on a method that declares it`

## Acceptance Criteria
- `src/Attributes/Cacheable.php` is a `readonly class` with `#[Attribute(Attribute::TARGET_METHOD)]`
- Constructor: `__construct(public int $ttl, public array $tags = [])`
- Tests in `tests/Unit/Attributes/CacheableTest.php` cover the three requirements above (use a fixture class with a `#[Cacheable]`-annotated method to verify reflection)
- Strict types declared
- No `@throws` needed

## Implementation Notes
(Left blank — filled in by programmer during implementation)
