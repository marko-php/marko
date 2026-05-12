# Task 014: README for marko/page-cache-file

**Status**: completed
**Depends on**: 010, 011, 012
**Retry count**: 0

## Description
Write `packages/page-cache-file/README.md` per the Package README Standards. Implementation package: describe what the driver does (file-based storage with tag indexing), show that it works automatically once installed, document config knobs.

## Context
- Related files:
  - `packages/cache-file/README.md` (template — implementation package README)
  - `.claude/code-standards.md` "Package README Standards" section
- Required sections:
  - Title + One-liner
  - Overview
  - Installation
  - Usage (note: works automatically — just install and `#[Cacheable]` works)
  - Configuration (env vars: `PAGE_CACHE_DRIVER`, `PAGE_CACHE_PATH`, `PAGE_CACHE_TTL`)
  - API Reference (note: implements `PageCacheInterface`; see `marko/page-cache` for usage)

## Requirements (Non-Test Acceptance Checklist)

This task has NO executable tests. The checkboxes below are review-checked acceptance criteria, not Pest test descriptions. The TDD worker should NOT attempt to write tests for them — they are checked manually during review.

- [ ] README exists at `packages/page-cache-file/README.md`
- [ ] All standard sections present
- [ ] Documents storage layout (`pages/`, `tags/`) at a high level so ops know what's on disk
- [ ] Documents env vars
- [ ] References the interface package for full usage examples
- [ ] One-liner states benefit upfront

## Acceptance Criteria
- All checkbox requirements met
- README is concise; cross-references `marko/page-cache` rather than duplicating its content

## Implementation Notes
(Left blank — filled in by programmer during implementation)
