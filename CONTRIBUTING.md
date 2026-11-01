# Contributing to Marko

## Workflow

```
git checkout develop && git pull
git checkout -b feature/my-feature
# make changes, commit, push
git push -u origin feature/my-feature
# open PR targeting develop
```

## Branch Naming

| Prefix | Use for |
|---|---|
| `feature/{name}` | New features |
| `fix/{name}` | Bug fixes |
| `docs/{name}` | Documentation changes |

## Commit Format

Marko uses [Conventional Commits](https://www.conventionalcommits.org/).

```
<type>[(<scope>)][!]: <description>
```

| Type | Purpose |
|---|---|
| `feat` | New feature |
| `fix` | Bug fix |
| `docs` | Documentation |
| `refactor` | Code restructure, no behavior change |
| `test` | Tests only |
| `ci` | CI/CD configuration |
| `chore` | Build, deps, maintenance |

Append `!` for breaking changes: `feat(auth)!: remove legacy driver`. A `BREAKING CHANGE:` footer line in the body also marks a change as breaking. A "Breaking changes" heading in the PR description does not, so a section that says "None" never adds the label.

## Pull Requests

- **Title must follow commit format** — e.g. `feat(cache): add Redis driver`
- Reference issues with `Closes #N` in the PR body
- All tests must pass: `./vendor/bin/pest --parallel`
- Changes to driver packages or `module.php` wiring should also pass `composer test:integration`, which runs against real Postgres and Redis. CI runs it in the **Integration** job. See "Integration Tests" in `.claude/testing.md` for how to start the services locally.
- Lint must pass: `./vendor/bin/phpcs`

### Auto-Labeling

Labels are applied automatically from the PR title prefix:

| Prefix | Label |
|---|---|
| `fix` | `bug` |
| `feat` | `enhancement` |
| `docs` | `documentation` |
| `refactor` | `refactor` |
| `test` | `testing` |
| `ci` | `ci` |
| `chore` | `maintenance` |
| `type!` | `breaking` (added alongside type label) |
| `BREAKING CHANGE:` footer line in the body | `breaking` |

A PR title that doesn't follow the commit format fails the **Auto Label PR** check, because an unlabeled PR would land in "Other Changes" in the release notes. The `breaking` label is only ever added automatically, never removed, so maintainers can add it by hand.

## Release Process (Maintainers)

From a clean `develop` branch:

```bash
./bin/release.sh <version>   # e.g. ./bin/release.sh 1.2.0
```

The script handles everything: merges develop into main, runs tests, tags, pushes, creates a GitHub Release with auto-generated notes, and returns develop to sync with main. Version format is `X.Y.Z` — no `v` prefix.

## GitHub Actions

When adding or editing workflows under `.github/workflows/`:

- **Pin third-party actions to the latest stable major.** Currently that means `actions/checkout@v7`, `actions/setup-node@v7`, `ramsey/composer-install@v4` and `shivammathur/setup-php@v2`. Older majors emit Node deprecation warnings as GitHub rolls runners forward.
- **Stay consistent with the rest of the repo.** Before introducing a new action version, grep the existing workflows — if `actions/checkout@v7` is already in use, match it rather than picking a different major.
- **Don't pin to floating refs** (`@main`, `@master`). Majors are stable enough; floating refs are not.

A quick check:

```bash
grep -rh 'uses: actions/' .github/workflows/ | sort -u
```

## Code Standards

See [`.claude/code-standards.md`](.claude/code-standards.md) for PHP-specific conventions (strict types, constructor promotion, type declarations, etc.).
