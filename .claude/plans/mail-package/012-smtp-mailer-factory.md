# Task 012: SmtpMailerFactory

**Status**: completed
**Depends on**: 009, 011
**Retry count**: 0

## Description
Create the SmtpMailerFactory for creating configured SmtpMailer instances.

## Context
- Creates SmtpMailer with proper configuration
- Injects SmtpConfig and SmtpTransport
- Used by module.php for lazy instantiation

## Requirements (Test Descriptions)
- [ ] `SmtpMailerFactory creates SmtpMailer instance`
- [ ] `SmtpMailerFactory injects configuration`
- [ ] `SmtpMailerFactory returns MailerInterface`

## Implementation Notes
