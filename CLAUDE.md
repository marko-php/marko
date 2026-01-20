# Marko Framework

> **Opinionated, not restrictive. There's always a way - it's just the right way.**

Marko is a PHP 8.5+ framework combining Magento's extensibility with Laravel's developer experience.

## Quick Reference

### Commands
```bash
# Run tests
./vendor/bin/pest

# Run tests in parallel
./vendor/bin/pest --parallel

# Run with coverage
./vendor/bin/pest --coverage --min=80

# Lint (check)
./vendor/bin/phpcs

# Lint (fix)
./vendor/bin/php-cs-fixer fix
```

### Key Conventions
- **Constructor property promotion** - always use it
- **Strict types** - every file needs `declare(strict_types=1)`
- **No magic methods** - be explicit
- **No final classes** - blocks Preferences (extensibility)
- **readonly** - use when appropriate for immutability, not as blanket rule
- **Type declarations** - required on all parameters, returns, properties

### Architecture
- **Everything is a module** - framework, vendor, and app code
- **Three directories**: `vendor/` → `modules/` → `app/` (override priority)
- **DI with Preferences** - replace classes via `#[Preference]`
- **Plugins** - `#[Before]` and `#[After]` only (no around)
- **Events/Observers** - decouple "something happened" from reactions
- **PHP config only** - no XML, YAML, or DSL

### Package Decision Framework

When deciding whether functionality belongs in `marko/core` or a separate package:

| Question | If Yes → | If No → |
|----------|----------|---------|
| Does it have multiple valid implementations? | Separate package (interface/driver split) | Could be core |
| Is it optional functionality not everyone needs? | Separate package | Could be core |
| Is it core to the extensibility story? | In core | Separate package |
| Would virtually every app depend on it anyway? | In core | Separate package |

**Examples:**
- Database drivers → Separate (MySQL vs PostgreSQL are swappable)
- Plugin system → Core (fundamental extensibility, no alternative)
- Event system → Core (fundamental extensibility, no alternative)
- CLI → Separate (global install, thin client, different lifecycle)

### Monorepo Structure
```
packages/
  core/           # Bootstrap, DI, module loader
  blog/           # Blog module (built alongside core)
  routing/        # Route attributes, router
  database/       # Database interfaces
  database-mysql/ # MySQL driver
  ...
demo/             # Development test application (not a package)
```

### Blog Module (Lockstep Development)

The `marko/blog` package is built **in lockstep with core** - we only add blog features when the corresponding core features exist. This ensures:

1. **Real-world validation** - Core features are tested against actual use cases
2. **No premature abstractions** - Blog only uses what's actually available
3. **Living documentation** - Shows how to use each core feature properly

**Current state:** Skeleton package with composer.json and empty src/ directory. Real functionality will be added as core features are built.

### Demo Application

The `demo/` directory contains a minimal bootstrap application for testing framework features during development.

```
demo/
  vendor/         # marko/core, marko/blog (via Composer path repos)
  modules/        # Empty (for manually-installed third-party modules)
  app/
    blog/         # Future customizations of marko/blog
  public/         # Web root (index.php - minimal bootstrap)
```

**Naming convention for app/ modules:**
- `app/blog` - Customizes `marko/blog` (same name indicates override/extension)
- `app/my-feature` - New app-only module (different name = standalone)

**Requirements:**
- Update blog and demo as each core feature is completed
- Demo should show realistic app-level customization, not artificial examples
- Never build blog features that require core features we don't have yet

## Feature Development

For any feature beyond a simple fix or quick change, use the `plan-create` skill to trigger the autonomous development workflow.

Use this workflow for: new features, multi-file changes, anything requiring multiple steps or tests.

Skip for: quick bug fixes, single-line changes, questions, documentation.

**Important:** Every feature implementation must include updates to the `demo/` directory that exercise the new functionality. This serves as both validation that the feature works end-to-end and living documentation of how to use it.

## Documentation

See `.claude/` for detailed documentation:
- `architecture.md` - Master architecture document (modules, DI, plugins, events, routing, CLI, etc.)
- `project-overview.md` - Tech stack, principles, resources
- `code-standards.md` - Coding standards and style guide
- `testing.md` - Pest 4 testing configuration

## Core Principles

1. **Loud errors** - No silent failures, helpful messages
2. **Explicit over implicit** - No magic, everything discoverable
3. **Opinionated, not restrictive** - Guide toward better patterns
4. **True modularity** - Interface/implementation split, clean boundaries
5. **No pseudo-functionality** - Don't build fake features to demonstrate concepts; only build real functionality when core supports it. If there's nothing meaningful to build, build nothing.
