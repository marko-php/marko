# Task 021: Media Entity and Core Interfaces

**Status**: pending
**Depends on**: none
**Retry count**: 0

## Description
Create the marko/media package scaffolding with the Media entity, core interfaces (MediaManagerInterface, ImageProcessorInterface, AttachmentInterface, UrlGeneratorInterface), and exceptions. This establishes the foundation for media management.

## Context
- New package at `packages/media/`
- Namespace: `Marko\Media`
- Depends on: marko/core, marko/filesystem, marko/database, marko/validation, marko/config
- Study `packages/blog/src/Entity/Post.php` for entity pattern with #[Table], #[Column] attributes
- Study `packages/filesystem/src/Contracts/FilesystemInterface.php` for storage operations
- Study `packages/filesystem/src/Config/FilesystemConfig.php` for disk-based config pattern
- Media entity: id, filename, original_filename, mime_type, size, disk, path, metadata (JSON), created_at, updated_at
- ImageProcessorInterface defines resize/crop/convert — implemented by media-gd and media-imagick drivers
- AttachmentInterface: associate media with any entity via attachable_type + attachable_id
- UrlGeneratorInterface: generate public URLs for media files

## Requirements (Test Descriptions)
- [ ] `it defines Media entity with table and column attributes for all media properties`
- [ ] `it defines MediaManagerInterface with upload, retrieve, delete, and exists methods`
- [ ] `it defines ImageProcessorInterface with resize, crop, and convert methods`
- [ ] `it defines AttachmentInterface for associating media with entities`
- [ ] `it defines UrlGeneratorInterface for generating public URLs for media`
- [ ] `it throws MediaException with context and suggestion for media operation failures`
- [ ] `it creates valid package scaffolding with composer.json, module.php, and config`

## Acceptance Criteria
- All requirements have passing tests
- Entity in `src/Entity/Media.php`
- Interfaces in `src/Contracts/`
- Exceptions in `src/Exceptions/` (MediaException, UploadException, FileNotFoundException)
- Value objects in `src/Value/` (UploadedFile, ImageDimensions)
- Config at `config/media.php` with disk, max_file_size, allowed_mime_types, allowed_extensions, url_prefix
- Code follows code standards

## Implementation Notes
