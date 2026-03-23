# Devil's Advocate Review: packagist-publishing

## Critical (Must fix before building)

### C1. `minimum-stability: dev` in root will pull dev versions of third-party packages (Task 002)

Task 002 says to set `minimum-stability` to `"dev"` in the root composer.json. The root currently has `"minimum-stability": "stable"`. Changing it to `"dev"` means Composer may resolve dev versions of third-party packages (amphp/amp, guzzlehttp/guzzle, predis/predis, etc.) during `composer update`. This is unnecessary -- the `replace` section completely bypasses version resolution for replaced packages. Composer does not check stability of replaced packages.

The actual reason Symfony uses `minimum-stability: dev` is because they have `require: "self.version"` in the root for their own packages, and during dev those resolve to `dev-main`. But Marko's root doesn't `require` any `marko/*` packages -- it uses `replace` + direct autoload. So `minimum-stability: dev` is not needed and is actively harmful.

**Fix**: Remove the requirement to change `minimum-stability` to `"dev"`. Keep it as `"stable"`. The `replace` + `self.version` pattern works without it. If this assumption turns out wrong during integration verification (task 009), the worker can add it then -- but `prefer-stable: true` should also be explicitly noted as a safety net.

### C2. `composer validate` will fail for sub-packages using `self.version` in require (Task 001, Task 009)

When you run `composer validate` on an individual package's `composer.json` that has `"marko/core": "self.version"`, Composer will report an error because `self.version` is not a valid version constraint string -- it's a special token that only resolves in the context of a tagged release or a root `replace`. Running `composer validate` on each sub-package in isolation (as Task 009 requires) will fail.

Task 009's implementation notes show:
```bash
for dir in packages/*/; do
  (cd "$dir" && composer validate --no-check-all --no-check-publish)
done
```

This will fail for every package with `self.version` constraints.

**Fix**: Task 009 must validate sub-packages differently. Either: (a) validate from the monorepo root only (where `replace` provides context), or (b) use `composer validate --no-check-all --no-check-publish` which may still reject `self.version`, or (c) use a grep-based structural validation instead of `composer validate` for sub-packages. Task 001's acceptance criteria saying "`composer validate` passes for every package composer.json" also needs updating.

### C3. The `framework` metapackage has no `autoload` section -- but `replace` + path repos pattern may conflict (Task 002)

The `framework` package is `"type": "metapackage"`. Metapackages cannot have `autoload`, `require-dev`, or `repositories` sections. Task 001 correctly removes `repositories` from it, but Task 002 adds a path repo for `framework` in the root. Since the root uses `replace` for all packages, the path repo for `framework` is unnecessary and may cause issues because Composer might try to symlink a metapackage directory.

The root `replace` already tells Composer "I provide marko/framework" -- the path repo is redundant for the `replace` mechanism. Path repos are only needed if the root `require`s the package, which it doesn't.

**Fix**: Actually, the path repos in Task 002 are needed so that `composer install` can read each package's metadata (dependencies, autoload). But for a metapackage, there's nothing to install. Task 002 should note that `framework` may need special handling, or testing should verify it works. Add a note to Task 002 about the metapackage.

### C4. Task 001 count is wrong -- plan says 179 `@dev` constraints but actual count is higher (Task 001)

The plan says "179 internal dependencies use `@dev`" but grepping shows 226 total occurrences of `@dev` or `*` across 67 files. The discrepancy suggests the plan may have miscounted, or there are `*` constraints beyond the ones explicitly called out (dev-server and core). Task 001 needs to catch ALL of them, not just the ones enumerated.

**Fix**: Task 001's requirements already say "all internal marko/* require constraints" and "any remaining wildcard marko/* constraints" which covers the full set. Update the context description to remove the specific "179" count and say "all" instead, to avoid a worker thinking they're done at 179.

## Important (Should fix before building)

### I1. Task 002 adds 70 path repos to root composer.json -- but root doesn't `require` any marko packages (Task 002)

The root `composer.json` does not have any `marko/*` in its `require` or `require-dev` sections. It only has third-party deps. The `replace` section tells Composer "I provide these packages" -- it doesn't need path repos to do that. Path repos are only needed when Composer needs to resolve a `require`d package.

Symfony's monorepo root DOES `require` its own packages (e.g., `"symfony/console": "self.version"` in root require). The Marko root does NOT -- it just has direct PSR-4 autoload entries. So adding 70 path repos may be unnecessary clutter and could slow down `composer update`.

**Fix**: Verify whether path repos are actually needed. If the root has no `require` for `marko/*` packages, the `replace` section alone is sufficient. If path repos are needed for `composer validate` to understand the replace targets, note that explicitly. Consider whether the root should `require` marko packages instead of (or in addition to) direct autoloading -- but that's a bigger architectural decision beyond this plan's scope.

### I2. The release script uses `composer test` but the actual test command needs the specific PHP binary (Task 005)

Task 005's implementation notes show `composer test` for running tests. The `composer.json` scripts section has `"test": "pest -c phpunit.xml --parallel"`. But the MEMORY.md says all pest commands need the PHP 8.5 binary prefix: `/opt/homebrew/Cellar/php/8.5.1_2/bin/php`. If the system PHP is not 8.5, `composer test` will use the wrong PHP.

