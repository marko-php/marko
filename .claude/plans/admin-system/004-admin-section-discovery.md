# Task 004: marko/admin Discovery - AdminSectionDiscovery

**Status**: completed
**Depends on**: 002
**Retry count**: 0

## Description
Create the discovery mechanism that scans modules for classes with the `#[AdminSection]` attribute and registers them in the `AdminSectionRegistry`. This follows the exact same pattern as `PreferenceDiscovery`, `PluginDiscovery`, and `ObserverDiscovery` in core.

## Context
- Related files: `packages/core/src/Container/PreferenceDiscovery.php`, `packages/core/src/Plugin/PluginDiscovery.php`, `packages/core/src/Event/ObserverDiscovery.php`
- Discovery scans `src/` directories of all modules for files containing `#[AdminSection`
- Uses `ClassFileParser` to extract class names
- Instantiates the attribute to get section metadata
- The discovered class must implement `AdminSectionInterface`
- Registration happens during application boot (will be integrated into Application.php in a later task when all packages are ready)

## Requirements (Test Descriptions)
- [ ] `it discovers classes with AdminSection attribute in a module`
- [ ] `it skips classes without AdminSection attribute`
- [ ] `it throws AdminException when AdminSection class does not implement AdminSectionInterface`
- [ ] `it extracts section metadata from AdminSection attribute`
- [ ] `it discovers AdminPermission attributes on AdminSection classes`
- [ ] `it returns empty array when module has no admin sections`
- [ ] `it discovers sections across multiple modules`

## Acceptance Criteria
- All requirements have passing tests
- Discovery follows existing discovery patterns exactly
- Code follows code standards
