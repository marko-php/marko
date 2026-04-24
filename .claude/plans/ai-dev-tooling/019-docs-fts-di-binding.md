# Task 019: Wire docs-fts DI binding

**Status**: pending
**Depends on**: 018
**Retry count**: 0

## Description
Wire `FtsSearch` as the DI implementation of `DocsSearchInterface` in the package's `module.php`. Ensure the container resolves the driver correctly and that installing just `marko/docs` (without any driver) produces a loud error directing the user to install a driver.

## Context
- File: `packages/docs-fts/module.php`
- Singleton binding (driver is stateless but heavy — caches SQLite handle)
- Autowiring must resolve through `MarkdownRepository` for content retrieval

## Requirements (Test Descriptions)
- [ ] `it registers DocsSearchInterface singleton binding to FtsSearch in module.php`
- [ ] `it resolves to FtsSearch from the Marko container when docs-fts is installed`
- [ ] `it throws BindingException when only marko/docs is installed without any driver`
- [ ] `it exposes the underlying driver name via DocsSearchInterface::driverName`

## Acceptance Criteria
- Integration test boots a Marko app with docs-fts and resolves the interface
- Error message on missing driver is helpful (message + context + suggestion)

## Implementation Notes
(Filled in by programmer during implementation)
