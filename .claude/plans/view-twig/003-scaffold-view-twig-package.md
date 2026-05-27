# Task 003: Scaffold marko/view-twig package structure + composer.json

**Status**: pending
**Depends on**: none
**Retry count**: 0

## Description
Create the directory structure and Composer metadata for the new `marko/view-twig` package. Mirror the shape of `packages/view-latte/`. This task only scaffolds; subsequent tasks fill in the PHP code.

## Context
- Related files (to mirror):
  - `packages/view-latte/composer.json`
  - `packages/view-latte/.gitattributes`
  - `packages/view-latte/LICENSE`
- New directory: `packages/view-twig/` with subdirectories `src/`, `tests/`, `config/`
- Package name: `marko/view-twig`
- Namespace: `Marko\View\Twig\` → `src/`
- Test namespace: `Marko\View\Twig\Tests\` → `tests/`
- Twig library: `twig/twig ^3.0`
- Add `"conflict": {"marko/view-latte": "*"}` to enforce mutual exclusion
- Add `extra.marko.module = true` (per `view-latte` pattern)

## Requirements (Test Descriptions)
- [ ] `it ships a composer.json with name marko/view-twig`
- [ ] `it requires php ^8.5`
- [ ] `it requires marko/view at self.version`
- [ ] `it requires twig/twig ^3.0`
- [ ] `it declares a Composer conflict with marko/view-latte`
- [ ] `it autoloads Marko\\View\\Twig namespace from src/`
- [ ] `it autoloads Marko\\View\\Twig\\Tests namespace from tests/`
- [ ] `it marks the package as a Marko module via extra.marko.module`

## Acceptance Criteria
- `packages/view-twig/composer.json` exists with correct schema
- `packages/view-twig/LICENSE` exists (MIT, matching the repo)
- `packages/view-twig/.gitattributes` exists (matching `view-latte`'s)
- `packages/view-twig/src/` and `packages/view-twig/tests/` and `packages/view-twig/config/` directories exist (use a `.gitkeep` file in each so empty dirs are committed before later tasks add real files)
- Composer can resolve the package (valid JSON, valid schema)
- Do NOT create `module.php`, `src/*.php`, or `config/view.php` in this task — those are created by tasks 004–009
- Code follows code standards

## Implementation Notes
(Left blank — filled in by programmer during implementation)
