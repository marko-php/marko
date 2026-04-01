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

## Code Standards

See [`.claude/code-standards.md`](.claude/code-standards.md) for PHP-specific conventions (strict types, constructor promotion, type declarations, etc.).
