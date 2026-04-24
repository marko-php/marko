# Task 036: Implement LSP translation key completion + goto

**Status**: pending
**Depends on**: 033
**Retry count**: 0

## Description
Implement LSP features for translation keys: completion inside `$translator->get('|')` and `$translator->choice('|', ...)` string literals, goto-definition jumping to the translation PHP file and line, and diagnostics for unknown keys in the default locale.

## Context
- Namespace: `Marko\Lsp\Features\TranslationFeature`
- Detection: AST-aware — `TranslatorInterface::get`, `::choice`, `::has`
- Data source: `IndexCache::getTranslationKeys`
- Key format: plain dot `group.key` or namespaced `namespace::group.key`

## Requirements (Test Descriptions)
- [ ] `it offers completion for translation keys inside get`
- [ ] `it handles namespaced translation keys with double-colon syntax`
- [ ] `it resolves goto-definition to the translation file and line`
- [ ] `it publishes a diagnostic when a translation key is missing from the default locale`
- [ ] `it suggests closest-match keys in the diagnostic code action`

## Acceptance Criteria
- Fixtures include multi-locale translations and namespaced modules
- Missing-key detection uses the configured default locale

## Implementation Notes
(Filled in by programmer during implementation)
