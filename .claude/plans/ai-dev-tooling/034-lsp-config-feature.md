# Task 034: Implement LSP config key completion + goto + diagnostics

**Status**: pending
**Depends on**: 033
**Retry count**: 0

## Description
Implement LSP features for config keys: completion inside `$config->get*('|')` string literals, goto-definition jumping to the config file where the key is declared, and diagnostics flagging unknown keys.

## Context
- Namespace: `Marko\Lsp\Features\ConfigKeyFeature`
- Detection: PHP AST-aware — recognizes calls to `ConfigRepositoryInterface::get`, `::getString`, `::getInt`, `::getBool`, `::getFloat`, `::getArray`
- Data source: `IndexCache::getConfigKeys`

## Requirements (Test Descriptions)
- [ ] `it offers completion for config keys inside getString string literal`
- [ ] `it filters completion items by dot-prefix typed so far`
- [ ] `it includes documentation and default value in completion item`
- [ ] `it resolves goto-definition to the config file and line`
- [ ] `it publishes a diagnostic when a config key literal does not exist in the index`
- [ ] `it suggests closest-match keys in the diagnostic code action`
- [ ] `it handles scoped config cascade for multi-tenant keys`

## Acceptance Criteria
- Feature tests use fixture documents representing real PHP files
- Completion stable across re-triggers

## Implementation Notes
(Filled in by programmer during implementation)
