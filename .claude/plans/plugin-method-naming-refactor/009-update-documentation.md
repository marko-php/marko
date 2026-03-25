# Task 009: Update Documentation

**Status**: completed
**Depends on**: 007, 008
**Retry count**: 0

## Description
Update the plugin documentation page and the architecture document to reflect the new naming convention. Include examples of both standard usage and the `method:` parameter escape hatch (with a completely different function name). Update the architecture doc's Plugin System section.

## Context
- Related files:
  - `docs/src/content/docs/concepts/plugins.md` — full rewrite of plugin docs
  - `.claude/architecture.md` — update Plugin System section (starts at line 644)
  - `.claude/testing.md` — update the "Reflection-Invoked Test Fixtures" section (line 564-581) which shows old `beforeXxx`/`afterXxx` naming in the example

## Requirements (Test Descriptions)
- [ ] `it documents standard plugin naming where method name matches target`
- [ ] `it documents method param escape hatch with completely different function name`
- [ ] `it documents sort order usage with method param`
- [ ] `it documents before plugin short-circuit behavior`
- [ ] `it documents after plugin result modification`
- [ ] `it updates architecture doc plugin discovery description`

## Acceptance Criteria
- Docs page shows new convention as primary with clear examples
- `method:` param documented with example using a completely different function name (e.g., `validateInput` targeting `save`)
- Architecture doc updated to match
- Testing doc fixture example updated
- Migration note for existing plugins

## Implementation Notes
The docs page should be restructured to:
1. Show the standard (intuitive) convention first as the primary way
2. Show `method:` param as "Advanced: Explicit Method Targeting" section
3. Include a complete example with `method:` param where the function name is completely unrelated to the target (e.g., `auditPaymentCompliance` targeting `processPayment`)
4. Note that using `method:` param allows both before and after hooks on the same target method in one plugin class
