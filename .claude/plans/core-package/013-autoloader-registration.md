# Task 013: Autoloader Registration for Non-Vendor Modules

**Status**: completed
**Depends on**: 004, 011
**Retry count**: 0

## Description
Add PSR-4 autoloader registration for modules discovered in `modules/` and `app/` directories. Currently, only vendor modules get autoloading via Composer. Non-vendor modules need the framework to register autoloaders based on their composer.json autoload configuration.

## Context
- Related files: `packages/core/src/Module/ModuleManifest.php`, `packages/core/src/Module/ManifestParser.php`, `packages/core/src/Application.php`
- Patterns to follow: Composer's PSR-4 autoloading behavior
- Vendor modules already work because Composer handles them
- This enables app/blog to work without being in demo/composer.json require

## Requirements (Test Descriptions)
- [ ] `it extracts psr-4 autoload configuration from composer.json into ModuleManifest`
- [ ] `it stores autoload as empty array when composer.json has no autoload section`
- [ ] `it registers PSR-4 autoloaders for modules source during boot`
- [ ] `it skips autoloader registration for vendor modules`
- [ ] `it resolves class from app module without explicit require in root composer.json`
- [ ] `it resolves class from modules directory without explicit require in root composer.json`

## Acceptance Criteria
- All requirements have passing tests
- ModuleManifest includes autoload property
- ManifestParser extracts autoload.psr-4 from composer.json
- Application registers SPL autoloaders for non-vendor modules
- Demo app/blog works without being in demo/composer.json require
- Code follows strict types declaration

## Implementation Notes
(Left blank - filled in by programmer during implementation)
