# PR Review Process

Step-by-step process for reviewing, updating, and merging external pull requests.

When reviewing a PR, walk this entire document. The first review pass should be exhaustive — items missed cost a round-trip with the contributor.

## 1. Fetch and understand the PR

```bash
# Get PR details, related issues, and diff
gh pr view <number> --json title,body,state,headRefName,baseRefName,author,commits,maintainerCanModify
gh pr diff <number>

# Check the related issue if referenced
gh issue view <issue-number> --json title,body,state
```

Read the PR description and related issue. Understand what problem is being solved and whether the issue is valid/real.

## 2. Checkout the PR locally

```bash
# Ensure develop is up to date first
git checkout develop && git pull origin develop

# Checkout the PR branch
gh pr checkout <number>
```

## 3. Rebase onto develop

External PRs are often behind `develop`. Rebase before testing:

```bash
git rebase develop
```

If there are conflicts, resolve them. If the rebase is clean, proceed.

## 4. Run the test suite and lint

```bash
composer test                                              # full suite (excludes integration-destructive)
./vendor/bin/pest packages/<name>/tests                    # package-specific tests
./vendor/bin/phpcs --standard=phpcs.xml packages/<name>/   # phpcs
./vendor/bin/php-cs-fixer fix packages/<name>/ --dry-run   # cs-fixer
```

All tests must pass and lint must be clean. Fix ALL lint errors in touched files before merging, including pre-existing ones.

**No PR CI workflow exists in this repo.** `gh pr checks <N>` will report no checks. The merge-readiness signal is local lint + tests passing plus `gh pr view <N> --json mergeStateStatus,mergeable` returning `CLEAN`/`MERGEABLE`. There is no async CI to wait for — do not write "will merge once CI is green" in PR comments.

## 5. Review the code

### General code quality

Check each changed file against project standards:

- **`declare(strict_types=1)`** in every PHP file
- **Constructor property promotion** used consistently
- **No unnecessary imports** (self-namespace, unused)
- **No hardcoded paths or environment-specific values** — use `PHP_BINARY`, env vars, etc.
- **Tests exist** for new classes, bindings, and behavior
- **Module bindings** have corresponding tests in `ModuleBindingsTest.php`
- **No `final` classes**, including exceptions (blocks Preferences extensibility — see CLAUDE.md)
- **Type declarations** on all parameters, returns, properties
- **Compare with sibling packages** — if adding something to `database-mysql`, check how `database-pgsql` does it and ensure consistency

### Loud errors, no silent fallbacks

Marko's "loud errors" principle (CLAUDE.md) means missing config, missing files, or invalid state should throw with a helpful message — not return defaults, empty strings, HTML comments, or silently degrade. Specifically reject:

- `try { ... } catch (Throwable) { return $default; }` patterns that swallow exceptions
- Returning `<!-- comment -->` or empty strings for missing build artifacts (e.g., a Vite manifest)
- "Best-effort" fallbacks that hide configuration mistakes

Throw a `MarkoException`-derived exception with `message`, `context`, and `suggestion` fields so the `errors` package can render a useful screen.

### No defensive checks on container resolutions

The container is type-guaranteed. After `$x = $container->get(FooInterface::class);`, do not write `if (! $x instanceof FooInterface) { throw ... }`. That's validation for scenarios that can't happen. Reference `packages/admin-auth/module.php` for the canonical binding pattern.

### No pseudo-functionality

Per CLAUDE.md core principles: do not build fake features to demonstrate concepts. Examples to flag:

- An in-memory-only `flash()` that pretends to be session-backed
- Stub config that won't actually be read
- Methods that exist only to round out an interface but throw `not implemented`

Either integrate with a real abstraction or remove the surface.

## 5a. Package-PR-specific checks

When the PR adds or modifies a package under `packages/`, also verify:

### `module.php` is optional, and minimal when used

`ManifestParser::parseModulePhp()` returns `[]` when the file doesn't exist. **Skip `module.php` entirely** if the package doesn't need:

