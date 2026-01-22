# Task 001: Package Scaffolding

**Status**: completed
**Depends on**: -
**Retry count**: 0

## Description
Create composer.json files for both marko/mail (interface package) and marko/mail-smtp (SMTP driver).

## Context
- marko/mail is the interface package with Message builder, Address, Attachment, and MailerInterface
- marko/mail-smtp is the SMTP implementation driver
- Both packages need proper namespace autoloading

## Requirements (Test Descriptions)
- [ ] `mail composer.json exists with correct name`
- [ ] `mail composer.json has proper autoload configuration`
- [ ] `mail-smtp composer.json exists with correct name`
- [ ] `mail-smtp composer.json depends on marko/mail`
- [ ] `mail-smtp composer.json has proper autoload configuration`

## Implementation Notes
