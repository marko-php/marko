# Plan: Release Workflow & Conventions

## Created
2026-04-01

## Status
completed

## Objective
Establish a fully automated release workflow with auto-labeling, generated release notes, issue/PR templates, and documented contribution conventions so that `bin/release.sh` is the only manual step for a release.

## Scope

### In Scope
- Auto-labeling workflow for PRs based on conventional commit title prefix
- GitHub release notes configuration (`.github/release.yml`)
- Update `bin/release.sh` to create GitHub Releases with generated notes
- Issue templates (bug report, feature request) in YAML form format
- PR template
- CONTRIBUTING.md with branch naming, commit format, and workflow
- One-time label setup script for new labels
- Label creation script (`bin/setup-labels.sh`)

### Out of Scope
- PR title enforcement/validation (not needed — convention is documented)
- Package-specific labels from commit scopes (too fragile with 70+ packages)
- Per-package changelogs (single unified version)
- Dependabot configuration
- Branch protection rules

## Success Criteria
- [ ] PRs auto-labeled from title prefix without manual intervention
- [ ] `bin/release.sh 0.2.0` creates tag, pushes, and creates GitHub Release with categorized notes
- [ ] Issue templates render correctly on GitHub
- [ ] PR template appears when opening new PRs
- [ ] CONTRIBUTING.md documents the complete workflow
- [ ] All 5 new labels exist on the repo
- [ ] All tests passing
- [ ] Code follows project standards

## Task Overview
| Task | Description | Depends On | Status |
|------|-------------|------------|--------|
| 001 | Label setup script | - | completed |
| 002 | Release notes configuration | - | completed |
| 003 | Auto-labeling workflow | 001 | completed |
| 004 | Update release script | 002 | completed |
| 005 | Issue templates | - | completed |
| 006 | PR template | - | completed |
| 007 | CONTRIBUTING.md | 003, 004, 005, 006 | completed |

## Architecture Notes
- All GitHub config files go in `.github/`
- Workflows use standard GitHub Actions conventions
- Release notes categories map labels to sections
- Auto-labeling uses a lightweight shell step (no third-party actions) with `pull_request_target` event for fork PR write access
- Scope in conventional commit prefix (e.g., `feat(auth):`) is ignored for labeling — only the type matters

## Risks & Mitigations
- Packagist webhook failure on release: mitigated by existing Notify Packagist step in split.yml
- Auto-label regex mismatch: keep pattern simple, test against known PR titles
- GitHub Release creation failure in release script: wrap in an explicit `if` block (not just `|| true`) because `set -euo pipefail` is active
- First release with no previous tag: `--notes-start-tag` must be conditionally omitted
