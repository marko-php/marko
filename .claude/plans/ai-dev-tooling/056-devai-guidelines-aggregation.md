# Task 056: Implement guidelines aggregation convention

**Status**: pending
**Depends on**: 047
**Retry count**: 0

## Description
Implement `GuidelinesAggregator` that scans every installed `marko/*` package (and third-party packages) for `resources/ai/guidelines.md` files and aggregates them with the core Marko guidelines. Matches Laravel Boost's per-package guidelines convention.

## Context
- Namespace: `Marko\DevAi\Guidelines\GuidelinesAggregator`
- Discovery: ModuleWalker (reused from codeindexer) finds all modules; aggregator reads `resources/ai/guidelines.md` per module
- Sections from each package tagged with package name for attribution
- Core framework guidelines live in `packages/devai/resources/ai/guidelines/core.md`

## Requirements (Test Descriptions)
- [ ] `it discovers resources/ai/guidelines.md across every installed module`
- [ ] `it aggregates content preserving source package attribution`
- [ ] `it merges sections with consistent heading hierarchy`
- [ ] `it includes Marko core framework guidelines from devai's own resources`
- [ ] `it returns empty sections when no package contributes guidelines`
- [ ] `it produces deterministic ordering across repeated runs`

## Acceptance Criteria
- Fixtures include multiple contributor packages
- Ordering is alphabetical by package name with core first

## Implementation Notes
(Filled in by programmer during implementation)