- Interface → implementation bindings (e.g., `CacheInterface::class => FileCacheDriver::class`)
- Shared singletons
- Custom factory closures
- A `boot` callback
- Explicit load ordering (`sequence: { after / before }`)

For packages whose only public surface is a concrete class with a typed constructor, autowiring already works without `module.php`.

When you do include it, keep it minimal. Defaults the framework already provides — do not re-state:

- `enabled: true` is the default
- `sequence: { after: [...] }` — DI resolves lazily, so module load order rarely matters; only add when a `boot` callback or eager construction depends on another module's bindings
- **Redundant `bindings` entries.** List-style singletons (`X::class` as a value, not a key) trigger autowiring directly via `BindingRegistry::registerModule()` (`if (is_int($interface)) { $this->container->singleton($implementation); continue; }`), so this is redundant:

  ```php
  // Don't write this:
  'bindings' => [X::class => X::class],
  'singletons' => [X::class],

  // List-style singleton alone is enough:
  'singletons' => [X::class],
  ```

  Only add a `bindings` closure when construction needs custom logic (factory call, conditional config, multi-step wiring). See `packages/view-latte/module.php` and `packages/authentication/module.php` for examples of when closures are warranted.

### `composer.json`

- `"type": "marko-module"` (or `library` only if genuinely a non-module library)
- **No hardcoded `"version"`** — Composer infers from the branch
- Internal Marko deps use `"marko/x": "self.version"`
- `marko/env` must be in `require` if `config/{name}.php` uses `env()`
- `marko/testing` in `require-dev` if tests use fakes
- **4-space indent** matching every other package's composer.json
- No package-local `scripts` block, no redundant dev deps for tooling that lives at the monorepo root (pest, phpstan, php-cs-fixer)
- `extra.marko.module: true`

### File structure for a new package

Required files:

