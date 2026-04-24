# Task 007: Implement attribute parser in codeindexer

**Status**: pending
**Depends on**: 005
**Retry count**: 0

## Description
Implement an `AttributeParser` service that walks a module's `src/` tree, parses every PHP class file via reflection, and extracts all Marko framework attributes: `#[Observer]`, `#[Plugin]`, `#[Before]`, `#[After]`, `#[Preference]`, `#[Command]`, `#[Get/Post/Put/Patch/Delete]`, `#[Middleware]`, `#[DisableRoute]`. Returns a structured symbol table keyed by attribute type.

## Context
- Namespace: `Marko\CodeIndexer\Attributes\AttributeParser`
- Uses `nikic/php-parser` AST traversal (NOT reflection — reflection requires autoloadability, which fails for modules with missing deps, partial installs, or files-only scans). AST parsing lets us read attributes without loading the class.
- Output types: `ObserverEntry`, `PluginEntry`, `PreferenceEntry`, `CommandEntry`, `RouteEntry` (all readonly, defined in task 005)
- Handles class-level AND method-level attributes
- Gracefully skips unparseable files with a structured warning (does not abort the whole scan); files with fatal syntax errors are collected into a diagnostics list on the parser output, never thrown
- Resolves attribute FQCNs by reading the file's `use` statements and namespace declaration, so short names like `#[Observer(...)]` resolve to the correct fully qualified class

## Requirements (Test Descriptions)
- [ ] `it parses class-level Observer attributes with event class reference`
- [ ] `it parses method-level Before attributes with sortOrder and target method`
- [ ] `it parses method-level After attributes with sortOrder`
- [ ] `it parses Plugin attributes and associates methods to target class`
- [ ] `it parses Preference attributes and maps replacement class to replaced class`
- [ ] `it parses Command attributes from class-level declarations`
- [ ] `it parses route attributes Get/Post/Put/Patch/Delete with path and middleware`
- [ ] `it parses DisableRoute attributes on overridden methods`
- [ ] `it skips classes outside the specified module namespace`
- [ ] `it returns empty entries for modules with no attributes`
- [ ] `it resolves short attribute names via file use statements to fully qualified class names`
- [ ] `it does not require target classes to be autoloadable (pure AST traversal, no class loading)`
- [ ] `it records syntax-error files as diagnostics without aborting the scan`

## Acceptance Criteria
- Test fixtures use real attribute classes from marko/core and marko/routing
- Parser handles nested namespaces correctly
- All entries include source file path and line number for goto-def support

## Implementation Notes
(Filled in by programmer during implementation)
