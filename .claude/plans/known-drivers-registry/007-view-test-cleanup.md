# Task 007: Clean up marko/view test suite — zero dependency on marko/view-latte

**Status**: pending
**Depends on**: none
**Retry count**: 0

## Description
`packages/view/tests/Feature/IntegrationTest.php` currently uses `Marko\View\Latte\LatteEngineFactory` and `Marko\View\Latte\LatteViewConfig` — hard dependencies on `marko/view-latte` from inside the interface package's test suite. This violates the principle that the interface package must be installable standalone (e.g., when a user is writing their own template engine driver outside Marko's core). Move the integration test into `marko/view-latte/tests/Feature/` where it logically belongs.

## Context
- Files to modify/move:
  - DELETE: `packages/view/tests/Feature/IntegrationTest.php`
  - CREATE: `packages/view-latte/tests/Feature/IntegrationTest.php` (moved file, namespace and any `use` statements adjusted)
- The integration test exercises Latte-specific behavior (LatteEngineFactory + ModuleTemplateResolver end-to-end). It tests `marko/view-latte`, not `marko/view`.
- After the move, `packages/view/tests/` must contain only tests that exercise `marko/view`'s own contracts — no Latte- or Twig-specific imports.

**Verify zero-dependency after move:**
1. Search `packages/view/tests/` for any remaining import of `Marko\View\Latte\*` or `Marko\View\Twig\*`. Should return zero matches.
2. Run `/opt/homebrew/Cellar/php/8.5.1_2/bin/php ./vendor/bin/pest packages/view/tests/ --parallel` — all tests pass.
3. Verify the moved test still passes: `/opt/homebrew/Cellar/php/8.5.1_2/bin/php ./vendor/bin/pest packages/view-latte/tests/Feature/IntegrationTest.php --parallel`.

**Namespace adjustment:** the moved file's namespace likely changes from `Marko\View\Tests\Feature` to `Marko\View\Latte\Tests\Feature` to match its new location. Update Pest's `uses()` if applicable.

## Requirements (Test Descriptions)
- [ ] `marko/view test suite contains no imports of Marko\\View\\Latte namespace`
- [ ] `the moved IntegrationTest passes in its new location under marko/view-latte/tests/Feature/`
- [ ] `all existing marko/view tests continue to pass after the cleanup`
- [ ] `all existing marko/view-latte tests continue to pass after the cleanup`

**Note on view-twig:** the task description mentions checking for `Marko\View\Twig\*` imports, but no such package exists in the monorepo today. The grep for that namespace will return zero by default — this is correct/expected, not an assertion failure.

## Acceptance Criteria
- Old file deleted, new file created at the new path
- Namespace updated to reflect new location
- Grep for `Marko\\View\\Latte` in `packages/view/tests/` returns zero results
- Both test suites pass independently
- Code follows code standards
