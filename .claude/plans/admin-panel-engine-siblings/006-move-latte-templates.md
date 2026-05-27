# Task 006: Move .latte templates and LayoutTemplateTest into admin-panel-latte

**Status**: completed
**Depends on**: 002, 004
**Retry count**: 0

## Description
Physically move all 5 `.latte` template files and the `LayoutTemplateTest.php` test from `marko/admin-panel` into `marko/admin-panel-latte`. After this task, `marko/admin-panel/resources/views/` contains no templates — that cleanup happens in task 009. The resolver enhancement from task 002 makes admin-panel-latte's templates resolvable via `admin-panel::path` syntax.

## Context
- Files to move (source → destination):
  - `packages/admin-panel/resources/views/auth/login.latte` → `packages/admin-panel-latte/resources/views/auth/login.latte`
  - `packages/admin-panel/resources/views/layout/base.latte` → `packages/admin-panel-latte/resources/views/layout/base.latte`
  - `packages/admin-panel/resources/views/dashboard/index.latte` → `packages/admin-panel-latte/resources/views/dashboard/index.latte`
  - `packages/admin-panel/resources/views/partials/sidebar.latte` → `packages/admin-panel-latte/resources/views/partials/sidebar.latte`
  - `packages/admin-panel/resources/views/partials/flash.latte` → `packages/admin-panel-latte/resources/views/partials/flash.latte`
  - `packages/admin-panel/tests/Unit/Template/LayoutTemplateTest.php` → `packages/admin-panel-latte/tests/LayoutTemplateTest.php`
- The test file uses `dirname(__DIR__, 3) . '/resources/views'`. After the move, the test is at `packages/admin-panel-latte/tests/LayoutTemplateTest.php`, so the path needs to become `dirname(__DIR__) . '/resources/views'` (1 level up, not 3). Verify and adjust.
- The existing `LayoutTemplateTest.php` is a Pest test file with no `namespace` declaration and no class — only top-level `it(...)` calls. Do NOT add a namespace; the file is namespace-less by design. The PSR-4 mapping in `admin-panel-latte/composer.json` (`Marko\AdminPanel\Latte\Tests\` → `tests/`) is for any future class-based tests; the moved Pest file simply lives in the directory.
- Use `git mv` (not file-by-file copy/delete) to preserve git history.
- Templates use `{include 'admin-panel::partials/sidebar'}` syntax — this continues to work because the resolver enhancement (task 002) finds admin-panel-latte via `templates_for`.

**Verify the resolver enhancement works end-to-end:**
After moving, write a small integration test (in `packages/admin-panel-latte/tests/`) that:
1. Sets up a minimal container with a real `ModuleTemplateResolver`
2. Asks for `admin-panel::dashboard/index`
3. Asserts the resolved path is the new sibling location

This catches the failure case where the resolver enhancement is incomplete.

## Requirements (Test Descriptions)
- [ ] `all 5 .latte template files exist in packages/admin-panel-latte/resources/views/`
- [ ] `no .latte files remain in packages/admin-panel/resources/views/`
- [ ] `LayoutTemplateTest.php exists in packages/admin-panel-latte/tests/`
- [ ] `LayoutTemplateTest.php has been removed from packages/admin-panel/tests/Unit/Template/`
- [ ] `LayoutTemplateTest.php passes against the moved templates (path references updated)`
- [ ] `ModuleTemplateResolver resolves admin-panel::dashboard/index to the new admin-panel-latte path`
- [ ] `the moved Pest file uses dirname(__DIR__) for $viewsPath (one level up)`
- [ ] `the .gitkeep file in resources/views/ is removed once real templates land`

## Acceptance Criteria
- All 5 template files moved (preserved byte-for-byte via `git mv`)
- LayoutTemplateTest moved with namespace and path adjustments
- Existing test assertions still pass against templates in the new location
- New integration test verifies end-to-end resolution via the resolver enhancement
- Code follows code standards
