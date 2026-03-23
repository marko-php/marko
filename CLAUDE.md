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

- **No hardcoded versions in composer.json** - never add `"version"` to package composer.json files; let Composer infer from the branch
- **Constructor property promotion** - always use it
- **Strict types** - every file needs `declare(strict_types=1)`
- **No magic methods** - be explicit
- **No final classes** - blocks Preferences (extensibility)
- **readonly** - use when appropriate for immutability, not as blanket rule
- **Type declarations** - required on all parameters, returns, properties

## Demo Application

The `demo/` directory contains minimal bootstrap infrastructure for testing that packages integrate correctly.

```
demo/
  vendor/         # marko/core, marko/blog (via Composer path repos)
  modules/        # Empty (for manually-installed third-party modules)
  app/
    blog/         # Empty until real customization needed
  public/         # Web root (index.php - minimal bootstrap)
```

**What belongs in demo/:**
- `demo/public/index.php` - Bootstrap infrastructure that wires packages together
- `demo/app/*/module.php` - Module structure scaffolding (can be empty)
- Integration that proves packages work together

**What does NOT belong in demo/:**
- Code that "demonstrates" or "shows" a feature works
- Overrides that exist only to prove Preferences/Plugins/etc. function
- Any customization without a real, practical purpose

> **⚠️ CRITICAL: Plans must NEVER include demo/app/ customization requirements**
>
> This is the #1 source of pseudo-functionality. When writing plans:
>
> **NEVER write requirements like:**
> - "demo/app/blog overrides PostController via Preference"
> - "app PostController uses DisableRoute to demonstrate route removal"
> - "demo/app/ shows how to customize X"
>
> **Tests verify features work, not demo code.** The packages have comprehensive tests. Adding demo/app/ code that duplicates what tests already verify is pseudo-functionality.
>
> **The word "demonstrate" is a red flag.** If a plan requirement uses "demonstrate," "show," or "prove" in relation to demo/, delete that requirement. Tests prove things work. Demo code uses things for real purposes.
>
> **When is demo/app/ customization appropriate?**
> Almost never at this stage. Only when:
> 1. There's real data to work with (database, files, external APIs)
> 2. There's a genuine business reason to customize behavior
> 3. The customization would exist in a real application
>
> Until then, demo/app/ modules remain empty scaffolding. That's the correct state.

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
