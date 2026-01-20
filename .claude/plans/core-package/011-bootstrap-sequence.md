# Task 011: Bootstrap Sequence

**Status**: pending
**Depends on**: 006, 007, 009, 010
**Retry count**: 0

## Description
Create the Application class and bootstrap.php that ties everything together. The bootstrap executes the full sequence: scan modules, parse manifests, validate dependencies, sort, boot modules, and prepare the container.

## Context
- Location: `packages/core/src/` and `packages/core/bootstrap.php`
- bootstrap.php is the single entry point hardcoded in applications
- Application class coordinates all subsystems
- After bootstrap, container is ready to handle requests

## Requirements (Test Descriptions)
- [ ] `it creates Application class as main entry point`
- [ ] `it accepts base paths for vendor, modules, and app directories`
- [ ] `it scans all three directories for modules during boot`
- [ ] `it parses all discovered module manifests`
- [ ] `it validates module dependencies exist`
- [ ] `it detects and reports circular dependencies`
- [ ] `it sorts modules in correct load order`
- [ ] `it registers bindings from all modules`
- [ ] `it discovers and registers preferences`
- [ ] `it discovers and registers plugins`
- [ ] `it discovers and registers observers`
- [ ] `it provides access to configured container`
- [ ] `it provides access to event dispatcher`
- [ ] `bootstrap.php creates and boots Application instance`

## Acceptance Criteria
- All requirements have passing tests
- Bootstrap fails fast with clear errors
- Application is the single coordination point
- Code follows strict types declaration

## Files to Create
```
packages/core/src/
  Application.php           # Main application class
packages/core/
  bootstrap.php             # Entry point script
```

## Implementation Notes
(Left blank - filled in by programmer during implementation)
