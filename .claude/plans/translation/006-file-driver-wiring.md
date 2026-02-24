# Task 006: module.php, composer.json for File Driver Package

**Status**: done
**Depends on**: 004
**Retry count**: 0

## Description
Create the package scaffolding for `marko/translation-file` -- the file-based translation driver. This includes `composer.json` with correct dependencies and PSR-4 autoloading, and `module.php` with the binding of TranslationLoaderInterface to FileTranslationLoader. The module.php must configure the FileTranslationLoader with the correct base path from the application root.

## Context
- Package: `marko/translation-file`
- Namespace: `Marko\Translation\File\`
- Location: `packages/translation-file/`
- Dependencies: marko/core, marko/config, marko/translation
- module.php binds TranslationLoaderInterface -> FileTranslationLoader
- FileTranslationLoader needs a base path (the application root where `lang/` lives)
- Reference: `packages/cache-file/composer.json`, `packages/cache-file/module.php`
- Pattern: Same as other driver packages (cache-file, encryption-openssl, session-file)

## Requirements (Test Descriptions)
- [ ] `it has valid composer.json with marko module flag and correct dependencies`
- [ ] `it requires marko/translation as a dependency`
- [ ] `it has PSR-4 autoload for Marko\Translation\File namespace`
- [ ] `it has module.php that binds TranslationLoaderInterface to FileTranslationLoader`
- [ ] `it returns valid module configuration array`

## Acceptance Criteria
- `composer.json` has name `marko/translation-file`, type `marko-module`
- `composer.json` requires `php: ^8.5`, `marko/core`, `marko/config`, `marko/translation`
- `composer.json` has PSR-4 autoload for `Marko\Translation\File\` -> `src/`
- `composer.json` has PSR-4 autoload-dev for `Marko\Translation\File\Tests\` -> `tests/`
- `composer.json` has `extra.marko.module: true`
- No hardcoded version in `composer.json`
- `module.php` returns array with bindings mapping TranslationLoaderInterface to FileTranslationLoader
- `module.php` binding creates FileTranslationLoader with the application root path
- Tests verify composer.json structure and module.php return value
- Uses `declare(strict_types=1)`

## Implementation Notes
(Left blank)
