# Task 003: Document engine-sibling pattern in architecture.md

**Status**: pending
**Depends on**: none
**Retry count**: 0

## Description
Add a new section to `.claude/architecture.md` documenting the `marko/{module}-{engine}` engine-sibling pattern as the canonical approach for UI packages that want multi-engine support. Explains the `templates_for` composer-extra key, when to use the pattern (reusable UI packages) vs. when not to (single-application modules), and the trade-offs.

## Context
- File to modify: `.claude/architecture.md`
- Section placement: after the existing "Package Architecture" section, before "Dependency Injection". The new section is conceptually about package structure, so it sits with other package-level architecture documentation.
- New section title: "Engine-Specific Template Siblings"

**Content outline (write in the architecture.md voice — opinionated, instructive, with examples):**

1. **The problem.** UI packages that ship templates must pick a template engine — locking consumers into that engine. Naive solutions (shipping templates for all engines in one package, ship N versions in subdirectories) don't scale.

2. **The pattern.** Split UI packages into:
   - `marko/{module}` — PHP code only (controllers, services, routes). Engine-agnostic.
   - `marko/{module}-{engine}` — templates only, one per supported engine (e.g., `marko/admin-panel-latte`, `marko/admin-panel-twig`)
   - Engine siblings declare `extra.marko.templates_for: marko/{module}` in composer.json
   - Siblings `require` their corresponding view driver
   - Siblings do NOT need Composer `conflict` declarations — both being installed is harmless (the resolver routes to the right templates based on `view.extension`), and Marko's DI-level detection handles any actual interface conflicts at the driver layer

3. **How resolution works.** `ModuleTemplateResolver` searches both the parent module's `resources/views/` AND any module declaring `templates_for: marko/{parent}`. Controllers reference templates via the parent's namespace (`{module}::path`) regardless of which engine is in use — abstraction preserved.

4. **When to use this pattern.** For reusable, shipped UI modules where the maintainer can't predict the consumer's engine. Examples: `marko/admin-panel`, future admin dashboards, form builders, debug bars.

5. **When NOT to use it.** Application-specific modules (`app/blog`, `app/admin-customizations`) — these are written for one team's chosen engine. Hard-dep on the engine, ship templates directly. Multi-engine packaging is overhead with no benefit when there's only one consumer.

6. **Trade-offs.**
   - Cost: each engine sibling is a separate Composer package to maintain; adopting a new core engine requires writing template translations for every existing UI module
   - Benefit: genuine engine choice for consumers; framework principle of "explicit over implicit" upheld
   - Scaling: realistic ceiling is 2-3 engines (mainstream PHP options: Twig, Latte, maybe Blade). Beyond that the maintenance math gets ugly — `CrossEngineTemplateParityTest` enforces this as a real commitment.

7. **The parity test.** `marko/view`'s `CrossEngineTemplateParityTest` mechanically enforces parity. Adopting a new core engine fails the build until templates are written for every existing UI module. Document this so contributors know the bar.

## Requirements (Test Descriptions)
- [ ] `architecture.md contains a section titled Engine-Specific Template Siblings`
- [ ] `the section is placed between Package Architecture and Dependency Injection`
- [ ] `the section explains the marko/{module}-{engine} naming pattern`
- [ ] `the section documents the templates_for composer-extra key`
- [ ] `the section explains when to use the pattern (reusable UI packages)`
- [ ] `the section explains when NOT to use the pattern (application-specific modules)`
- [ ] `the section mentions the CrossEngineTemplateParityTest as the enforcement mechanism`

## Acceptance Criteria
- New section in `.claude/architecture.md` covers all outline points above
- Section follows the voice and formatting of surrounding architecture sections
- Tests assert key passages appear in the document (use `file_get_contents` + `toContain` assertions, mirroring how the codebase verifies architecture/docs content elsewhere)
- Test file: `tests/Documentation/ArchitectureDocTest.php` if a similar pattern exists, otherwise an ad-hoc test verifying the section is present
