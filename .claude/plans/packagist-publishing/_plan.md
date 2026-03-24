# Plan: Packagist Publishing Infrastructure

## Created
2026-03-23

## Status
completed

## Objective
Set up complete Packagist publishing infrastructure for the Marko monorepo — including composer.json cleanup, splitsh-lite GitHub Actions, release scripting, repo management automation, and documentation — so that all 70 packages are installable via `composer require marko/*` with zero manual steps after initial setup.

## Scope

### In Scope
- Remove path repositories from all 38 package composer.json files
- Change all internal `@dev` / `*` constraints to `self.version`
- Restructure root composer.json: add `replace`, `require` all packages, remove manual PSR-4 (let Composer build autoload from each package's own composer.json — Symfony/Laravel pattern)
- Add `.gitattributes` to all 70 packages for lean Composer archives
- Add `LICENSE` (MIT, Copyright Devtomic LLC) to all 70 packages and root
- Create GitHub Actions workflow using splitsh-lite for subtree splitting
- Create repo management scripts (bulk create repos, register on Packagist, add new package)
- Create release script with validation
- Remove `demo/` directory entirely (no longer needed)
- Update project-overview.md with correct org/repo info
- Create release process documentation
- Verify local development still works after all changes

### Out of Scope
- Modifying any package source code (src/)
- Writing new tests for package functionality
- Creating the GitHub organization (manual step — user creates `marko-php` org under Devtomic LLC)
- Rotating compromised secrets (already done)
- Packagist account setup (already done)
- Actually running the first release (separate step after plan completes)

## Success Criteria
- [ ] No package composer.json contains `repositories` (path repos)
- [ ] All internal marko/* dependencies use `self.version`
- [ ] Root composer.json `require`s all 70 packages with `self.version` and has `replace` for all 70
- [ ] Root composer.json has NO manual PSR-4 autoload for packages (Composer builds this from each package's own composer.json)
- [ ] `composer validate` passes for root; sub-packages pass structural validation (composer validate rejects `self.version` in isolation — this is expected)
- [ ] `composer install` works from monorepo root (local dev)
- [ ] Every package has a `.gitattributes` excluding tests/docs/config from archives
- [ ] Every package has a `LICENSE` file (MIT, Copyright Devtomic LLC)
- [ ] GitHub Actions split workflow exists and is syntactically valid
- [ ] Release script validates version format, branch, and test status
- [ ] Repo management scripts exist for bulk creation and new packages
- [ ] Release process fully documented
- [ ] `demo/` directory is removed
- [ ] All existing tests still pass

## Task Overview
| Task | Description | Depends On | Status |
|------|-------------|------------|--------|
| 001 | Clean all package composer.json files | - | completed |
| 002 | Restructure root composer.json (require + replace + remove manual PSR-4) | - | completed |
| 003 | Add .gitattributes and LICENSE to all packages | - | completed |
| 004 | Create GitHub Actions split workflow | - | completed |
| 005 | Create repo management scripts | - | completed |
| 006 | Create release script | - | completed |
| 007 | Remove demo directory and clean up references | - | completed |
| 008 | Update project-overview.md | - | completed |
| 009 | Create release process documentation | 001, 002, 003, 004, 005, 006 | completed |
| 010 | Integration verification | 001, 002, 003, 007 | completed |

## Architecture Notes

### Versioning Strategy
- **Unified versioning**: All 70 packages share the same version number
- **Starting version**: `0.1.0` (signals active development, API may change)
- **Constraint**: `self.version` — resolves to the version from the git tag
- **Local dev**: Root `replace` section tells Composer "I provide all these packages at this version" — `self.version` never needs to resolve during development

### Root Autoloading Strategy (Symfony/Laravel Pattern)
- Root `composer.json` lists all 70 packages in `require` with `self.version`
- Root `composer.json` lists all 70 packages in `replace` with `self.version`
- Root `composer.json` has path repositories for all 70 packages
- **No manual PSR-4 autoload entries** — Composer reads each package's own `composer.json` for autoload
- This ensures each package's `composer.json` is exercised during development, catching missing deps/typos before users do

### Splitting Strategy
- **Tool**: splitsh-lite (same as Symfony)
- **Trigger**: Tag push (`v*`) splits + tags all packages; branch push splits branches
- **Split repos**: `marko-php/marko-{package}` (e.g., `marko-php/marko-core`)
- **Branches split**: `main`, `develop`
- **Packagist**: Watches split repos via webhook, auto-updates on push

### Branch Strategy
- `develop` — active development, PRs target here
- `main` — release-ready, merges from develop
- Tags cut from `main` (e.g., `0.1.0`)

### GitHub Organization
- `marko-php` org under Devtomic LLC — $0 for public repos
- User creates org manually, scripts handle everything else
- Monorepo transferred to `marko-php/marko` (GitHub auto-redirects old `markshust/marko` URL)

## Risks & Mitigations
- **`self.version` + local dev**: Mitigated by root `replace` section — Composer never resolves self.version during monorepo development. Do NOT change `minimum-stability` to `"dev"` — `replace` bypasses stability checks, and `"dev"` would risk pulling dev versions of third-party packages
- **`composer validate` on sub-packages**: Will fail because `self.version` is not a standard version constraint — validate structurally (valid JSON, required keys) instead of with `composer validate` for individual packages. Root `composer validate` still works.
- **Autoload migration**: Removing manual PSR-4 from root and relying on package-level autoload may surface missing/incorrect autoload declarations in individual packages — Task 010 (integration verification) catches this
- **70 split repos to manage**: Mitigated by automation scripts for bulk creation and new package workflow
- **Packagist webhook setup**: Mitigated by registration script using Packagist API
- **splitsh-lite availability**: It's a well-maintained tool used by Symfony; available as a GitHub Action
