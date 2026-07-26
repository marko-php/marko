---
name: release
description: >
  Cut a new Marko release. Reads every PR merged since the last tag, audits their
  release-notes labels, recommends the next version number with reasoning, and — only after
  you confirm or override it — runs `./bin/release.sh`.
  **Use this skill whenever the user types /release or asks to cut, ship, publish, or tag a release.**
  Takes no arguments; the version is decided in conversation, not on the command line.
---

# Cut a Marko release

`bin/release.sh` already automates everything mechanical: the full test suite, the
`develop` → `main` merge, changelog generation, the tag, the GitHub Release, and the
merge-back to `develop`. It does not decide *whether* to release or *what to call it*.
That judgment is this skill's only job.

Two things must be right before a tag is pushed, and both are listed as manual in
`.claude/release-process.md`:

1. **Every merged PR is labeled**, or its changes vanish into a generic "Other Changes"
   bucket in the release notes.
2. **The version number is correct**, because all 70 packages share it and Composer
   constraints downstream depend on it.

There is exactly **one approval gate**: present the recommendation, stop, and wait. Do not
tag on your own initiative — not even for an obviously-correct patch bump.

## Arguments

None. `/release` takes no arguments; the version is settled at the approval gate in step 5.

If the user does type something after `/release` anyway, treat it as an authoritative
override and skip straight to validating it (same checks as an override at the gate).

## Step 1 — Verify preconditions

```bash
git fetch --quiet --tags origin develop main
git status --short                        # must be empty
git rev-parse --abbrev-ref HEAD           # must be develop
git rev-list --count origin/develop..develop   # must be 0 (nothing unpushed)
git rev-list --count develop..origin/develop   # must be 0 (nothing unpulled)
php -v                                    # must be 8.5.x, else set PHP_BIN
command -v gh jq                          # both required by bin/release.sh
```

Also confirm there is something to release: `git log $(git tag --sort=-v:refname | head -1)..develop --oneline` must be non-empty.

If any check fails, say exactly which one and stop. Do not quietly fix it — an unpushed
commit or a dirty tree usually means work is still in flight, which is the user's call,
not yours.

If `php -v` is not 8.5, don't abandon the run — pass an explicit interpreter through to
the script instead: `PHP_BIN=/path/to/php8.5 ./bin/release.sh X.Y.Z`.

## Step 2 — Read what is shipping

```bash
LAST_TAG=$(git tag --sort=-v:refname | head -1)
git log "$LAST_TAG"..develop --pretty='%s' | grep -oE '#[0-9]+' | sort -u
```

Read every PR you found — title, body, and labels. Commit subjects alone will mislead you
about scope:

```bash
gh pr view <N> --json number,title,body,labels,url
```

**Flag any commit on `develop` with no `#NN` reference.** `bin/release.sh` builds the notes
by walking `git log` and resolving PR numbers, so an unreferenced commit is silently
omitted from both `CHANGELOG.md` and the GitHub Release. Surface these at the gate; the fix
is a follow-up commit that mentions the PR, not a hand-edited changelog.

## Step 3 — Audit the labels

`.github/release.yml` buckets PRs into release-notes sections by label. Anything unlabeled
lands in "Other Changes"; anything mislabeled lands in the wrong section.

| Label | Section |
|-------|---------|
| `breaking` | Breaking Changes |
| `enhancement` | New Features |
| `bug` | Bug Fixes |
| `documentation` | Documentation |
| `refactor` | Refactoring |
| `testing` | Testing |
| `ci` | CI |
| `maintenance` | Maintenance |

`duplicate`, `invalid`, `wontfix`, `question`, `good first issue`, and `help wanted` are
excluded from the notes entirely — a release-worthy PR carrying only one of those is a
labeling bug.

Propose label corrections at the gate rather than applying them silently. When applying
them, note that `gh pr edit` silently fails on this repo (GraphQL Projects-classic bug) —
use the REST endpoint:

```bash
gh api repos/marko-php/marko/issues/<N>/labels -f "labels[]=bug"
gh api repos/marko-php/marko/issues/<N>/labels/enhancement -X DELETE
```

## Step 4 — Decide the version

Marko is in `0.x`: all 70 packages share one version, and `1.0.0` is the first release with
semver guarantees. Compute from the latest tag.

- **Patch** (`0.8.4` → `0.8.5`) — bug fixes, documentation, CI, refactors, tests, and
  additive changes that add no public API surface.
- **Minor** (`0.8.4` → `0.9.0`) — a new package, new public API, or any breaking change.
  While in `0.x` there is no separate major channel, so breaking changes ride the minor.
- **Major** (`1.0.0`) — never infer this. Only when the user says the API is stable.

### Labels do not decide the version

Labels route a PR into a release-notes section. They say nothing about the bump. The
`enhancement` label feeds a section titled "New Features" — that is a heading, not a minor
version. **Judge every `enhancement` on its own merits; do not let one in the batch
mechanically escalate a patch to a minor.**

Classify each PR by what a consumer can observe after `composer update`:

