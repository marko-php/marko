# Plan: Config No Defaults

## Created
2026-01-29

## Status
in_progress

## Objective
Remove default parameters from ConfigRepositoryInterface to enforce config files as the single source of truth. Missing config keys throw ConfigNotFoundException instead of silently falling back.

## Scope

### In Scope
- Remove default parameters from `get()`, `getString()`, `getInt()`, `getBool()`, `getFloat()`, `getArray()`
- Update ConfigRepository implementation
- Update all package Config classes to remove fallback parameters
- Ensure all package config files have complete defaults
- Update tests for new behavior
- Document scoped config cascade as intentional exception in architecture
- Update config package README

### Out of Scope
- Changing scoped config cascade behavior (intentional design)
- Environment variable handling (stays in config files only)
- Adding new config values

## Success Criteria
- [ ] ConfigRepositoryInterface has no default parameters on getters
- [ ] All package config files define all values used by their Config classes
- [ ] All Config classes call getters without fallback parameters
- [ ] Missing config throws ConfigNotFoundException
- [ ] Scoped config cascade documented as intentional exception
- [ ] All tests passing
- [ ] Code follows project standards

## Task Overview
| Task | Description | Depends On | Status |
|------|-------------|------------|--------|
| 001 | Update ConfigRepositoryInterface | - | pending |
| 002 | Update ConfigRepository implementation | 001 | pending |
| 003 | Update blog package config | 002 | pending |
| 004 | Update session package config | 002 | pending |
| 005 | Update hashing package config | 002 | pending |
| 006 | Update cache package config | 002 | pending |
| 007 | Update auth package config | 002 | pending |
| 008 | Update mail package config | 002 | pending |
| 009 | Update log package config | 002 | pending |
| 010 | Update filesystem package config | 002 | pending |
| 011 | Update documentation | 002 | pending |

## Architecture Notes
- Scoped config cascade (scopes.{tenant} → default → direct) is an **intentional exception** to allow multi-tenant flexibility
- Environment variables are ONLY referenced in config.php files, never in application code
- Config files are the single source of truth - code reads from config, not env

## Risks & Mitigations
- **Breaking change for existing code**: Pre-release, acceptable
- **Missing config values cause runtime errors**: Tests will catch missing values; loud errors are intentional
