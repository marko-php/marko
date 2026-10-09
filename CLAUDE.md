# Marko Framework

Marko is a modular PHP 8.5+ framework combining Magento's extensibility with Laravel's developer experience.

## Core Principles

1. **Loud errors** - No silent failures, helpful messages
2. **Explicit over implicit** - No magic, everything discoverable
3. **Pragmatically opinionated** - Guide toward better patterns, grounded in real-world needs
4. **True modularity** - Interface/implementation split, clean boundaries
5. **No pseudo-functionality** - Don't build fake features to demonstrate concepts; only build real functionality when core supports it. If there's nothing meaningful to build, build nothing.

## Commands

```bash
# Run tests (fast — excludes slow destructive integration tests)
composer test

# Run all tests including destructive integration tests
composer test:all

# Run with coverage
./vendor/bin/pest -c phpunit.xml --parallel --exclude-group=integration-destructive --coverage --min=80

# Lint (check)
./vendor/bin/phpcs

# Lint (fix)
./vendor/bin/php-cs-fixer fix

# Static analysis — NOT part of composer test; must be zero errors
composer phpstan

# Everything the CI gate runs (tests + lint + static analysis)
composer ci

# Real-service suite: fixture app + pgsql/mysql drivers + live Redis (needs the
# services from tests/Integration/compose.yml; see .claude/testing.md)
composer test:integration
```

Every PR is gated by the `CI` workflow on `Tests`, `Lint`, and `Static analysis`. A red check blocks the merge; `develop` never carries a failing test, lint error, or PHPStan error. The same workflow's `Integration` job runs the real-service suite against Postgres, MySQL and Redis. It is not yet a required status check (a maintainer decision, #226), but a red `Integration` run blocks the merge all the same.

## Key Conventions

- **Branch naming** - all branches use `feature/{name}` format, regardless of change type (fix, feature, refactor, docs, etc.). Never use `fix/`, `chore/`, or other prefixes
- **No hardcoded versions in composer.json** - never add `"version"` to package composer.json files; let Composer infer from the branch
- **Constructor property promotion** - always use it
- **Strict types** - every file needs `declare(strict_types=1)`
- **No magic methods** - be explicit
- **No final classes** - blocks Preferences (extensibility)
- **readonly** - use when appropriate for immutability, not as blanket rule
- **Type declarations** - required on all parameters, returns, properties
- **Tests never read documentation** - no test may open, assert on, or depend on a docs page, README, `CLAUDE.md`, `.claude/` file, or any other prose written for readers. Docs explain how things work; they are not a spec for the implementation. Test the code's behavior instead. Files the code itself ships or emits (templates, generated config, skill files a package installs) are product, not docs, and may be tested.

## Feature Development

For simple fixes and quick changes, use TDD (when at all possible).

For any feature or request beyond simple ones, use the `hcf:plan-create` skill to trigger the autonomous development workflow. NEVER use Claude Code's built-in plan mode. After writing a plan, ask user if they would like to execute it. Also provide the command to run it later with the `hcf:plan-orchestrate` skill.

Use this workflow for new features, multi-file changes, or anything requiring multiple steps or tests.

## Project Overview 

<project-overview>
@.claude/project-overview.md
</project-overview>

## Architecture

<architecture>
@.claude/architecture.md
</architecture>

## Reviewing PRs

When reviewing or merging a pull request, read `.claude/pr-review-process.md` first. It has the full review workflow, code-quality checklist, package-PR-specific checks (module.php minimalism, required docs page, composer.json conventions, cross-cutting updates), and the merge policy. Not auto-loaded — pull it into context only when actively reviewing.

## Detailed Configuration

Project configuration files are in `.claude/`:
- `project-overview.md` — Project identity and tech stack
- `architecture.md` — Technical patterns and directory structure
- `testing.md` — Test configuration, TDD workflow, and patterns
- `code-standards.md` — Coding conventions and style rules
- `pr-review-process.md` — PR review workflow, checklist, and merge policy
- `module-development.md` — Building new packages/modules
- `sibling-modules.md` — Naming and conventions for driver packages
- `release-process.md` — Release workflow
- `skills/release/SKILL.md` — The `/release` skill: assess merged PRs, recommend a version, then cut the release
