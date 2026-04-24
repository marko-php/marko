# Task 038: Implement LSP inverse-index code-lenses

**Status**: pending
**Depends on**: 033
**Retry count**: 0

## Description
Implement code lenses that show inverse-index information above event classes and target classes: "N observers listen to this event" above an event class; "M plugins intercept this class" above a target class. Clicking the lens reveals the list with goto-definition links.

## Context
- Namespace: `Marko\Lsp\Features\CodeLensFeature`
- Data source: `IndexCache` inverse queries
- Lens providers registered via `textDocument/codeLens`

## Requirements (Test Descriptions)
- [ ] `it publishes a code lens above every event class indicating observer count`
- [ ] `it publishes a code lens above every target class indicating plugin count`
- [ ] `it shows zero count lenses when nothing listens or intercepts`
- [ ] `it resolves lens click to a list of observer or plugin locations`
- [ ] `it refreshes lenses when the workspace re-indexes`

## Acceptance Criteria
- Fixtures include event + observer pairs and plugin + target pairs
- Lens positions anchored to class declaration line

## Implementation Notes
(Filled in by programmer during implementation)
