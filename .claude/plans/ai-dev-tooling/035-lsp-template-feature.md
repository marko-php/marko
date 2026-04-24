# Task 035: Implement LSP template name completion + resolution + diagnostics

**Status**: pending
**Depends on**: 033
**Retry count**: 0

## Description
Implement LSP features for template references: completion inside `$view->render('|')` string literals, goto-definition jumping to the `.latte` (or configured extension) file, and diagnostics for missing templates.

## Context
- Namespace: `Marko\Lsp\Features\TemplateFeature`
- Detection: AST-aware — `ViewInterface::render`, `::renderToString`, other configured methods
- Data source: `IndexCache::getTemplates`
- Syntax: `'module::template/path'`

## Requirements (Test Descriptions)
- [ ] `it offers completion for module::template names inside render`
- [ ] `it filters by partial module name and partial template path`
- [ ] `it resolves goto-definition to the template absolute path`
- [ ] `it publishes a diagnostic when the referenced template does not exist`
- [ ] `it suggests the closest known template name in the diagnostic code action`
- [ ] `it supports plain template names without module prefix`

## Acceptance Criteria
- Fixtures include multi-module templates and override scenarios
- Diagnostics are scoped to the current file only

## Implementation Notes
(Filled in by programmer during implementation)
