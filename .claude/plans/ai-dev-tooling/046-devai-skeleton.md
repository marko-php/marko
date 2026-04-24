# Task 046: Create marko/devai package skeleton

**Status**: pending
**Depends on**: 030, 039
**Retry count**: 0

## Description
Create the `marko/devai` package skeleton — the installer/orchestrator that wires `marko/mcp` and `marko/lsp` into any supported AI coding agent.

## Context
- Path: `packages/devai/`
- Namespace: `Marko\DevAi\`
- Composer requires: `marko/core`, `marko/mcp`, `marko/lsp`
- Will expose `devai:install` and `devai:update` commands (in later tasks)

## Requirements (Test Descriptions)
- [ ] `it has composer.json with name marko/devai and dependencies on mcp and lsp`
- [ ] `it has src tests/Unit tests/Feature directories with Pest bootstrap`
- [ ] `it autoloads cleanly with composer dump-autoload`
- [ ] `it has module.php with empty bindings (adapters register later)`

## Acceptance Criteria
- Skeleton present, composer autoload works

## Implementation Notes
(Filled in by programmer during implementation)
