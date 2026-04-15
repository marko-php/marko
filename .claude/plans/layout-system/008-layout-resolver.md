# Task 008: LayoutResolver

**Status**: completed
**Depends on**: 002, 003
**Retry count**: 0

## Description
Create the `LayoutResolver` class that reads `#[Layout]` attributes from controller classes and methods. Method-level `#[Layout]` overrides class-level. Returns the resolved root component class name and its `#[Component]` attribute metadata.

## Context
- Related files: `packages/routing/src/RouteDiscovery.php` (reflection pattern)
- Reads `#[Layout]` from method first, falls back to class level
- Validates that the referenced class has a `#[Component]` attribute
- Throws `LayoutNotFoundException` if no `#[Layout]` is found on the controller
- Throws `LayoutNotFoundException` if the referenced class has no `#[Component]` attribute

## Requirements (Test Descriptions)
- [ ] `it resolves layout from class-level attribute`
- [ ] `it resolves layout from method-level attribute`
- [ ] `it prefers method-level layout over class-level layout`
- [ ] `it throws LayoutNotFoundException when no layout attribute exists`
- [ ] `it throws LayoutNotFoundException when referenced class has no Component attribute`
- [ ] `it returns the component class name and its Component attribute`

## Acceptance Criteria
- All requirements have passing tests
- Class is readonly
- Uses constructor property promotion
- Throws loud errors with message, context, and suggestion
- No decrease in test coverage

## Implementation Notes
(Left blank - filled in by programmer during implementation)
