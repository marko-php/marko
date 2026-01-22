# Task 015: CLI mail:test Command

**Status**: completed
**Depends on**: 006, 007
**Retry count**: 0

## Description
Create the mail:test CLI command for verifying mail configuration.

## Context
- Command: marko mail:test <email>
- Sends test email to specified address
- Supports --subject option for custom subject
- Displays success/failure with helpful messages

## Requirements (Test Descriptions)
- [ ] `mail:test command requires email argument`
- [ ] `mail:test command sends test email`
- [ ] `mail:test command supports subject option`
- [ ] `mail:test command shows success message`
- [ ] `mail:test command shows failure message on error`

## Implementation Notes
