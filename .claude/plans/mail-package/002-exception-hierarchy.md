# Task 002: MailException Hierarchy

**Status**: completed
**Depends on**: 001
**Retry count**: 0

## Description
Create the mail exception hierarchy: MailException (base), TransportException, MessageException.

## Context
- MailException extends base Marko exception
- TransportException for delivery failures (connection, TLS, auth)
- MessageException for message building errors (invalid email, missing attachments)
- All exceptions should have static factory methods with context and suggestions

## Requirements (Test Descriptions)
- [ ] `MailException has noDriverInstalled factory method`
- [ ] `MailException has configFileNotFound factory method`
- [ ] `TransportException has connectionFailed factory method`
- [ ] `TransportException has tlsFailed factory method`
- [ ] `TransportException has authenticationFailed factory method`
- [ ] `TransportException has unexpectedResponse factory method`
- [ ] `MessageException has invalidEmailAddress factory method`
- [ ] `MessageException has attachmentNotFound factory method`
- [ ] `MessageException has noRecipients factory method`

## Implementation Notes
