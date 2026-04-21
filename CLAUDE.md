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
# Run tests
./vendor/bin/pest --parallel

# Run with coverage
./vendor/bin/pest --parallel --coverage --min=80

# Lint (check)
./vendor/bin/phpcs

# Lint (fix)
./vendor/bin/php-cs-fixer fix
```

## Key Conventions

- **Branch naming** - all branches use `feature/{name}` format, regardless of change type (fix, feature, refactor, docs, etc.). Never use `fix/`, `chore/`, or other prefixes
- **No hardcoded versions in composer.json** - never add `"version"` to package composer.json files; let Composer infer from the branch
- **Constructor property promotion** - always use it
- **Strict types** - every file needs `declare(strict_types=1)`
- **No magic methods** - be explicit
- **No final classes** - blocks Preferences (extensibility)
- **readonly** - use when appropriate for immutability, not as blanket rule
- **Type declarations** - required on all parameters, returns, properties

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

## Detailed Configuration

Project configuration files are in `.claude/`:
- `project-overview.md` — Project identity and tech stack
- `architecture.md` — Technical patterns and directory structure
- `testing.md` — Test configuration, TDD workflow, and patterns
- `code-standards.md` — Coding conventions and style rules
