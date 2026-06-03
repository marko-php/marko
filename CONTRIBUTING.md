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

Append `!` for breaking changes: `feat(auth)!: remove legacy driver`.

## Pull Requests

- **Title must follow commit format** — e.g. `feat(cache): add Redis driver`
- Reference issues with `Closes #N` in the PR body
- All tests must pass: `./vendor/bin/pest --parallel`
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

## Release Process (Maintainers)

From a clean `develop` branch:

```bash
./bin/release.sh <version>   # e.g. ./bin/release.sh 1.2.0
```

The script handles everything: merges develop into main, runs tests, tags, pushes, creates a GitHub Release with auto-generated notes, and returns develop to sync with main. Version format is `X.Y.Z` — no `v` prefix.

## GitHub Actions

When adding or editing workflows under `.github/workflows/`:

- **Pin third-party actions to the latest stable major.** Currently that means `actions/checkout@v6`, etc. Older majors (`@v4`, `@v5`) emit Node deprecation warnings as GitHub rolls runners forward.
- **Stay consistent with the rest of the repo.** Before introducing a new action version, grep the existing workflows — if `actions/checkout@v6` is already in use, match it rather than picking a different major.
- **Don't pin to floating refs** (`@main`, `@master`). Majors are stable enough; floating refs are not.

A quick check:

```bash
grep -rh 'uses: actions/' .github/workflows/ | sort -u
```

## Code Standards

See [`.claude/code-standards.md`](.claude/code-standards.md) for PHP-specific conventions (strict types, constructor promotion, type declarations, etc.).
