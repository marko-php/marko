# Task 002: S3 Read Operations

**Status**: pending
**Depends on**: 001
**Retry count**: 0

## Description
Implement the read-side methods of `S3Filesystem`: `read`, `readStream`, `exists`, `isFile`, `isDirectory`, `info`, `size`, `lastModified`, `mimeType`, and `listDirectory`. All operations use the AWS S3 SDK client, prefix paths with `S3Config::$prefix`, and wrap SDK exceptions in `FilesystemException` with helpful context.

## Context
- Reference: `packages/filesystem-local/src/Filesystem/LocalFilesystem.php` for the interface contract
- Reference: `packages/filesystem/src/Contracts/FilesystemInterface.php` for method signatures
- Reference: `packages/filesystem/src/Values/FileInfo.php`, `DirectoryEntry.php`, `DirectoryListing.php` for return types
- S3 has no real concept of directories; they are inferred from key prefixes ending with `/`
- `exists` uses `HeadObject` and catches `NoSuchKey` / 404 errors
- `isFile` checks that the key exists and does not end with `/`
- `isDirectory` checks for objects with the path as prefix (using `ListObjectsV2` with max-keys=1)
- `read` uses `GetObject` and reads the Body stream to string
- `readStream` uses `GetObject` and returns the Body stream resource directly
- `info` uses `HeadObject` to get ContentLength, LastModified, ContentType
- `size` and `lastModified` use `HeadObject`
- `mimeType` uses `HeadObject` ContentType header
- `listDirectory` uses `ListObjectsV2` with delimiter `/` and prefix to get files and common prefixes (subdirectories)
- All paths must be prefixed with `S3Config::$prefix` before SDK calls and stripped from results

## Requirements (Test Descriptions)
- [ ] `it reads file contents from S3 using GetObject`
- [ ] `it returns a stream resource from readStream`
- [ ] `it returns true for exists when object exists`
- [ ] `it returns false for exists when object does not exist`
- [ ] `it throws FileNotFoundException when reading non-existent file`
- [ ] `it returns file info with size, lastModified, and mimeType from HeadObject`
- [ ] `it lists directory contents using ListObjectsV2 with delimiter`

## Acceptance Criteria
- S3Filesystem class created at `src/Filesystem/S3Filesystem.php`
- Constructor accepts `S3Client` and `S3Config`
- All paths are prefixed before SDK calls using a private `prefixPath` method
- All returned paths have the prefix stripped using a private `stripPrefix` method
- SDK exceptions (S3Exception) are caught and wrapped in FilesystemException with three-part pattern (message, context, suggestion)
- FileNotFoundException is thrown when HeadObject returns 404 or NoSuchKey
- `listDirectory` returns a `DirectoryListing` with `DirectoryEntry` objects
- `readStream` returns the raw stream from the S3 response Body (which is a `GuzzleHttp\Psr7\Stream`)
- Tests mock the S3Client, verifying correct method calls and parameters

## Implementation Notes
(Left blank)