1. **Never leaves the monorepo** — `.claude/`, `bin/`, `.github/`, root `tests/`, repo docs.
   Only `packages/*` is copied into split repos, so nothing here can reach anyone's
   `vendor/`. **Irrelevant to the version, whatever its label.** Confirm mechanically:

   ```bash
   gh pr view <N> --json files -q '[.files[].path] | map(select(startswith("packages/"))) | length'
   ```

   A `0` means repo-internal tooling. This is the common case for maintainer-facing
   `enhancement` PRs — a new skill, a CI tweak, a release script improvement.

2. **Ships in a package but adds no callable surface** — CLI output or prompt wording, a
   docs page, an internal refactor, a new fake in `marko/testing` used only by our own
   suite. Nothing new for an app developer to call, extend, configure, or depend on.
   **Patch.** This includes internals added in service of a fix — a new exception factory,
   for instance, is *thrown* at consumers rather than built against, so it does not make a
   bug fix into a feature.

3. **Adds or changes surface a consumer builds against** — a new interface, method,
   attribute, config key, container binding, event, console command, or an entire package.
   Also anything that changes existing behavior an app could already be relying on.
   **Minor.**

Only tier 3 forces the minor. A batch of tier-1 and tier-2 work plus a bug fix is a patch,
even when several PRs carry `enhancement`.

Calibration example — the batch that shipped as `0.8.5`, which carried two `enhancement`
labels and was still correctly a patch:

| PR | Label | Tier | Why |
|----|-------|------|-----|
| #142, #144 | documentation | 1 | repo docs and skills, nothing under `packages/` |
| #143 | enhancement | 2 | a `devai:install` prompt tip — no new callable surface |
| #146 | enhancement | 1 | maintainer-only `/release` skill under `.claude/` |
| #145 | bug | 2 | unblocked `db:migrate`; its new exception factory is thrown, not built against |

**The tiebreaker for a genuinely mixed batch is Composer reachability.** In `0.x`, Composer
treats the *minor* as the breaking position: `^0.8.4` accepts `0.8.5` but refuses `0.9.0`. A
patch reaches every downstream project on a plain `composer update`; a minor sits unnoticed
until someone edits their constraint. So when the release exists to get a fix into users'
hands, and the rest of the batch is tier 1 or 2, prefer the patch. Do not use this as cover
for hiding tier-3 work in a patch — that earns the minor even if it means a slower rollout.

State the decision in one or two lines and cite the PRs driving it. Also check whether the
bug being fixed makes a released version unusable — that is the difference between "worth
releasing now" and "wait for more to accumulate", and the user should hear which one this
is.

## Step 5 — Present the recommendation, then stop

Show, concisely:

1. **What is shipping** — a table of PRs since the last tag, with labels **and the tier you
   assigned each one**. Showing the tiers is what makes the version recommendation
   auditable: the user can disagree with one classification rather than the whole call.
2. **Label fixes needed** — or explicitly "labels are clean".
3. **Recommended version + rationale** — including whether this is urgent enough to ship
   now. If any `enhancement` in the batch did *not* escalate the bump, say so explicitly and
   why, rather than leaving the user to wonder whether it was overlooked.
4. **What will happen on approval** — merge `develop` into `main`, run the full suite
   *including* the `integration-destructive` group, generate `CHANGELOG.md`, commit, tag,
   push, create the GitHub Release, merge back to `develop`.

**Be precise about the failure state — do not promise a clean abort.** `bin/release.sh`
checks out `main` and merges `develop` *before* it runs the tests. A test failure therefore
leaves the local repo checked out on `main` with the merge already made. Nothing is pushed,
no tag exists, and `CHANGELOG.md` is untouched — but it is not a no-op. Recovery is
`git checkout develop`, fix the failure, and re-run; the merge is idempotent. Mention this
when describing what will happen, and note that re-running while still on `main` trips the
Step 1 branch precondition.

Then wait. If the user overrides the version, validate it before running: `X.Y.Z` with no
`v` prefix and no pre-release suffix, strictly greater than the latest tag, and not an
existing tag. If it looks wrong, say so once — then defer if they confirm.

## Step 6 — Execute

From `develop`, with a clean tree:

```bash
./bin/release.sh <version>
```

Expect this to run for several minutes — the destructive integration group builds real
installs. Let it finish; do not run it in the background and do not re-run it after a
partial failure without reading the error first. If it failed in the test phase you are now
on `main` — `git checkout develop` before retrying.

## Step 7 — Report

Give the user the version shipped and the GitHub Release URL, then point at the two
asynchronous things that finish after the tag:

- Split workflow: <https://github.com/marko-php/marko/actions>
- Packagist: <https://packagist.org/packages/marko/>

If the script aborted, relay its error verbatim and stop.

## Guardrails

- **Never tag without explicit approval of a specific version number.**
- **Never hand-edit `CHANGELOG.md`.** `bin/release.sh` generates and commits it; a manual
  edit produces a duplicate section and a redundant commit.
- **Never run the script from a branch other than `develop`**, and never commit directly to
  `main` — the script owns that merge.
- **Never narrow the test suite to get past a failure.** No `--exclude-group`, no skipping.
  A failing destructive-integration test means the release would ship a broken install.
- Never invent a release-notes entry for something you did not find in a merged PR.
- Never round the version up to make a release look bigger. Never bump the minor merely
  because several PRs landed, or because one of them is labeled `enhancement` — only tier-3
  surface changes force it.
- If nothing user-facing merged since the last tag, say so and ask whether to proceed
  instead of manufacturing a reason to tag.
