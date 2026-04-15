# Devil's Advocate Review: release-workflow

## Critical (Must fix before building)

### C1. `set -euo pipefail` defeats `|| true` pattern for `gh release create` (Task 004)

The existing `bin/release.sh` uses `set -euo pipefail` on line 2. The plan says to handle `gh release create` failure with `|| true` or a warning. However, if any command in a pipeline or subshell fails unexpectedly before reaching the `|| true`, `set -e` can still abort. More importantly, the worker needs explicit guidance that `set -e` is active, otherwise they may write something like:

```bash
PREV_TAG=$(git describe --tags --abbrev=0 HEAD~1)
gh release create "$TAG" --generate-notes --notes-start-tag "$PREV_TAG" --latest || true
```

If `git describe` fails (e.g., first release with no previous tag), `set -e` kills the script before it ever reaches `gh release create`. The task must explicitly require handling the "no previous tag" case.

### C2. First release has no previous tag for `--notes-start-tag` (Task 004)

Task 004 requires "it references the previous tag for note range using --notes-start-tag" and says "Previous tag is determined dynamically." But if this is the first GitHub Release (which it likely is given the project just started tagging), there is no previous tag. `git describe --tags --abbrev=0 HEAD^` or similar will fail. The task must handle the case where no previous tag exists by omitting `--notes-start-tag` entirely.

### C3. Auto-labeling workflow needs `pull-requests: write` permission (Task 003)

The workflow uses `gh pr edit --add-label` which requires write permission on pull requests. GitHub Actions workflows triggered by `pull_request` from forks run with read-only tokens by default. The task says "Uses `GITHUB_TOKEN` permissions (no extra secrets needed)" but does not specify that the workflow YAML must declare `permissions: pull-requests: write`. Without this, the `gh` command will fail with a 403 on any fork PR.

Additionally, `pull_request` events from forks cannot write to the repo. The workflow should use `pull_request_target` instead, which runs in the context of the base repo and has write access. This is a critical distinction the task must specify.

## Important (Should fix before building)

### I1. Task 003 label removal is underspecified

Requirement: "it removes stale type labels when PR title changes." The worker needs to know which labels to remove. If a PR title changes from `fix: something` to `feat: something`, the workflow must remove `bug` and add `enhancement`. The task should list the full set of type labels (bug, enhancement, documentation, refactor, testing, ci, maintenance) that should be treated as mutually exclusive, and specify that all type labels should be removed before the new one is added.

### I2. Task 002 has a soft dependency on 001 that is not declared

Task 002 says "Labels available after task 001: bug, enhancement, documentation, refactor, testing, ci, maintenance, breaking." While the release.yml file itself is just a YAML config and can reference labels that don't exist yet, a worker testing task 002 in isolation might be confused about why it references labels from task 001. More importantly, the release.yml categories only work correctly at runtime if the labels exist. The dependency table shows 002 depends on nothing, which is fine for file creation, but this implicit ordering should be noted in the task.

### I3. Package dropdown in issue templates is incomplete (Task 005)

Task 005 lists these categories: Core, Database, Cache, View, Mail, Queue, Session, Filesystem, Errors, Log, Encryption, Auth, Admin, Translation, Notification, PubSub, Media, Blog, Other.

But the actual repo has many more packages that don't fit cleanly into those categories: API, CLI, Config, CORS, Dev Server, Env, Framework, Hashing, Health, HTTP, Pagination, Rate Limiting, Routing, Scheduler, Search, Security, SSE, Testing, Validation, Webhook. The "Other" catch-all handles this, but several major packages (Routing, Config, Validation, Security) deserve their own entries. The dropdown should be updated to cover the major areas more accurately.

### I4. Task 004 does not specify where exactly in the script to insert the release creation

The release script has a specific flow: push tag -> checkout develop -> merge main -> print "Next steps". Task 004 says "after tag push but before checkout develop." The task should quote the exact lines it expects to insert between (after `git push origin "$TAG"` on line 67, before `git checkout develop` on line 69) so the worker doesn't have to guess the insertion point.

### I5. `gh` CLI availability not validated in release.sh (Task 004)

Task 004 adds `gh release create` to the release script, but the existing script never checks if `gh` is installed. If a maintainer doesn't have `gh` CLI, the release will complete (tag pushed, split triggered) but then error on the release creation step. Since `set -euo pipefail` is active, even with `|| true` on the `gh release create` line, if `gh` isn't found at all, the behavior depends on the shell. The task should add a `gh` availability check near the existing PHP check, or at minimum document that `gh` is required.

## Minor (Nice to address)

### M1. No tests are actually executable for most tasks

All 7 tasks produce static files (shell scripts, YAML, Markdown). The "test descriptions" in requirements use `it creates...` / `it includes...` phrasing suggesting Pest tests, but these are not testable with Pest. The workers will likely just verify file contents manually or write simple shell assertions. This isn't a blocker but the TDD framing is misleading for static file tasks.

### M2. Architecture.md references `devtomic` org but actual repo uses `marko-php`

Architecture.md references `github.com/devtomic/marko` while `split.yml` and `release.sh` both use `marko-php`. Task 007 (CONTRIBUTING.md) should use `marko-php` consistently. Not a plan issue per se, but worth noting so the CONTRIBUTING.md worker uses the correct org.

### M3. Label colors are arbitrary

Task 001 specifies hex colors (`#d4c5f9`, `#bfd4f2`, etc.) but there's no visual system or reasoning documented. This is fine but a worker might question whether these are intentional. They appear to be GitHub's default palette colors, which is sensible.

## Questions for the Team

1. **Should `pull_request_target` be used instead of `pull_request`?** Using `pull_request_target` allows write access for fork PRs but runs the workflow code from the base branch, not the PR branch. Since this workflow only reads the PR title (not code), `pull_request_target` is safe and appropriate. But this is a security-relevant decision that should be confirmed.

2. **Is `perf:` a conventional commit type that should be supported?** Many projects include `perf:` for performance improvements. The current plan doesn't map it. If desired, it could map to `enhancement` or get its own label.

3. **Should the release script push the develop merge?** Currently `release.sh` does `git checkout develop && git merge main` but never pushes develop. Task 004 doesn't address this either. After adding the release creation, the "next steps" message should clarify whether the developer needs to `git push origin develop` manually.
