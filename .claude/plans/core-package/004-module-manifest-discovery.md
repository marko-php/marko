# Task 004: Module Manifest and Discovery

**Status**: completed
**Depends on**: 002
**Retry count**: 0

## Description
Create the module manifest parser and discovery system. The discovery system scans configured directories for modules (identified by composer.json) and parses their configuration.

## Context
- Location: `packages/core/src/Module/`
- **composer.json required** - Provides name, version, require (enforces good PHP practices)
- **module.php optional** - Provides Marko-specific config: enabled, sequence, bindings
- Discovery depths: vendor/*/ (2 levels), modules/**/ (recursive), app/*/ (1 level)

## Requirements (Test Descriptions)
- [x] `it parses a module with composer.json into ModuleManifest object`
- [x] `it extracts module name from composer.json`
- [x] `it extracts module version from composer.json`
- [x] `it extracts enabled state from module.php defaulting to true`
- [x] `it extracts require dependencies from composer.json`
- [x] `it extracts sequence hints (after/before) from module.php`
- [x] `it extracts bindings from module.php`
- [x] `it throws ModuleException when composer.json is missing`
- [x] `it throws ModuleException when composer.json is invalid JSON`
- [x] `it throws ModuleException when composer.json missing required name field`
- [x] `it throws ModuleException when module.php has syntax errors`
- [x] `it discovers modules in vendor directory two levels deep`
- [x] `it discovers modules in modules directory recursively`
- [x] `it discovers modules in app directory one level deep`
- [x] `it skips directories without composer.json file`
- [x] `it returns discovered modules with their source directory (vendor/modules/app)`
- [x] `it filters out php and extension requirements from dependencies`

## Acceptance Criteria
- All requirements have passing tests
- ModuleManifest is immutable (readonly properties)
- Discovery is deterministic (same order given same filesystem)
- Code follows strict types declaration

## Files to Create
```
packages/core/src/Module/
  ModuleManifest.php        # Value object for parsed manifest
  ManifestParser.php        # Parses module.php files
  ModuleDiscovery.php       # Scans directories for modules
```

## Implementation Notes

### Architectural Decision: composer.json as Source of Truth
Per framework philosophy of "opinionated, not restrictive", all modules require composer.json for:
- **name**: Package identifier (vendor/package format)
- **version**: Semantic versioning
- **require**: Hard dependencies on other packages

This enforces good PHP practices even for non-distributable modules.

### module.php for Marko-Specific Config
module.php is optional and contains only Marko framework configuration:
- **enabled**: Can disable a module without removing it (default: true)
- **sequence**: Soft ordering hints (after/before arrays)
- **bindings**: Interface → implementation mappings for DI

### Dependency Filtering
The parser filters out non-module dependencies from composer require:
- `php` version constraints
- `ext-*` PHP extensions
- `lib-*` library versions

This leaves only actual package dependencies for module resolution.

### Files Created
- `packages/core/src/Module/ModuleManifest.php` - Readonly value object
- `packages/core/src/Module/ManifestParser.php` - Parses composer.json + module.php
- `packages/core/src/Module/ModuleDiscovery.php` - Directory scanning

### Test Coverage
- 17 tests covering all requirements
