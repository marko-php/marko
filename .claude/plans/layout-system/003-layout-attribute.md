# Task 003: Layout Attribute

**Status**: completed
**Depends on**: none
**Retry count**: 0

## Description
Create the `#[Layout]` PHP attribute class. This attribute goes on controller classes or methods to designate which component serves as the page root. It accepts a class-string pointing to a class with a `#[Component]` attribute.

## Context
- Related files: `packages/routing/src/Attributes/Middleware.php` (targets both CLASS and METHOD)
- The attribute lives in `packages/layout/src/Attributes/Layout.php`
- Namespace: `Marko\Layout\Attributes`
- When on a class, all methods use that layout. When on a method, it overrides the class-level layout for that action.

## Requirements (Test Descriptions)
- [x] `it can be instantiated with a component class string`
- [x] `it stores the component class as a public property`
- [x] `it targets both classes and methods`

## Acceptance Criteria
- All requirements have passing tests
- Attribute is readonly
- Uses constructor property promotion
- Follows existing attribute patterns (Middleware for dual targeting)
- No decrease in test coverage

## Implementation Notes
- Created `packages/layout/src/Attributes/Layout.php` as a `readonly class` with `#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]`
- Single constructor-promoted property `public string $component`
- Follows the same dual-target pattern as `Marko\Routing\Attributes\Middleware`
- Tests at `packages/layout/tests/Unit/Attributes/LayoutTest.php`
