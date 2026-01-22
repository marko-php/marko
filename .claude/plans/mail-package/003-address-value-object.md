# Task 003: Address Value Object

**Status**: pending
**Depends on**: 001
**Retry count**: 0

## Description
Create the Address value object for email addresses with optional display name.

## Context
- Readonly class with email and optional name
- Validates email format on construction using filter_var
- Throws MessageException on invalid email
- toString() formats as "John Doe <john@example.com>" or just "john@example.com"

## Requirements (Test Descriptions)
- [ ] `Address stores email correctly`
- [ ] `Address stores optional name correctly`
- [ ] `Address throws MessageException for invalid email`
- [ ] `Address toString formats with name correctly`
- [ ] `Address toString formats without name correctly`
- [ ] `Address is readonly`

## Implementation Notes
