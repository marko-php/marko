# Task 009: Remove resources/views/ from marko/admin-panel; update composer.json suggest

**Status**: complete
**Depends on**: 006, 007
**Retry count**: 0

## Description
After task 006 moves the templates out of `marko/admin-panel`, this task cleans up. Remove the now-empty `resources/views/` directory, update admin-panel's `composer.json` `suggest` block to list both engine siblings so users discover them at install time, and verify all remaining admin-panel tests pass (controllers, config, menu builder — all engine-agnostic).

## Context
- Files to modify/delete:
  - DELETE: `packages/admin-panel/resources/views/` directory (recursively)
  - MODIFY: `packages/admin-panel/composer.json` — add `suggest` block listing both engine siblings
  - VERIFY: no orphaned files in `packages/admin-panel/tests/Unit/Template/` (LayoutTemplateTest moved in task 006; the `Template/` directory should be empty and removable)

**composer.json `suggest` block to add:**
```json
"suggest": {
    "marko/admin-panel-twig": "Twig templates for the admin panel (recommended for broader ecosystem familiarity)",
    "marko/admin-panel-latte": "Latte templates for the admin panel"
}
```

This pairs with the resolver enhancement (task 002) — installing `marko/admin-panel` alone leaves the controllers without templates to render; the `suggest` block points users to fix this.

**Existing tests to verify pass after cleanup:**
- `tests/Unit/Config/AdminPanelConfigTest.php` — config tests, no template dependency
- `tests/Unit/Controller/DashboardControllerTest.php` — mocks ViewInterface
- `tests/Unit/Controller/LoginControllerTest.php` — mocks ViewInterface
- `tests/Unit/Menu/AdminMenuBuilderTest.php` — no template dependency
- `tests/Unit/PackageStructureTest.php` — verified at plan time that it asserts only on composer.json fields, not on `resources/views/` presence. If a future contributor adds such an assertion before this task runs, remove it.

**Integration verification:**
After cleanup, run `composer test` from the monorepo root. Expectations:
- admin-panel tests all pass
- admin-panel-latte tests all pass (its 7 LayoutTemplateTest assertions plus PackageTest)
- admin-panel-twig tests all pass (its 7 LayoutTemplateTest assertions plus PackageTest)

## Requirements (Test Descriptions)
- [x] `packages/admin-panel/resources/views/ no longer exists`
- [x] `packages/admin-panel/composer.json includes a suggest block`
- [x] `the suggest block lists marko/admin-panel-twig`
- [x] `the suggest block lists marko/admin-panel-latte`
- [x] `the suggest block does not place either engine sibling in require`
- [x] `PackageStructureTest does not assert on resources/views/ presence`
- [x] `all existing admin-panel unit tests continue to pass`

## Acceptance Criteria
- `packages/admin-panel/resources/views/` is gone (directory removed)
- `tests/Unit/Template/` directory removed (since its only file was moved in task 006)
- composer.json `suggest` block added; admin-panel-twig listed first (recommended)
- Neither engine sibling appears in `require` or `require-dev`
- All admin-panel tests pass without templates present
- Code follows code standards
