# Task 022: MediaManager Implementation

**Status**: pending
**Depends on**: 021
**Retry count**: 0

## Description
Implement the MediaManager service that handles file uploads, storage via filesystem interfaces, validation, and CRUD operations for media entities.

## Context
- Package: `packages/media/`
- Study `packages/filesystem/src/Contracts/FilesystemInterface.php` for write/read/delete operations
- Study `packages/filesystem/src/Contracts/FilesystemManagerInterface.php` for disk selection
- Study `packages/validation/src/Contracts/ValidatorInterface.php` for validation integration
- MediaManager delegates storage to FilesystemInterface (not a specific driver)
- Uploads: validate → generate path → store file → create entity
- Path generation: configurable pattern (e.g., YYYY/MM/filename.ext)
- Validation: file size, MIME type whitelist, extension whitelist

## Requirements (Test Descriptions)
- [ ] `it uploads a file to the configured disk via filesystem interface`
- [ ] `it validates file size against configured maximum and throws UploadException`
- [ ] `it validates MIME type against configured whitelist and throws UploadException`
- [ ] `it validates file extension against configured whitelist and throws UploadException`
- [ ] `it creates Media entity record after successful upload`
- [ ] `it deletes file from storage and removes entity record on delete`
- [ ] `it retrieves file contents from storage via Media entity path and disk`

## Acceptance Criteria
- All requirements have passing tests
- MediaManager in `src/Service/MediaManager.php`
- Uses FilesystemInterface (not concrete) for all storage operations
- Uses MediaConfig for validation thresholds and disk settings
- Media entity is persisted via repository pattern
- Generated paths prevent filename collisions (slugify + UUID or timestamp)
- Code follows code standards

## Implementation Notes
