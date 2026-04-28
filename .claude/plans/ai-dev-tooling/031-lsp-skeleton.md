# Task 031: Create marko/lsp package skeleton

**Status**: pending
**Depends on**: 011
**Retry count**: 0

## Description
Create the `marko/lsp` package skeleton. Pure PHP Language Server Protocol implementation specific to Marko framework semantics. Provides completion, goto-definition, and diagnostics for config keys, template names, translation keys, attribute parameters, and inverse-index code lenses.

## Context
- Path: `packages/lsp/`
- Namespace: `Marko\Lsp\`
- Composer requires: `marko/core`, `marko/codeindexer`
- Protocol: LSP 3.17 (or latest stable at implementation time)

## Requirements (Test Descriptions)
- [ ] `it has composer.json with name marko/lsp and dependency on codeindexer`
- [ ] `it has src tests/Unit tests/Feature directories with Pest bootstrap`
- [ ] `it autoloads cleanly with composer dump-autoload`
- [ ] `it has module.php with empty bindings`

## Acceptance Criteria
- Skeleton present, composer autoload works
- Placeholder tests run

## Implementation Notes
(Filled in by programmer during implementation)
