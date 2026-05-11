# Task 020: `marko/page-cache-entity` Package Scaffolding

**Status**: pending
**Depends on**: none
**Retry count**: 0

## Description

Create the `marko/page-cache-entity` bridge package — directory structure, `composer.json`, `module.php`, root monorepo autoload entries, and structural tests verifying the package layout. No business logic yet; that's task 021.

## Context

- Related files (reference patterns):
  - `packages/page-cache-file/composer.json` — sibling driver package, mirror its `require` style
  - `packages/page-cache-file/module.php` — sibling driver package module file
  - `packages/page-cache-file/tests/PackageStructureTest.php` — structure-test pattern (uses `dirname(__DIR__, 3)` to reach the root composer.json)
  - The root `/composer.json` — append entries for the new package's autoload + path repository
- Pattern to follow: Identical layout to `packages/page-cache-file` minus the driver class.

**Directory structure to create:**
```
packages/page-cache-entity/
  composer.json
  module.php
  LICENSE                    # copy from packages/page-cache-file/LICENSE
  .gitattributes             # copy from packages/page-cache-file/.gitattributes
  src/
    .gitkeep                 # placeholder; task 021 will populate
  tests/
    Pest.php
    PackageStructureTest.php
```

**composer.json shape:**
- name: `marko/page-cache-entity`
- description: short, e.g. "Marko bridge package that auto-purges page-cache tags when entities implementing IdentityInterface are saved or deleted."
- license: MIT
- type: `marko-module`
- require: PHP `^8.5`, `marko/core: self.version` (Observer attribute), `marko/page-cache: self.version` (IdentityInterface + PageCacheInterface), `marko/database: self.version` (Entity + lifecycle events)
- require-dev: `pestphp/pest: ^4.0`, `marko/testing: self.version`
- autoload PSR-4: `Marko\\PageCache\\Entity\\` → `src/`
- autoload-dev PSR-4: `Marko\\PageCache\\Entity\\Tests\\` → `tests/`
- `config.allow-plugins.pestphp/pest-plugin`: true
- `extra.marko.module`: true

**module.php shape (for now):**
```php
return [
    // observers in task 021 are discovered via #[Observer] attribute, not registered here
    // empty module.php is acceptable; include it for consistency with sibling packages
];
```

**Root composer.json updates:**
- Add `"packages/page-cache-entity"` to the `repositories` array (path repo entry, matching siblings — alphabetical position: between `page-cache-file` and `pagination`)
- Add `"marko/page-cache-entity": "self.version"` to the root `require` block (alphabetical position: between `marko/page-cache-file` and `marko/pagination`). Without this, the monorepo `composer install` does not pull the package into vendor and `module.php`/observers are not discovered at runtime — matching the pattern set by `marko/page-cache-file`.
- Add `Marko\\PageCache\\Entity\\Tests\\` → `packages/page-cache-entity/tests/` to root `autoload-dev` (alphabetical position: between `Marko\\PageCache\\File\\Tests\\` and `Marko\\Pagination\\Tests\\`)
- The package's own PSR-4 (`Marko\\PageCache\\Entity\\`) is autoloaded by its own composer.json; no root entry needed

**Package name in `bug_report.yml` / `feature_request.yml`:** Add `- page-cache-entity` to the package dropdown options in both issue templates, alphabetically between `page-cache-file` and `pagination`.

## Requirements (Test Descriptions)

- [ ] `it creates packages/page-cache-entity with a valid composer.json`
- [ ] `it declares marko/page-cache-entity as the package name`
- [ ] `it declares the marko-module type and the extra.marko.module flag`
- [ ] `it requires marko/page-cache and marko/database with self.version constraints`
- [ ] `it registers PSR-4 autoload for Marko PageCache Entity namespace pointing at src`
- [ ] `it ships a module.php returning an array`
- [ ] `it has a LICENSE file and .gitattributes`
- [ ] `it adds packages/page-cache-entity as a path repository in the root composer.json`
- [ ] `it declares marko/page-cache-entity as a self.version requirement in the root composer.json`
- [ ] `it registers the package test autoload as Marko\PageCache\Entity\Tests\\ in the root composer.json autoload-dev`
- [ ] `it lists page-cache-entity in the package dropdown of bug_report.yml and feature_request.yml`

## Acceptance Criteria

- All requirements have passing tests
- `composer dump-autoload` runs cleanly in the Docker container
- No actual source files in `src/` yet beyond the placeholder
- Follows the `marko/page-cache-file` scaffolding pattern exactly

## Implementation Notes

(Left blank - filled in by programmer during implementation)
