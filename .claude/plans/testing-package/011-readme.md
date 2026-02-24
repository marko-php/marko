# Task 011: README.md

**Status**: pending
**Depends on**: 001, 002, 003, 004, 005, 006, 007, 008, 009, 010
**Retry count**: 0

## Description
Create the README.md for `marko/testing` following the Package README Standards. This task depends on all others so the README accurately reflects what was built.

## Context
- Related files:
  - `.claude/code-standards.md` - Package README Standards section
  - `packages/errors/README.md` - example of interface package README (for reference)
  - `packages/errors-simple/README.md` - example of implementation package README (for reference)
  - All source files created in tasks 001-009
- Location: `packages/testing/README.md`

## Requirements (Test Descriptions)
- [ ] `it has a README.md with title and practical one-liner`
- [ ] `it has an overview section explaining the benefit`
- [ ] `it has an installation section with composer command`
- [ ] `it has a usage section with code examples for each fake`
- [ ] `it has an API reference section listing all public methods`
- [ ] `it follows the Package README Standards from code-standards.md`

## Acceptance Criteria
- README follows Package README Standards exactly
- Title + one-liner states practical benefit
- Usage section leads with common case, then details
- Code examples follow full code standards (multiline params, trailing commas)
- API reference uses single-line signatures (acceptable per standards)
- No marketing speak, no verbose paragraphs
- Covers all 9 fake classes and Pest expectations

## Implementation Notes
### Structure
1. **Title + One-Liner**: "Testing utilities for the Marko framework - reusable fakes, assertions, and Pest integration that eliminate test boilerplate."
2. **Overview**: 2-3 sentences on what the package provides and why
3. **Installation**: `composer require marko/testing --dev`
4. **Usage**: Code examples for each fake class showing:
   - Instantiation
   - Injection into code under test
   - Assertion methods
5. **Pest Expectations**: Show `expect()->toHaveDispatched()` etc.
6. **API Reference**: All public methods grouped by class

### Key messaging
- "Drop-in replacements for ad-hoc test doubles"
- "Each fake captures side effects and provides assertion methods"
- "No traits, no magic - just explicit objects you instantiate and inject"
