# Task 005: Scaffold marko/admin-panel-twig package

**Status**: pending
**Depends on**: none
**Retry count**: 0

## Description
Create the directory structure and Composer metadata for `marko/admin-panel-twig`. This task only scaffolds — task 007 writes the actual `.twig` template files. After PR #92, twig siblings sit in the monorepo symmetrically with latte siblings — both in `repositories[]` and `require`.

## Context
- New directory: `packages/admin-panel-twig/`
- Reference pattern: task 004's spec for `admin-panel-latte` (mirror it, swap engine). Also `packages/view-twig/` post-PR-#92 (fully registered monorepo package).
- Package name: `marko/admin-panel-twig`
- No PHP source code (template-only package)

**composer.json contents:**
```json
{
    "name": "marko/admin-panel-twig",
    "description": "Twig templates for marko/admin-panel",
    "type": "marko-module",
    "license": "MIT",
    "require": {
        "php": "^8.5",
        "marko/admin-panel": "self.version",
        "marko/view-twig": "self.version"
    },
    "require-dev": {
        "pestphp/pest": "^4.0",
        "marko/testing": "self.version",
        "marko/view": "self.version",
        "marko/core": "self.version"
    },
    "autoload-dev": {
        "psr-4": {
            "Marko\\AdminPanel\\Twig\\Tests\\": "tests/"
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

No `conflict` block. No `psr-4` under `autoload` (top-level) — template-only package.

## Requirements (Test Descriptions)
- [ ] `it ships a composer.json with name marko/admin-panel-twig`
- [ ] `it requires marko/admin-panel at self.version`
- [ ] `it requires marko/view-twig at self.version`
- [ ] `it does not declare a Composer conflict block`
- [ ] `it declares extra.marko.templates_for as marko/admin-panel`
- [ ] `it marks the package as a Marko module via extra.marko.module`
- [ ] `it has a tests directory ready for the Twig LayoutTemplateTest`
- [ ] `it has a resources/views/ directory ready for template files`
- [ ] `the monorepo root composer.json registers packages/admin-panel-twig as a path repository`
- [ ] `the monorepo root composer.json requires marko/admin-panel-twig at self.version`
- [ ] `the monorepo root composer.json autoload-dev includes the Marko\AdminPanel\Twig\Tests namespace`
- [ ] `RootComposerJsonTest.$allPackages includes marko/admin-panel-twig`

## Root monorepo integration (CRITICAL — do not skip)

Symmetric with task 004 — both engine siblings are first-class monorepo packages now that PR #92 removed the conflict pattern:

- Root `composer.json` `repositories[]`: add `{"type": "path", "url": "packages/admin-panel-twig"}` (alphabetical position — between `admin-panel-latte` and `amphp`)
- Root `composer.json` `require`: add `"marko/admin-panel-twig": "self.version"` (alphabetical position)
- Root `composer.json` `autoload-dev.psr-4`: add `"Marko\\AdminPanel\\Twig\\Tests\\": "packages/admin-panel-twig/tests/"`
- `packages/framework/tests/RootComposerJsonTest.php` `$allPackages`: add `'marko/admin-panel-twig'` (alphabetical, after `admin-panel-latte` from task 004)
- Update package-count strings: task 004 bumped to 72; this task bumps to 73

Verify: after task 004 + this task, `composer install` from the monorepo root succeeds with BOTH siblings installed simultaneously.

## Acceptance Criteria
- `packages/admin-panel-twig/composer.json` exists with correct schema, no `conflict` block
- `packages/admin-panel-twig/LICENSE` exists (MIT)
- `packages/admin-panel-twig/.gitattributes` exists
- `packages/admin-panel-twig/tests/Pest.php` exists (minimal Pest bootstrap)
- `packages/admin-panel-twig/phpunit.xml` exists (so `pest` can run from the package root)
- Directories created with `.gitkeep` files: `resources/views/`, `tests/` (cleaned up once real files land)
- `require-dev` includes `marko/view` and `marko/core` (Twig templates are tested with file_get_contents — no resolver integration here; the deps are kept symmetric with admin-panel-latte for consistency and future integration tests)
- No `src/` or `module.php`
- Composer validates the file
- Code follows code standards
