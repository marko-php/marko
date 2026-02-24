# Task 003: S3 Write Operations

**Status**: pending
**Depends on**: 001
**Retry count**: 0

## Description
Implement the write-side methods of `S3Filesystem`: `write`, `writeStream`, `append`, `delete`, `copy`, `move`, `makeDirectory`, `deleteDirectory`, `setVisibility`, and `visibility`. Includes content-type detection for uploads and ACL-based visibility mapping.

## Context
- Reference: `packages/filesystem-local/src/Filesystem/LocalFilesystem.php` for method contracts
- `write` uses `PutObject` with `Body`, `ContentType`, and optional `ACL`
- `writeStream` uses `PutObject` with a stream resource as `Body`
- `append` must read existing content, concatenate, and write back (S3 does not support append natively)
- `delete` uses `DeleteObject` -- returns true even if object did not exist (S3 is idempotent on delete)
- `copy` uses `CopyObject` with `CopySource` set to `{bucket}/{prefixed_source_key}`
- `move` is `copy` + `delete` of the source
- `makeDirectory` is a no-op on S3 (directories are virtual) but should create a zero-byte marker object with trailing `/` for consistency
- `deleteDirectory` uses `ListObjectsV2` to find all objects with the prefix, then `DeleteObjects` to batch-delete them
- `setVisibility` uses `PutObjectAcl` -- maps `public` to `public-read`, `private` to `private`
- `visibility` uses `GetObjectAcl` -- checks grants for public read access
- Content-type detection: use an extension-to-MIME map for common types (jpg, png, gif, pdf, html, css, js, json, xml, svg, zip, csv, txt, mp4, mp3, etc.), falling back to `application/octet-stream`

## Requirements (Test Descriptions)
- [ ] `it writes file contents to S3 with detected content type`
- [ ] `it writes stream to S3 using writeStream`
- [ ] `it appends content to existing S3 object`
- [ ] `it deletes an object from S3`
- [ ] `it copies an object within the same bucket`
- [ ] `it moves an object by copying then deleting the source`
- [ ] `it sets visibility to public using public-read ACL`

## Acceptance Criteria
- `write` sets `ContentType` from extension-based detection, overridable via `$options['content_type']`
- `write` sets `ACL` based on `$options['visibility']` if provided
- `writeStream` validates that the resource is a valid stream before passing to SDK
- `append` reads existing content via `read()`, concatenates, and writes back; creates new file if it does not exist
- `delete` returns true even if object did not exist (idempotent)
- `copy` correctly formats `CopySource` as `/{bucket}/{key}` (URL-encoded)
- `move` calls `copy` then `delete`
- `makeDirectory` creates a zero-byte object with key ending in `/`
- `deleteDirectory` lists and batch-deletes all objects under the prefix
- `setVisibility('public')` sets ACL to `public-read`; `setVisibility('private')` sets ACL to `private`
- `visibility` inspects ACL grants to determine if public read is granted
- Content-type map covers at least: jpg/jpeg, png, gif, webp, svg, pdf, html, css, js, json, xml, zip, tar, gz, csv, txt, mp4, mp3, woff, woff2
- All SDK exceptions wrapped in FilesystemException
- Tests mock the S3Client

## Implementation Notes
(Left blank)
