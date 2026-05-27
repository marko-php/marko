# Task 002: Enhance ModuleTemplateResolver to honor templates_for metadata

**Status**: pending
**Depends on**: none
**Retry count**: 0

## Description
Extend `Marko\View\ModuleTemplateResolver::getSearchedPaths()` to ALSO search modules declaring `extra.marko.templates_for` in their composer.json. When resolving `admin-panel::path`, the resolver currently finds only modules whose basename equals `admin-panel`. After this change, it also finds modules declaring `templates_for: marko/admin-panel`. This is the load-bearing change that enables engine-sibling packages to provide templates for their parent module.

## Context
- Files to modify:
  - `packages/view/src/ModuleTemplateResolver.php` — add second match path in `getSearchedPaths()`
  - `packages/view/tests/ModuleTemplateResolverTest.php` — extend tests
- **Cross-package change required (marko/core):** `ModuleManifest` currently does NOT expose `extra` from composer.json. The resolver needs this metadata. This task must extend the manifest pipeline:
  - `packages/core/src/Module/ModuleManifest.php` — add `public array $extra = []` constructor-promoted property (typed `array<string, mixed>`)
  - `packages/core/src/Module/ManifestParser.php::parse()` — pass `extra: $composerData['extra'] ?? []` into the manifest
  - `packages/core/src/Module/ModuleDiscovery.php::withPathAndSource()` — include `extra: $manifest->extra` when reconstructing the manifest (otherwise the field is dropped after discovery)
  - `packages/core/tests/Unit/Module/ManifestParserTest.php` (if exists) and `packages/core/tests/Unit/Module/ModuleDiscoveryTest.php` — extend to cover the new `extra` field
- `ModuleRepositoryInterface::all()` returns `ModuleManifest[]` — after the change above, callers can read `$module->extra` directly.

**Resolver logic shape (after change):**
```php
public function getSearchedPaths(string $template): array
{
    [$moduleName, $templatePath] = $this->parseTemplate($template);
    $extension = $this->viewConfig->extension();

    $paths = [];

    foreach ($this->moduleRepository->all() as $module) {
        // Existing match: module basename equals requested name
        if ($this->matchesModuleName($module->name, $moduleName)) {
            $paths[] = $module->path . '/resources/views/' . $templatePath . $extension;
            continue;
        }

        // New match: module declares templates_for for the requested module
        if ($this->matchesTemplatesFor($module, $moduleName)) {
            $paths[] = $module->path . '/resources/views/' . $templatePath . $extension;
        }
    }

    return $paths;
}

private function matchesTemplatesFor(ModuleManifest $module, string $shortModuleName): bool
{
    $templatesFor = $module->extra['marko']['templates_for'] ?? null;
    if (!is_string($templatesFor)) {
        return false;
    }

    // templates_for is a full package name like "marko/admin-panel" — match by basename
    return $this->matchesModuleName($templatesFor, $shortModuleName);
}
```

**Ordering matters:** The parent module is checked first (`matchesModuleName`). If found, its path is added first. This means if `marko/admin-panel` itself ever ships templates again in the future, those take priority over sibling-provider templates — matching Marko's "later directories win" override semantics for templates within the same family.

## Requirements (Test Descriptions)
- [ ] `ModuleManifest exposes the extra array from composer.json`
- [ ] `ManifestParser populates the extra field from composer.json data`
- [ ] `ModuleDiscovery preserves the extra field when setting path and source`
- [ ] `ModuleManifest defaults extra to an empty array when not specified`
- [ ] `it resolves a template via the parent module name match (existing behavior preserved)`
- [ ] `it resolves a template via a templates_for declaration on a sibling module`
- [ ] `it includes both the parent and the sibling paths in getSearchedPaths when both exist`
- [ ] `it puts the parent path before the sibling path in the search order`
- [ ] `it ignores modules without a templates_for declaration when looking for sibling providers`
- [ ] `it does not throw when a module's extra.marko key is missing entirely`
- [ ] `it does not throw when templates_for is present but not a string`
- [ ] `it matches templates_for by basename (marko/admin-panel matches admin-panel::path)`

## Acceptance Criteria
- `ModuleManifest::$extra` is a new public readonly array property; defaults to `[]`
- `ManifestParser` populates `extra` from composer.json
- `ModuleDiscovery::withPathAndSource()` preserves `extra` when reconstructing manifests
- `ModuleTemplateResolver` finds templates from both parent modules and `templates_for` siblings
- Existing template resolution behavior preserved — all existing tests pass without modification (including the existing `ModuleManifest` constructor call sites in tests, since the new `extra` parameter is defaulted)
- Module metadata (specifically `extra.marko.templates_for`) is accessible from the resolver via the module repository
- New tests cover the templates_for resolution path
- Code follows code standards (strict_types, typed params/returns, `@throws` on methods that throw, readonly class preserved)
- No final classes; `ModuleManifest` remains readonly
