# Task 003: Update Architecture Docs

**Status**: completed
**Depends on**: 001
**Retry count**: 0

## Description
Update the architecture document and module.php doc comment to reflect that boot callbacks now support auto-injection of any registered dependency, not just `ContainerInterface`.

## Context
- Related files: `.claude/architecture.md` (search for `'boot' => function ($container)` in the environment-specific bindings section), `packages/core/src/Module/ModuleManifest.php` (boot param doc comment)
- The architecture doc's boot callback examples currently show `function ($container) { ... }` -- update to show typed `ContainerInterface $container` and explain that any registered dependency can be type-hinted
- Keep backward-compatible example too, showing both styles are valid
- Also update README boot callback examples in these packages (all currently show untyped `$container`):
  - `packages/core/README.md`
  - `packages/scheduler/README.md`
  - `packages/cache-redis/README.md`
  - `packages/mail-log/README.md`
  - Note: `packages/health/README.md` already shows typed params -- use this as the model

## Requirements (Test Descriptions)
- [ ] `architecture.md boot callback examples show auto-injected dependencies`
- [ ] `ModuleManifest boot property docblock reflects auto-injection`
- [ ] `README boot callback examples use typed parameters`

## Acceptance Criteria
- Architecture doc examples updated to show auto-injected boot callbacks
- ModuleManifest docblock updated
- Both old and new style shown as valid in docs
- README files in core, scheduler, cache-redis, and mail-log updated to show typed boot callback parameters