The task notes mention using the project's PHP binary path but the actual script code uses bare `composer test`.

**Fix**: The release script should either: (a) use the full path `PHP_BIN=/opt/homebrew/Cellar/php/8.5.1_2/bin/php` and `$PHP_BIN vendor/bin/pest --parallel`, or (b) document that the system PHP must be 8.5+, or (c) detect the PHP version and abort with a helpful message if wrong. Option (c) is most robust. Add a PHP version check to the release script requirements.

### I3. `add-package.sh` step 4 says "Add to root composer.json replace section" -- but it should also add autoload entries (Task 004)

Task 004's `add-package.sh` includes step 4: "Add to root composer.json replace section" and step 5: "Print reminder to add PSR-4 autoload entries if not present." But modifying the root `composer.json` replace section programmatically from a bash script is fragile (JSON manipulation in bash). And forgetting the autoload entry will cause the package to not work in the monorepo.

**Fix**: Either (a) use `jq` or `php -r` for JSON manipulation and make autoload addition automatic too, or (b) make both the replace and autoload additions manual with clear instructions. Task 004 should specify which tool to use for JSON manipulation and note `jq` as a prerequisite, or use a PHP script instead.

### I4. No `.gitattributes` or split repo initialization mentioned (Task 003, Task 004)

When splitsh-lite splits a package, the split repo gets the package's subdirectory as its root. But there's no mention of:
- `.gitattributes` in each package directory (to exclude tests/docs from Composer installs)
- Initial empty commits or branch creation in split repos (splitsh needs existing repos to push to)

The `create-split-repos.sh` script creates repos but they'll be empty. splitsh-lite can push to empty repos, but the first push needs to handle the case where the remote has no branches yet.

**Fix**: Add a note to Task 003 that the first split push to an empty repo needs `--force` or the repo needs an initial commit. Add consideration for `.gitattributes` in packages to Task 001 or as a separate concern.

### I5. Task 009 depends on 001, 002, 006 but should also sanity-check that `composer update` regenerates the lock file correctly (Task 009)

After changing `minimum-stability` (if that stays) and adding `replace` + path repos, the existing `composer.lock` will be stale. Task 009 says "runs composer update from root successfully" but doesn't mention deleting the existing lock file first or handling conflicts.

**Fix**: Task 009 should explicitly include removing `composer.lock` and `vendor/` before running `composer install`/`update` to ensure a clean verification.

### I6. Packagist API authentication method not specified correctly (Task 004)

The `register-packagist.sh` script mentions using the Packagist API with `PACKAGIST_TOKEN` but doesn't specify whether this is an API token or an OAuth token, and what header format to use. The Packagist API uses `?apiToken=XXX` query parameter, not a header-based auth. Getting this wrong means the registration script won't work.

**Fix**: Task 004 should specify the exact Packagist API authentication format: `?apiToken=XXX` query parameter, and the correct endpoint URL and request format.

## Minor (Nice to address)

### M1. 45 packages use `type: "marko-module"` -- Packagist will show this as an unknown type

This is not a blocker (Composer allows custom types) but Packagist will categorize these as "library" equivalent. If there's a custom Composer installer plugin expected for `marko-module` type, it needs to be documented.

### M2. The split workflow could be slow with 70 sequential splits

Task 003 mentions "parallel splits where possible" in acceptance criteria but the matrix strategy has a default concurrency limit of 256 on GitHub Actions, so this should be fine. However, 70 matrix jobs will consume significant GitHub Actions minutes.

### M3. No mention of GitHub Actions concurrency groups

The split workflow should use concurrency groups to prevent multiple split operations from running simultaneously (e.g., if two tags are pushed in quick succession). Without this, concurrent splits could cause push conflicts.

### M4. Demo `composer.json` has incomplete path repos

The `demo/composer.json` has path repos for only 4 packages (core, env, routing, blog) but these packages have transitive dependencies on other marko packages (blog requires database, view, cache, mail, config, session). If someone runs `composer install` in the demo directory after task 001 changes blog's constraints from `@dev` to `self.version`, Composer won't be able to resolve those transitive deps because there are no path repos for them and they're not on Packagist yet.

However, the plan says demo path repos should stay as-is, and this is an existing issue unrelated to the plan.

## Questions for the Team

### Q1. Should the root `composer.json` start `require`ing marko packages instead of direct PSR-4 autoloading?

The current pattern (direct PSR-4 in root, no `require` for own packages) is unusual for monorepos. Symfony's root `require`s its own packages. The direct autoload approach means the root doesn't validate that package dependency graphs are correct during `composer install`. Should this be addressed as part of this plan, or is it intentional?

### Q2. Is `marko-php` the confirmed GitHub organization name?

The plan uses `marko-php` as a default throughout. If it's not yet created, the user should confirm the name before scripts are built, since it appears in the split workflow and all management scripts.

### Q3. Should packages have `.gitattributes` to exclude tests from Composer installs?

Standard practice for published packages is to include a `.gitattributes` that excludes `/tests`, `phpunit.xml`, etc. from the Composer archive. This isn't mentioned in the plan and would affect every package directory.
