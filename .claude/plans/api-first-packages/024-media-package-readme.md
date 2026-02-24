# Task 024: Media Package README

**Status**: pending
**Depends on**: 023
**Retry count**: 0

## Description
Create the README.md for the marko/media package following the project's Package README Standards.

## Context
- Package: `packages/media/`
- Follow README format from `.claude/code-standards.md` "Package README Standards" section
- Show file uploads, media retrieval, URL generation, attachments, and image processing driver setup
- Study existing READMEs for tone and format

## Requirements (Test Descriptions)
- [ ] `README.md exists with title, overview, installation, usage, customization, and API reference sections`
- [ ] `README.md shows file upload and media entity creation example`
- [ ] `README.md shows URL generation and attachment association examples`
- [ ] `README.md documents image processing driver installation (media-gd, media-imagick)`

## Acceptance Criteria
- README.md follows Package README Standards exactly
- Code examples use multiline parameter signatures per code standards
- Configuration section documents all media.php options
- Notes about filesystem driver choice for storage backend
- API Reference lists MediaManagerInterface, UrlGeneratorInterface, ImageProcessorInterface

## Implementation Notes
