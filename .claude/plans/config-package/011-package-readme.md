# Task 011: Package README

## Status
pending

## Depends On
010

## Description
Create comprehensive README documentation for the config package.

## Requirements
- [ ] Create `packages/config/README.md` following package README standards
- [ ] Include sections:
  - Title + one-liner with practical benefit
  - Overview (2-4 sentences)
  - Installation (`composer require marko/config`)
  - Usage section with:
    - Basic config file example
    - Accessing config via ConfigRepository
    - Type-safe accessor methods
    - Dot notation examples
    - Environment variable integration
    - Scoped configuration for multi-tenant
  - Customization via Preferences
  - API Reference with public method signatures
- [ ] Code examples follow full code standards (multiline params, etc.)
- [ ] Document config file location convention (`config/*.php`)
- [ ] Document merge priority (vendor < modules < app)
- [ ] Document scoped config structure

## Implementation Notes
<!-- Notes added during implementation -->
