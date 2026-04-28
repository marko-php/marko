# Task 005: Create marko/codeindexer package skeleton

**Status**: complete
**Depends on**: none
**Retry count**: 0

## Description
Create the `marko/codeindexer` package skeleton at `packages/codeindexer/` with composer.json, module.php, directory structure, and Pest bootstrap. This is the shared static-analysis library used by both `marko/mcp` and `marko/lsp`.

## Context
- Pattern: standard Marko package (see `packages/core/`, `packages/cache/` for reference)
- Namespace: `Marko\CodeIndexer\`
- PHP 8.5 strict types, PSR-4 autoloading, Pest test config

## Requirements (Test Descriptions)
- [x] `it has composer.json with name marko/codeindexer and PSR-4 namespace Marko\\CodeIndexer\\`
- [x] `it has module.php with empty bindings and singletons arrays`
- [x] `it has src/ tests/Unit tests/Feature directories`
- [x] `it has tests/Pest.php that configures Pest with TestCase`
- [x] `it requires PHP ^8.5, marko/core, and nikic/php-parser ^5.4`
- [x] `it autoloads cleanly with composer dump-autoload`
- [x] `it defines IndexCacheInterface, ModuleWalkerInterface, AttributeParserInterface, ConfigScannerInterface, TemplateScannerInterface, TranslationScannerInterface contracts and the readonly value object types (ModuleInfo, ObserverEntry, PluginEntry, PreferenceEntry, CommandEntry, RouteEntry, ConfigKeyEntry, TemplateEntry, TranslationEntry) so parallel workers in tasks 006-011 can target stable shapes`

## Acceptance Criteria
- Skeleton present, composer autoload works
- Package registered in root composer.json repositories (path repository pattern if used)
- Empty test file exists to confirm Pest runs

## Implementation Notes
- Created `packages/codeindexer/` with standard Marko package structure
- `composer.json` uses `marko-module` type, `self.version` for marko/core, and `^5.4` for nikic/php-parser
- `module.php` returns plain array with empty `bindings` and `singletons` keys (matches actual codebase pattern, not ModuleInterface)
- All 9 value objects in `src/ValueObject/` are `readonly class` with constructor property promotion
- All 6 contracts in `src/Contract/` with full `@return list<T>` PHPDoc on array-returning methods
- Package registered in root `composer.json` repositories and `require` sections; test namespace added to `autoload-dev`
- Symlink created at `vendor/marko/codeindexer` by composer dump-autoload
