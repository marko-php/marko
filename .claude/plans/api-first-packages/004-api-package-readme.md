# Task 004: API Package README

**Status**: pending
**Depends on**: 003
**Retry count**: 0

## Description
Create the README.md for the marko/api package following the project's Package README Standards.

## Context
- Package: `packages/api/`
- Follow README format from `.claude/code-standards.md` "Package README Standards" section
- Sections: Title + One-Liner, Overview, Installation, Usage, Customization, API Reference
- Lead with practical benefit, keep prose minimal, let code speak
- Study existing READMEs like `packages/cache/README.md` or `packages/authentication/README.md` for tone and format
- Show JsonResource usage, ResourceCollection with pagination, conditional fields

## Requirements (Test Descriptions)
- [ ] `README.md exists with title, overview, installation, usage, and API reference sections`
- [ ] `README.md shows JsonResource usage example with entity wrapping`
- [ ] `README.md shows ResourceCollection with pagination example`
- [ ] `README.md documents conditional fields and MissingValue usage`

## Acceptance Criteria
- README.md follows Package README Standards exactly
- Code examples use multiline parameter signatures per code standards
- API Reference lists all public interfaces and methods
- No pseudo-functionality — examples show real-world usage patterns

## Implementation Notes
