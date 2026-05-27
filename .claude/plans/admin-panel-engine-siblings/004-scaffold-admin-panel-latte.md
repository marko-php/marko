# Task 004: Scaffold marko/admin-panel-latte package

**Status**: completed
**Depends on**: none
**Retry count**: 0

## Description
Create the directory structure and Composer metadata for `marko/admin-panel-latte`. This task only scaffolds — task 006 moves the actual `.latte` files in.

## Context
- New directory: `packages/admin-panel-latte/`
- Reference pattern: `packages/view-twig/` and `packages/view-latte/` (sibling driver packages already in the monorepo)
- Package name: `marko/admin-panel-latte`
- This package has NO PHP source code (no `src/` directory needed) — it's a template-only package. Use a `.gitkeep` in `resources/views/` so the empty directory commits. Tests directory is created since the moved test file will go there in task 006.

**composer.json contents:**
```json
{
    "name": "marko/admin-panel-latte",
    "description": "Latte templates for marko/admin-panel",
    "type": "marko-module",
    "license": "MIT",
    "require": {
        "php": "^8.5",
        "marko/admin-panel": "self.version",
        "marko/view-latte": "self.version"
    },
    "require-dev": {
        "pestphp/pest": "^4.0",
        "marko/testing": "self.version",
        "marko/view": "self.version",
        "marko/core": "self.version"
    },
    "autoload-dev": {
        "psr-4": {
            "Marko\\AdminPanel\\Latte\\Tests\\": "tests/"
        }
    },
    "config": {
        "allow-plugins": {
            "pestphp/pest-plugin": true
        }
    },
    "extra": {
        "marko": {
            "module": true,
            "templates_for": "marko/admin-panel"
        }
    }
}
```

Note: no `psr-4` under `autoload` (top-level), only `autoload-dev` — package ships no PHP code, only templates. No `conflict` block — see _plan.md's Architecture Notes on why engine template siblings don't need mutual-exclusion declarations.

## Requirements (Test Descriptions)
- [ ] `it ships a composer.json with name marko/admin-panel-latte`
- [ ] `it requires marko/admin-panel at self.version`
- [ ] `it requires marko/view-latte at self.version`
- [ ] `it does not declare a Composer conflict block`
- [ ] `it declares extra.marko.templates_for as marko/admin-panel`
- [ ] `it marks the package as a Marko module via extra.marko.module`
- [ ] `it has a tests directory ready for the LayoutTemplateTest move`
- [ ] `it has a resources/views/ directory ready for template files`
- [ ] `the monorepo root composer.json registers packages/admin-panel-latte as a path repository`
- [ ] `the monorepo root composer.json requires marko/admin-panel-latte at self.version`
- [ ] `the monorepo root composer.json autoload-dev includes the Marko\AdminPanel\Latte\Tests namespace`
- [ ] `RootComposerJsonTest.$allPackages includes marko/admin-panel-latte`

## Root monorepo integration (CRITICAL — do not skip)

The monorepo's root `composer.json` and `packages/framework/tests/RootComposerJsonTest.php` track every package. After PR #92 dropped the view-latte ↔ view-twig conflict, every multi-driver/multi-sibling family follows the same shape: both siblings registered + both in `require`. Apply this pattern here:

- Root `composer.json` `repositories[]`: add `{"type": "path", "url": "packages/admin-panel-latte"}` (alphabetical position — between `admin-panel` and `amphp`)
- Root `composer.json` `require`: add `"marko/admin-panel-latte": "self.version"` (alphabetical position)
- Root `composer.json` `autoload-dev.psr-4`: add `"Marko\\AdminPanel\\Latte\\Tests\\": "packages/admin-panel-latte/tests/"`
- `packages/framework/tests/RootComposerJsonTest.php` `$allPackages` array: add `'marko/admin-panel-latte'` (alphabetical)
- Update the package-count string in the test descriptions (currently "71 marko packages" after PR #92; task 005 will bump it to 72 when admin-panel-twig is added)

Both admin-panel-latte AND admin-panel-twig will end up in root `require` simultaneously — fine because no Composer conflict exists. The template resolver picks the right files based on `view.extension`.

## Acceptance Criteria
- `packages/admin-panel-latte/composer.json` exists with correct schema, no `conflict` block
- `packages/admin-panel-latte/LICENSE` exists (MIT, matching the repo)
- `packages/admin-panel-latte/.gitattributes` exists (mirroring view-latte's)
- `packages/admin-panel-latte/tests/Pest.php` exists (minimal Pest bootstrap mirroring `packages/view-latte/tests/Pest.php`) so Pest can run tests in this package standalone
- `packages/admin-panel-latte/phpunit.xml` exists (mirroring sibling packages) so `pest` can be invoked from the package root
- Directories created with `.gitkeep` files: `resources/views/`, `tests/` (the `.gitkeep` in `tests/` is removed once `Pest.php` lands; the `.gitkeep` in `resources/views/` is removed by task 006 when real templates land)
- `require-dev` includes `marko/view` and `marko/core` (needed by task 006's integration test that exercises `ModuleTemplateResolver`)
- No `src/` directory created (template-only package)
- No `module.php` in this task (templates don't need DI bindings)
- Composer validates the file (`composer validate`)
- Code follows code standards
