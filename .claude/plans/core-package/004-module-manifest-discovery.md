# Task 004: Module Manifest and Discovery

**Status**: pending
**Depends on**: 002
**Retry count**: 0

## Description
Create the module manifest parser and discovery system. The discovery system scans configured directories for `module.php` files and parses them into ModuleManifest objects.

## Context
- Location: `packages/core/src/Module/`
- Manifests are PHP files returning arrays (no XML, no YAML)
- Discovery depths: vendor/*/ (2 levels), modules/**/ (recursive), app/*/ (1 level)
- Manifest structure defined in architecture docs

## Requirements (Test Descriptions)
- [ ] `it parses a valid module.php file into ModuleManifest object`
- [ ] `it extracts module name from manifest`
- [ ] `it extracts module version from manifest`
- [ ] `it extracts enabled state defaulting to true`
- [ ] `it extracts require dependencies as array`
- [ ] `it extracts sequence hints (after/before) as arrays`
- [ ] `it extracts bindings as associative array`
- [ ] `it throws ModuleException when manifest file is invalid PHP`
- [ ] `it throws ModuleException when manifest missing required name field`
- [ ] `it discovers modules in vendor directory two levels deep`
- [ ] `it discovers modules in modules directory recursively`
- [ ] `it discovers modules in app directory one level deep`
- [ ] `it skips directories without module.php file`
- [ ] `it returns discovered modules with their source directory (vendor/modules/app)`

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
(Left blank - filled in by programmer during implementation)
