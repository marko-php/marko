# Task 006: Module Wiring, Config, and composer.json

**Status**: completed
**Depends on**: 005
**Retry count**: 0

## Description
Create the package infrastructure: `composer.json`, `module.php` with container bindings, and `AuthorizationConfig` for configuration. Wire the Gate, PolicyRegistry, and middleware into the DI container.

## Context
- Related files: `packages/auth/module.php` (pattern), `packages/auth/composer.json` (pattern), `packages/auth/src/Config/AuthConfig.php` (pattern)
- Config: `authorization.default_guard` (which auth guard to use, defaults to auth config default)
- module.php binds GateInterface to Gate, PolicyRegistry, etc.
- composer.json: requires marko/core, marko/auth, marko/routing
- Patterns to follow: config class wrapping ConfigRepositoryInterface, factory closures in module.php

## Requirements (Test Descriptions)
- [ ] `it creates valid composer.json with correct dependencies`
- [ ] `it creates module.php with Gate binding`
- [ ] `it creates module.php with PolicyRegistry binding`
- [ ] `it creates AuthorizationConfig with default guard accessor`
- [ ] `it wires Gate with AuthManager from container`
- [ ] `it includes config/authorization.php with sensible defaults`

## Acceptance Criteria
- All requirements have passing tests
- composer.json has no hardcoded version
- module.php uses factory closures for lazy initialization
- Config file provides all defaults (no hardcoded fallbacks in code)

## Implementation Notes
(Left blank - filled in by programmer during implementation)
