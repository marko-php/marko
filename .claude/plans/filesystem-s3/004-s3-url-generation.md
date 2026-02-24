# Task 004: S3 URL Generation

**Status**: pending
**Depends on**: 001
**Retry count**: 0

## Description
Add `url()` and `temporaryUrl()` methods to `S3Filesystem` for generating public URLs and pre-signed temporary URLs. These methods are S3-specific and not part of `FilesystemInterface` -- they are additional capabilities of the S3 driver. The public `url()` constructs a URL from the configured base URL or the default S3 endpoint. The `temporaryUrl()` uses the AWS SDK's `createPresignedRequest` to generate time-limited access URLs.

## Context
- Reference: AWS SDK `S3Client::createPresignedRequest()` for pre-signed URL generation
- `url()` constructs: `{base_url}/{prefix}/{path}` where base_url is either `S3Config::$url` (custom) or `https://{bucket}.s3.{region}.amazonaws.com`
- `temporaryUrl()` creates a `GetObject` command and generates a pre-signed request with expiration
- For S3-compatible services, the custom endpoint from `S3Config` is used as the base URL when no explicit `url` is configured
- Expiration defaults to 1 hour (3600 seconds) but is configurable
- Pre-signed URLs work for both public and private objects

## Requirements (Test Descriptions)
- [ ] `it generates public URL using default S3 endpoint pattern`
- [ ] `it generates public URL using custom base URL from config`
- [ ] `it includes prefix in generated URLs`
- [ ] `it generates pre-signed temporary URL with default expiration`
- [ ] `it generates pre-signed temporary URL with custom expiration`
- [ ] `it uses custom endpoint for URL generation on S3-compatible services`

## Acceptance Criteria
- `url(string $path): string` is a public method on S3Filesystem (not on the interface)
- `temporaryUrl(string $path, int $expiration = 3600): string` is a public method on S3Filesystem
- `url()` returns `{S3Config::$url}/{prefix}/{path}` when `$url` is configured
- `url()` returns `https://{bucket}.s3.{region}.amazonaws.com/{prefix}/{path}` when no custom URL
- `url()` returns `{endpoint}/{bucket}/{prefix}/{path}` when path-style endpoint is used
- `temporaryUrl()` creates a `GetObject` command on the S3Client
- `temporaryUrl()` calls `createPresignedRequest` with a `DateTimeImmutable` for expiration
- `temporaryUrl()` returns the URI string from the pre-signed request
- Both methods apply the prefix to the path
- Both methods normalize slashes (no double slashes in URLs)
- Tests mock the S3Client and verify command creation and presigned request parameters

## Implementation Notes
(Left blank)
