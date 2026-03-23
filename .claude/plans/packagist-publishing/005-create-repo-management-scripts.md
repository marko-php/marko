# Task 005: Create Repo Management Scripts

**Status**: complete
**Depends on**: none
**Retry count**: 0

## Description
Create automation scripts for managing the 70+ split repositories and Packagist registrations. These scripts handle the one-time bulk creation of all repos, registering packages on Packagist, and the ongoing process of adding new packages to the ecosystem.

## Context
- Scripts location: `bin/` directory (create if needed)
- Uses `gh` CLI for GitHub operations (user has it installed)
- Uses Packagist API for package registration
- Split repos follow naming: `marko-php/marko-{package-name}`
- The org name should be configurable (default: `marko-php`), owned by Devtomic LLC
- Must handle the full lifecycle: create repo, register on Packagist, set up webhook

## Requirements (Test Descriptions)
- [x] `it creates bin/create-split-repos.sh that bulk-creates all 70 split repos on GitHub`
- [x] `it creates bin/register-packagist.sh that registers all 70 packages on Packagist`
- [x] `it creates bin/add-package.sh that handles the full new-package workflow`
- [x] `it makes all scripts executable`
- [x] `it validates required tools and credentials before running (gh, curl, jq, API tokens)`
- [x] `it skips repos that already exist without erroring`
- [x] `it dynamically reads package list from packages/ directory`

## Acceptance Criteria
- `bin/create-split-repos.sh` creates all split repos on the configured GitHub org
- `bin/register-packagist.sh` registers all packages on Packagist with proper webhook
- `bin/add-package.sh` handles: create split repo, register on Packagist, update split workflow
- All scripts are idempotent (safe to run multiple times)
- All scripts validate prerequisites before starting
- Scripts use the org name from a single configurable variable

## Implementation Notes

### bin/create-split-repos.sh
```bash
#!/usr/bin/env bash
# Creates read-only split repositories for all packages
# Usage: GITHUB_ORG=marko-php ./bin/create-split-repos.sh

# For each package in packages/:
#   gh repo create {org}/marko-{package} --public --description "..."
```

- Set each repo description to the package's composer.json description
- Mark repos as read-only in the description (e.g., "[READ-ONLY] Subtree split of marko/{package}")
- Disable issues/wiki on split repos (issues go to the monorepo)

### bin/register-packagist.sh
```bash
#!/usr/bin/env bash
# Registers all split repos on Packagist
# Usage: PACKAGIST_TOKEN=xxx PACKAGIST_USERNAME=xxx GITHUB_ORG=marko-php ./bin/register-packagist.sh

# Packagist API: POST https://packagist.org/api/create-package?username=XXX&apiToken=XXX
# Body: {"repository":{"url":"https://github.com/{org}/marko-{package}"}}
# Auth: query parameters (username + apiToken), NOT header-based
# Content-Type: application/json
```

### bin/add-package.sh
```bash
#!/usr/bin/env bash
# Full workflow for adding a new package
# Usage: GITHUB_ORG=marko-php PACKAGIST_TOKEN=xxx ./bin/add-package.sh {package-name}

# Steps:
# 1. Verify packages/{name}/composer.json exists
# 2. Create split repo on GitHub
# 3. Register on Packagist
# 4. Add to root composer.json replace section (use jq for safe JSON manipulation)
# 5. Add path repo entry to root composer.json repositories (use jq)
# 6. Print reminder to add PSR-4 autoload entries if not present
#
# Requires: jq (for safe JSON manipulation of composer.json)
```
