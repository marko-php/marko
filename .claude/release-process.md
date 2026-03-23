# Release Process

This is the single source of truth for publishing Marko packages. Follow these steps exactly — nothing is ambiguous.

## How It Works

Marko is a monorepo at `marko-php/marko`. Each package under `packages/` is automatically split into its own read-only repository (`marko-php/marko-{name}`) using [splitsh-lite](https://github.com/splitsh/lite) via GitHub Actions. Packagist monitors those split repos via webhooks and updates package listings automatically.

```
monorepo (marko-php/marko)
  └── packages/core/
  └── packages/router/
  └── packages/...70 total
        |
        | GitHub Actions: split.yml (on tag + branch push)
        v
  marko-php/marko-core      ← Packagist: marko/core
  marko-php/marko-router    ← Packagist: marko/router
  marko-php/marko-...
```

**What is automatic:**
- Tag push → split workflow runs → all 70 packages tagged in their split repos → Packagist updates
- Branch push (`main` or `develop`) → split workflow updates branches in split repos (enables `dev-main`, `dev-develop`)
- Packagist webhooks → instant updates (no manual Packagist interaction ever needed)

**What is manual:**
- Merging `develop` → `main`
- Running `./bin/release.sh <version>`
- Initial setup (one-time only)

---

## Initial Setup (One-Time)

These steps have already been completed for the `marko-php` org. Documented here for reference and disaster recovery.

**Prerequisites:**
```bash
brew install gh jq
gh auth login
```

**Steps:**

1. Create `marko-php` org on GitHub under Devtomic LLC account — **already done**

2. Transfer monorepo to `marko-php/marko` — **already done**

3. Create a GitHub PAT with `repo` scope on the `marko-php` org, then add it as a repository secret:
   - Go to: https://github.com/marko-php/marko/settings/secrets/actions
   - Secret name: `SPLIT_TOKEN`
   - Value: the PAT

4. Create all 70 split repos:
   ```bash
   GITHUB_ORG=marko-php ./bin/create-split-repos.sh
   ```

5. Register all packages on Packagist:
   ```bash
   PACKAGIST_USERNAME=xxx PACKAGIST_TOKEN=xxx GITHUB_ORG=marko-php ./bin/register-packagist.sh
   ```
   Get `PACKAGIST_TOKEN` from: https://packagist.org/profile/ → API Token

6. Update local remote if needed:
   ```bash
   git remote set-url origin git@github.com:marko-php/marko.git
   ```

---

## Branch Strategy

| Branch | Purpose |
|--------|---------|
| `develop` | Active development. All work happens here. |
| `main` | Release-ready code only. Never commit directly — always merge from `develop`. |
| Tags (e.g., `v0.1.0`) | Cut from `main`. These trigger the split workflow and Packagist updates. |

**Rule:** Never commit directly to `main`. Merge from `develop`, then release.

---

## Versioning

- **Unified:** All 70 packages always share the same version number
- **Format:** semver `MAJOR.MINOR.PATCH`
- **`0.x.x`:** Under active development — API may change between minor versions
- **`1.0.0`:** First stable release — full semantic versioning guarantees apply from here
- **No version fields in `composer.json`** — Composer infers the version from git tags
- **Internal deps use `self.version`** — resolves to the exact tag version at publish time

Version examples:
- `0.1.0` → initial release
- `0.2.0` → new features (may break API while in `0.x`)
- `0.2.1` → bug fix
- `1.0.0` → stable, semver guarantees begin

---

## Cutting a Release

Run these steps in order. No shortcuts.

```bash
# 1. Make sure develop is up to date
git checkout develop
git pull

# 2. Merge to main
git checkout main
git merge develop

# 3. Push main (triggers split workflow for dev-main branch, not a release yet)
git push origin main

# 4. Run the release script — validates, runs tests, tags, and pushes
./bin/release.sh 0.2.0
```

The release script (`bin/release.sh`) does the following:
- Validates semver format
- Confirms you are on `main` branch
- Confirms working directory is clean
- Confirms tag does not already exist
- Runs the full test suite (must pass)
- Creates annotated tag `v0.2.0`
- Pushes tag to `origin`

After pushing the tag, everything else is automatic:
- GitHub Actions split workflow runs: https://github.com/marko-php/marko/actions
- All 70 packages are tagged in their split repos
- Packagist updates within seconds via webhooks
- Verify: https://packagist.org/packages/marko/

**If tests fail:** Fix the failing tests and re-run `./bin/release.sh`. Do not skip tests.

---

## Adding a New Package

When a new package is added under `packages/`:

1. Create the package directory and `composer.json` with proper structure:
   ```json
   {
     "name": "marko/new-feature",
     "description": "...",
     "type": "library",
     "license": "MIT",
     "require": {
       "php": "^8.5"
     },
     "autoload": {
       "psr-4": {
         "Marko\\NewFeature\\": "src/"
       }
     }
   }
   ```

2. Run the add-package script (creates split repo + registers on Packagist + updates root `composer.json`):
   ```bash
   GITHUB_ORG=marko-php PACKAGIST_USERNAME=xxx PACKAGIST_TOKEN=xxx ./bin/add-package.sh new-feature
   ```

3. Commit and push to `develop`:
   ```bash
   git add .
   git commit -m "feat: add marko/new-feature package"
   git push origin develop
   ```

4. The split workflow automatically pushes the package code to `marko-php/marko-new-feature`

5. Packagist auto-updates via webhook — the package becomes available as `dev-develop`

6. On next release (`./bin/release.sh X.Y.Z`), the package gets a proper version tag

---

## Required GitHub Secrets

| Secret | Where | Description |
|--------|-------|-------------|
| `SPLIT_TOKEN` | `marko-php/marko` repo secrets | GitHub PAT with `repo` scope on `marko-php` org. Used by split workflow to push to split repos. |

Runtime env vars for bin scripts (not stored as secrets — passed at the command line):

| Variable | Used By | Where to Get |
|----------|---------|--------------|
| `PACKAGIST_USERNAME` | `register-packagist.sh`, `add-package.sh` | Your Packagist username |
| `PACKAGIST_TOKEN` | `register-packagist.sh`, `add-package.sh` | https://packagist.org/profile/ → API Token |
| `GITHUB_ORG` | `create-split-repos.sh`, `register-packagist.sh`, `add-package.sh` | Default: `marko-php` |

---

## Troubleshooting

**Split workflow fails on tag/branch push**
- Check `SPLIT_TOKEN` secret exists and has `repo` scope on `marko-php` org
- Go to: https://github.com/marko-php/marko/settings/secrets/actions
- Regenerate PAT at: https://github.com/settings/tokens if needed

**Split repo does not exist for a package**
- Run: `GITHUB_ORG=marko-php ./bin/create-split-repos.sh`
- Script is idempotent — safe to re-run, skips repos that already exist

**Packagist not updating after a release**
- Verify the webhook is configured on the split repo: `https://github.com/marko-php/marko-{name}/settings/hooks`
- Packagist webhook URL: `https://packagist.org/api/github?username=xxx`
- Re-register the package: `PACKAGIST_USERNAME=xxx PACKAGIST_TOKEN=xxx GITHUB_ORG=marko-php ./bin/register-packagist.sh`

**`./bin/release.sh` says "not on main branch"**
- Run: `git checkout main && git merge develop && git push origin main`
- Then re-run: `./bin/release.sh X.Y.Z`

**`./bin/release.sh` says "tag already exists"**
- You already released that version. Increment the version number.
- To list existing tags: `git tag --list | sort -V`

**Tests fail before release**
- Fix the failing tests. They must pass before tagging.
- Run locally: `/opt/homebrew/Cellar/php/8.5.1_2/bin/php vendor/bin/pest --parallel`

**`composer update` fails locally**
- Always run from the repo root (not inside a package directory)
- Run: `cd /path/to/marko && composer update`
