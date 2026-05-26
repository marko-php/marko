# Task 015: README per Package README Standards (Final Task)

**Status**: pending
**Depends on**: 002, 011, 012, 013, 014
**Retry count**: 0

## Description
Replace the placeholder `README.md` (from task 002) with the final slim-pointer README per the Package README Standards in `.claude/code-standards.md`. Title, one-liner, brief overview, install command, a single short usage example, and a link to the full docs page (which now exists from task 014).

## Context
- **File location:** `packages/database-readwrite/README.md`
- **Standards reference:** `.claude/code-standards.md` → "Package README Standards" section. READMEs are slim pointers, not duplicate documentation.
- **Required sections (per the standards):**
  - Title + one-liner: what it does + practical benefit
  - Overview: 2-4 sentences expanding on the benefit
  - Installation: `composer require marko/database-readwrite`
  - Usage: the common case — show installing the package + a minimal config snippet, then state "all your existing app code works unchanged"
  - Customization (brief): mention the `ReplicaSelectorInterface` extension seam in 1-2 sentences
  - API Reference (brief): point to the full docs page; do not duplicate every signature
- **Final link:** Full docs link at `https://marko.build/docs/packages/database-readwrite/`.
- **Sibling README to mirror voice and length:** Read `packages/database-pgsql/README.md` and `packages/queue-database/README.md` for the slim-pointer pattern. The readwrite README should be similar length (under 100 lines).
- **Why this task depends on 014:** The README cross-links to the docs page; the link target must exist.

## Requirements (Test Descriptions)
*Docs-only task — assertions are content-quality, verified by review.*

- [ ] `the README has a clear title and one-liner stating what the package does and its benefit`
- [ ] `the README has an Installation section with the correct composer require command`
- [ ] `the README has a Usage section showing the common case (config snippet + app code unchanged)`
- [ ] `the README mentions the ReplicaSelectorInterface customization seam briefly`
- [ ] `the README links to the full docs page at https://marko.build/docs/packages/database-readwrite/`
- [ ] `the README is a slim pointer (no duplicated full docs content)`

## Acceptance Criteria
- README is under ~100 lines.
- All required sections present.
- Tone matches sibling READMEs.
- All code blocks are valid PHP / valid config.
- No broken markdown, no markdown-lint warnings.

## Implementation Notes
(Left blank — filled in by programmer during implementation)
