# Task 023: URL Generation and Attachments

**Status**: pending
**Depends on**: 022
**Retry count**: 0

## Description
Implement URL generation for media files and the attachment system for associating media with other entities.

## Context
- Package: `packages/media/`
- Study `packages/filesystem/src/Config/FilesystemConfig.php` for disk URL configuration
- Study `packages/blog/src/Entity/` for entity relationship patterns
- UrlGenerator creates public URLs based on disk config and media path
- For local disk: url_prefix + path (e.g., /storage/2026/02/image.jpg)
- For S3: bucket URL + path
- Attachment: polymorphic association via attachable_type (entity class) + attachable_id
- AttachmentRepository handles attach/detach/findByEntity operations

## Requirements (Test Descriptions)
- [ ] `it generates public URL for media with configurable prefix`
- [ ] `it generates URL based on the media disk configuration`
- [ ] `it attaches media to an entity via attachable_type and attachable_id`
- [ ] `it retrieves all media attached to a given entity`
- [ ] `it detaches media from an entity`

## Acceptance Criteria
- All requirements have passing tests
- UrlGenerator in `src/Service/UrlGenerator.php`
- Attachment entity in `src/Entity/Attachment.php` (if needed) or attachment methods on MediaManager
- URL generation is disk-aware (reads URL config from filesystem disk settings)
- Polymorphic attachments work with any entity class
- Code follows code standards

## Implementation Notes