- `packages/{name}/composer.json`
- `packages/{name}/module.php` (only if needed — see above)
- `packages/{name}/LICENSE` (MIT, copyright Devtomic LLC)
- `packages/{name}/.gitattributes` (export-ignore tests/, .github/, etc. — copy from a sibling package and verify column alignment)
- `packages/{name}/README.md` — slim-pointer format per `docs/DOCS-STANDARDS.md`: title + one-liner, Installation, Quick Example, Documentation link
- `packages/{name}/src/...` (PSR-4 namespace `Marko\Name\`)
- `packages/{name}/tests/...`
- `packages/{name}/config/{name}.php` if configurable

Cross-cutting updates a new package PR must include:

- Root `composer.json`: add to `repositories` (path), `replace`, `autoload-dev` for `Marko\Name\Tests\`. **Keep `repositories`, `replace`, and `autoload-dev.psr-4` alphabetically sorted** (Composer auto-sorts `require`/`require-dev`, but not these). For nested namespaces (`Marko\Cache\File\Tests\`), place the parent's own `Tests\` entry first, then siblings alphabetically.
- `.github/ISSUE_TEMPLATE/bug_report.yml` — add `{name}` to the package dropdown
- `.github/ISSUE_TEMPLATE/feature_request.yml` — add `{name}` to the package dropdown
- `tests/IntegrationVerificationTest.php` and `tests/PackagingTest.php` — bump `toHaveCount(N)` and the test descriptions
- **`docs/src/content/docs/packages/{name}.md`** — REQUIRED per `docs/DOCS-STANDARDS.md`. Every package needs a corresponding docs page (frontmatter title/description, intro, Installation, Configuration, Usage, Errors, API Reference, Related Packages). The `doc-updater` agent normally handles this in the post-implementation pipeline (`.claude/pipeline.md`), but for a contributor PR review, verify the page is present — do not assume it will be added later.

## 6. Make fixes directly on the PR

If the contributor enabled "Allow edits from maintainers" (`maintainerCanModify: true`), push fixes directly to their branch:

```bash
# Check if we can push
gh pr view <number> --json maintainerCanModify,headRepositoryOwner

# Add their fork as a remote (one-time)
git remote add <username> git@github.com:<username>/marko.git

# After making changes and committing (use Co-Authored-By trailers for the contributor)
git push <username> <branch-name>

# If rebased (history changed), force push is needed
git push <username> <branch-name> --force-with-lease=<branch>:<original-commit-sha>
```

If `maintainerCanModify` is `false`, comment on the PR requesting changes instead.

Common fixes to make:

- Rebase onto current `develop`
- Add missing tests
- Fix lint/style issues
- Remove hardcoded paths or environment-specific values
- Remove unnecessary imports
- Add the docs page if it was missed

When committing maintainer follow-ups, include the contributor's `Co-Authored-By:` so the credit is captured on the commit.

## 7. Comment on the PR

When posting a review comment on the PR (thank-you, summary of fixes, next-steps pointer):

- **Always show the draft to the user for approval first.** Do not call `gh pr comment` without surfacing the full body in chat and waiting for confirmation. Public, persistent communication tagged to a contributor needs final review.
- Summarize what was changed and why (loud errors, conventions, docs page, etc.)
- Point to the next package or follow-up issue if applicable
- Do not promise a CI gate that doesn't exist

If the PR description needs maintainer-side updates after pushing fixes:

```bash
gh api repos/marko-php/marko/pulls/<number> -X PATCH -f body="$(cat <<'EOF'
<updated body with new section for maintainer changes>
EOF
)"
```

Add a section like:

```markdown
### Additional changes (maintainer)
- Rebased onto current `develop`
- Added missing tests for ...
- Fixed lint issues in ...
- Added docs/src/content/docs/packages/{name}.md
```

## 8. Merge

**Use the merge commit strategy** (not squash) to preserve the contributor's commit history in the graph. Squash collapses the contributor's authorship into a single maintainer-authored commit with a `Co-Authored-By:` trailer — the `git log --graph` view loses their work.

```bash
gh pr merge <number> --merge --subject "Merge pull request #<N> from <user>/<branch>" --body "<short description>"
```

`Co-Authored-By:` trailers belong on maintainer fix commits (step 6), not the merge commit itself — the merge preserves contributor authorship through the graph topology.

After merging:

```bash
git checkout develop && git pull origin develop
git branch -D feature/<name>          # clean up local branch (squash/merge usually shows as not-fully-merged)
git remote remove <contributor-fork>  # optional: clean up the contributor remote
```

## Checklist

Before merging any package PR:

- [ ] Issue is valid and the fix is correct
- [ ] Branch is rebased onto current `develop`
- [ ] `composer test` passes
- [ ] `./vendor/bin/phpcs` clean on touched files
- [ ] `./vendor/bin/php-cs-fixer fix --dry-run` clean on touched files
- [ ] New code has corresponding tests
- [ ] No hardcoded paths or environment-specific values
- [ ] No `final` classes (including exceptions)
- [ ] No defensive `instanceof` checks on container resolutions
- [ ] No silent fallbacks (HTML comments, swallowed exceptions, default returns on missing config)
- [ ] No pseudo-functionality (in-memory-only stubs pretending to be backed)
- [ ] Strict types, type declarations everywhere
- [ ] `module.php` is omitted entirely or kept minimal (no redundant `enabled`, `sequence`, or list-style binding entries)
- [ ] `composer.json`: `type: marko-module`, no `version`, `self.version` for internal deps, 4-space indent
- [ ] LICENSE, .gitattributes (aligned), slim README per DOCS-STANDARDS
- [ ] Issue template dropdowns updated (bug + feature)
- [ ] `IntegrationVerificationTest` and `PackagingTest` counts bumped
- [ ] `docs/src/content/docs/packages/{name}.md` exists and follows the package-page structure
- [ ] PR description accurately reflects all changes
- [ ] PR comment drafted, reviewed, then posted
- [ ] Merge via `--merge` (preserves contributor commits), not `--squash`
- [ ] Clean up any accidental branches pushed to origin
