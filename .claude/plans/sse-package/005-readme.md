# Task 005: Package README.md

**Status**: completed
**Depends on**: 001, 002, 003, 004
**Retry count**: 0

## Description
Create the `README.md` for the `marko/sse` package following the Package README Standards. The README documents the package's purpose, installation, usage (with controller example), and API reference.

## Context
- Related files:
  - `packages/sse/README.md` — create this file
  - `.claude/code-standards.md` — "Package README Standards" section defines required structure
  - `packages/cors/README.md` or `packages/authentication/README.md` — reference for README format
- Required sections:
  1. **Title + One-Liner** — what it does + practical benefit
  2. **Overview** — 2-4 sentences expanding on the benefit
  3. **Installation** — `composer require marko/sse`
  4. **Usage** — controller example showing SseEvent, SseStream, StreamingResponse together
  5. **API Reference** — public signatures for SseEvent, SseStream, StreamingResponse
- Guidelines:
  - Lead with practical benefit
  - Keep prose minimal, let code speak
  - Code examples must follow full code standards (strict types, constructor promotion, type declarations)
  - Include client-side EventSource example for reference
  - Do NOT use emojis

## Requirements (Test Descriptions)
This task has no automated tests — it creates documentation only.

## Acceptance Criteria
- README.md exists at `packages/sse/README.md`
- Contains all required sections per Package README Standards
- Usage section shows a realistic controller example
- API Reference covers all three public classes
- Code examples follow Marko code standards
- No emojis

## Implementation Notes
(Left blank - filled in by programmer during implementation)
