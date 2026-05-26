# Task 011: Update skeleton composer.json suggest block

**Status**: pending
**Depends on**: none
**Retry count**: 0

## Description
Add both `marko/view-twig` and `marko/view-latte` to the `marko/skeleton` composer.json `suggest` block so users creating new projects see both engine options. List Twig first (broader recognition in the PHP ecosystem). The skeleton stays driver-agnostic — neither is a hard dependency.

## Context
- Related files:
  - `packages/skeleton/composer.json` (modify — add `suggest` block if missing, or add to existing)
- The skeleton has no `suggest` block today, so the entire block needs to be added
- Format (alphabetical inside suggest is conventional, but here we deliberately order by recommendation — Twig first for visibility):
  ```json
  "suggest": {
      "marko/view-twig": "Twig template engine driver (recommended for broader ecosystem familiarity)",
      "marko/view-latte": "Latte template engine driver (compile-time safety, n:attribute syntax)"
  }
  ```
- Do NOT add either to `require` — keep skeleton engine-agnostic

## Requirements (Test Descriptions)
- [ ] `it lists marko/view-twig in the skeleton composer suggest block`
- [ ] `it lists marko/view-latte in the skeleton composer suggest block`
- [ ] `it does not add marko/view-twig to require`
- [ ] `it does not add marko/view-latte to require`
- [ ] `it keeps the suggest entries valid JSON`

## Acceptance Criteria
- `packages/skeleton/composer.json` contains a `suggest` block with both entries
- Neither view driver appears in `require` or `require-dev`
- Composer can validate the file (`composer validate`)
- Code follows code standards

## Implementation Notes
(Left blank — filled in by programmer during implementation)
