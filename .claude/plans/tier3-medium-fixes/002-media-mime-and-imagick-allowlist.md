# Task 002: Media MIME-from-content derivation and Imagick raster-format allowlist

**Status**: pending
**Depends on**: [none]
**Retry count**: 0

## Description
`MediaManager::upload()` validates against caller-supplied `UploadedFile->mimeType`, which is attacker-controlled, and `ImagickImageProcessor` opens and writes any file Imagick can decode with no format allowlist (SVG/MSL/EPS/PDF coders enable SSRF and RCE-adjacent attacks). Derive the MIME type from the file content via `finfo` rather than trusting the upload, and restrict Imagick to an explicit raster-format allowlist (verify `getImageFormat()` before processing/writing).

## Context
- Related files: `packages/media/src/Service/MediaManager.php` (upload ~27-55, MIME check ~34-35, persisted mime ~49), `packages/media/src/Value/UploadedFile.php`, `packages/media/src/Config/MediaConfig.php` (`allowedMimeTypes()`), `packages/media/src/Exceptions/UploadException.php`, `packages/media/config/media.php`, `packages/media-imagick/src/Driver/ImagickImageProcessor.php` (resize ~31-60, crop ~65-81, and the convert/optimize methods below), `packages/media-imagick/src/Exceptions/ImagickProcessingException.php` (currently bare `extends MarkoException`)
- Patterns to follow: config getters throw `ConfigNotFoundException`, defaults live in `config/*.php` (add a `media-imagick` config file + typed config getter mirroring `MediaConfig`/`CorsConfig` for the allowed-format list); loud errors via static factory methods on `UploadException` and `ImagickProcessingException`; `readonly class` for config/value objects.
- Derive MIME from `$file->tmpPath` content (the bytes actually written via `file_get_contents($file->tmpPath)` at MediaManager ~44), NOT from `$file->mimeType` or `$file->extension`. Persist the derived MIME (`$media->mimeType`) rather than the caller-supplied one (~49).
- **MIME↔extension cross-check gap (VERIFIED):** `MediaConfig` today exposes `allowedMimeTypes()` and `allowedExtensions()` but NO mime→extension map, so the "content-derived MIME does not match the declared extension" test needs a mapping that does not exist yet. Add an explicit `allowed` mime→extension(s) map to `config/media.php` with a typed `MediaConfig` getter (throws `ConfigNotFoundException` when missing — no hardcoded fallback) and validate the declared `$file->extension` against the entry for the derived MIME. Do not infer the mapping silently from `finfo`-internal tables.
- **finfo availability:** guard with a loud `UploadException` if `finfo_open`/the fileinfo extension is unavailable rather than silently trusting the caller MIME.

## Requirements (Test Descriptions)
- [ ] `it derives the MIME type from file content via finfo and ignores the caller-supplied mimeType during upload`
- [ ] `it rejects an upload loudly when the content-derived MIME type is not in the allowed list`
- [ ] `it rejects an upload loudly when the content-derived MIME type does not match the declared file extension`
- [ ] `it accepts an upload whose content-derived MIME type is in the allowed list`
- [ ] `it throws an ImagickProcessingException when the image format is not in the raster allowlist`
- [ ] `it processes and writes an allowlisted raster image successfully`
- [ ] `it reads the Imagick raster allowlist from configuration and throws ConfigNotFoundException when the key is missing`

## Acceptance Criteria
- All requirements have passing tests
- Code follows code standards
- No decrease in test coverage

## Implementation Notes
(Left blank - filled in by programmer during implementation)
