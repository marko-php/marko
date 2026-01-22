# Task 006: MailerInterface Contract

**Status**: pending
**Depends on**: 005
**Retry count**: 0

## Description
Create the MailerInterface contract that mail drivers must implement.

## Context
- Primary mail sending contract
- send(Message) method throws TransportException on failure
- sendRaw(to, raw) for pre-formatted messages
- Both return bool indicating success

## Requirements (Test Descriptions)
- [ ] `MailerInterface defines send method`
- [ ] `MailerInterface defines sendRaw method`

## Implementation Notes
