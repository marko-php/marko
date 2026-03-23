# Task 009: Create Release Process Documentation

**Status**: complete
**Depends on**: 001, 002, 003, 004, 005, 006
**Retry count**: 0

## Description
Create comprehensive documentation for the release process so it's never ambiguous. This is the single source of truth for how releases work, covering the entire flow from development to Packagist.

## Context
- Location: `.claude/release-process.md`
- Must document the complete automated pipeline
- Audience: solo maintainer (Mark) who wants a "never think about it" process
- Should cover initial setup, regular releases, and adding new packages
- GitHub org: `marko-php` (under Devtomic LLC)
- Monorepo: `markshust/marko`

## Requirements (Test Descriptions)
- [x] `it creates .claude/release-process.md with complete release workflow`
- [x] `it documents the initial one-time setup process (org creation, repo creation, Packagist registration)`
- [x] `it documents the regular release process (merge to main, run release script)`
- [x] `it documents how to add a new package to the ecosystem`
- [x] `it documents the branch strategy (develop, main, tags)`
- [x] `it documents versioning rules (unified semver, self.version, 0.x means unstable)`
- [x] `it documents troubleshooting for common issues`

## Acceptance Criteria
- Document covers the complete lifecycle from development to Packagist
- Steps are numbered and unambiguous
- Includes the exact commands to run (no "figure it out" steps)
- Documents what happens automatically vs what requires manual action
- Includes troubleshooting section

## Implementation Notes
Structure:
1. **Overview** — How the publishing pipeline works (diagram)
2. **Initial Setup** (one-time) — Create org, run create-split-repos.sh, register on Packagist
3. **Development Workflow** — Branch strategy, PR process
4. **Cutting a Release** — Step-by-step with exact commands
5. **Adding a New Package** — Step-by-step with exact commands
6. **How It Works** — Technical details of splitsh, Packagist webhooks
7. **Troubleshooting** — Common issues and fixes
