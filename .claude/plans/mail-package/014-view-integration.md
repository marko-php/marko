# Task 014: Optional View Integration

**Status**: pending
**Depends on**: 006
**Retry count**: 0

## Description
Add optional view integration for email templates when marko/view is installed.

## Context
- ViewMailer wraps MailerInterface with template support
- Checks if ViewInterface is available at runtime
- Provides view() method on Message for template rendering
- Gracefully degrades when marko/view not installed

## Requirements (Test Descriptions)
- [ ] `Message view method sets template`
- [ ] `Message with method sets template data`
- [ ] `ViewMailer renders template when ViewInterface available`
- [ ] `ViewMailer works without view package installed`

## Implementation Notes
