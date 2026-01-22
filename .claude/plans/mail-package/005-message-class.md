# Task 005: Message Class

**Status**: completed
**Depends on**: 003, 004
**Retry count**: 0

## Description
Create the Message class as a fluent builder for email composition.

## Context
- Supports to, cc, bcc, from, replyTo (all accept email and optional name)
- Subject and body (html and/or text)
- Attachments and inline embeds
- Custom headers and priority
- Returns $this for fluent chaining
- Static create() factory method

## Requirements (Test Descriptions)
- [ ] `Message create returns new instance`
- [ ] `Message to adds recipient`
- [ ] `Message to accepts multiple recipients`
- [ ] `Message cc adds copy recipient`
- [ ] `Message bcc adds blind copy recipient`
- [ ] `Message from sets sender`
- [ ] `Message replyTo sets reply address`
- [ ] `Message subject sets subject line`
- [ ] `Message html sets HTML body`
- [ ] `Message text sets plain text body`
- [ ] `Message attach adds attachment`
- [ ] `Message embed adds inline attachment`
- [ ] `Message header adds custom header`
- [ ] `Message priority sets message priority`
- [ ] `Message methods return self for chaining`

## Implementation Notes
