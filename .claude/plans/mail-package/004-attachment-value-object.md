# Task 004: Attachment Value Object

**Status**: pending
**Depends on**: 001
**Retry count**: 0

## Description
Create the Attachment value object for file attachments with support for file paths, raw content, and inline images.

## Context
- Readonly class with properties for content, name, mime type, and inline status
- Static factory methods: fromPath, fromContent, inline
- fromPath reads file content
- fromContent accepts raw content
- inline sets content ID for embedded images (cid:)
- Throws MessageException when file not found

## Requirements (Test Descriptions)
- [ ] `Attachment fromPath reads file content`
- [ ] `Attachment fromPath uses filename as default name`
- [ ] `Attachment fromPath throws MessageException for missing file`
- [ ] `Attachment fromPath detects mime type`
- [ ] `Attachment fromContent stores raw content`
- [ ] `Attachment inline sets content ID`
- [ ] `Attachment is readonly`

## Implementation Notes
