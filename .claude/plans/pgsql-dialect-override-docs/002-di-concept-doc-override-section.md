# Task 002: DI Concept Doc — Cross-Module Binding Override Section

**Status**: completed
**Depends on**: none
**Retry count**: 0

## Description
Add a new section to `docs/src/content/docs/concepts/dependency-injection.md` titled "Overriding another module's bindings" that explains the boot-callback pattern as the supported way for one module (especially a vendor-installed sibling driver) to rebind an interface that another vendor module has already bound. This is a generic architectural concept; the database/dialect use case is one application of it.

## Context
- **Why concept doc and not a package doc:** Boot-callback rebinding applies to any sibling driver scenario — cache variants, queue variants, mail variants, etc. The concept belongs at the DI level so any reader hitting "how do I override a vendor module's binding" finds it.
- **Current state of the DI doc (verified):** `concepts/dependency-injection.md` does NOT currently mention `boot` callbacks at all. Its sections are: Constructor Injection, Bindings, Singletons, Resolution Order, Auto-Resolution Example, What Won't Auto-Resolve, Next Steps. The new section therefore must introduce `boot` enough to explain the override, OR cross-link to `packages/core.md` for the full boot mechanics. The existing boot+env-conditional pattern lives in `packages/core.md` under "Environment-Specific Bindings" (line 135) — reuse that vocabulary and reference it; do not re-duplicate.
- **Mechanism to explain (concise, no over-elaboration):**
  - Static `bindings` go through `BindingRegistry` which throws `BindingConflictException` if two same-priority modules bind the same interface — this protects against accidental conflicts.
  - `boot` callbacks run after all static bindings are registered and call `$container->bind()` directly, which **intentionally** allows rebinding. This is the right tool when one module *deliberately* overrides another.
  - Load order: boot callbacks honor module `sequence` (after/before) and `require` (composer dependency), so a variant module that `require`s its parent automatically boots after it. Use `sequence.after` only when there is no `require` relationship.
  - **Singleton caveat:** If an interface is also declared in the parent module's `singletons` key and is resolved before the variant's boot runs, the cached instance will be returned despite the rebind. The override pattern is safe for the 4 pgsql dialect interfaces because none of them are singletons. Mention this gotcha briefly so readers applying the pattern elsewhere don't trip on it.
- **Show, don't tell:** Include a small worked example using the database-pgsql variant case so readers see the shape. Keep it short — the database guide will have the full worked example; this is just illustrative.
- **Cross-link:** Add a short pointer at the end to `packages/database.md` for the database-specific application of this pattern.
- **Anchor (locked):** The H2 must be exactly `## Overriding another module's bindings`. github-slugger (used by Astro/Starlight) produces the anchor `overriding-another-modules-bindings`. Task 003 hardcodes this anchor — do not change the title.

## Requirements (Test Descriptions)
*Docs-only task — "tests" are content-quality assertions verified by review.*

- [ ] `the new section is titled "Overriding another module's bindings" (exact title — drives the anchor)`
- [ ] `the section explains why static bindings conflict and why boot does not`
- [ ] `the section includes a minimal code example using ContainerInterface and $container->bind()`
- [ ] `the section notes that module sequence (after/before) and composer require control override timing when multiple modules touch the same binding`
- [ ] `the section warns that singleton bindings cache on first resolve, so the override only works reliably if the parent's binding is not declared as a singleton (or is not resolved before the override boot runs)`
- [ ] `the section cross-links to packages/database.md for the database-specific example`

## Acceptance Criteria
- New section added to `docs/src/content/docs/concepts/dependency-injection.md`. Since the DI doc has no existing boot-callback section, the natural placement is after the "Singletons" section and before "Resolution Order", OR appended near the end before "Next Steps". The implementer should pick whichever flows better with the surrounding narrative.
- `npm --prefix docs run build` passes with no new warnings.
- Code example uses correct namespaces. Note: the project uses `Marko\Core\Container\ContainerInterface` for boot-callback injection (see `packages/core.md` line 145 — boot closures type-hint `Container`/`ContainerInterface` from Marko, not PSR directly). Match that.
- Tone matches surrounding doc voice (declarative, no marketing language).

## Implementation Notes
(Left blank — filled in by programmer during implementation)
