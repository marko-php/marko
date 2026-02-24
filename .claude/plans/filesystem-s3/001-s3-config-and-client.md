# Task 001: S3Config and AWS SDK Client Setup

**Status**: pending
**Depends on**: none
**Retry count**: 0

## Description
Create the `S3Config` readonly value object that encapsulates all S3 connection parameters (bucket, region, credentials, prefix, endpoint, URL, path-style). Include validation for required fields. Also set up the composer.json with the `aws/aws-sdk-php` dependency and establish the package namespace, Pest.php, and a helper function for creating a mock S3Client used across all test files.

## Context
- Reference: `packages/filesystem-local/composer.json` for package structure pattern
- Reference: `packages/filesystem/src/Config/FilesystemConfig.php` for config access pattern
- Namespace: `Marko\Filesystem\S3\`
- The S3Config is constructed from the disk config array (passed by the factory)
- Custom endpoint support enables MinIO, DigitalOcean Spaces, Backblaze B2
- Path-style endpoint is needed for MinIO (`http://minio:9000/bucket` vs `http://bucket.s3.amazonaws.com`)
- The prefix is normalized (strip leading/trailing slashes) to avoid double-slash issues
- A helper to build an S3Client from S3Config will be used by the factory -- place it as a static method on S3Config or as a standalone builder

## Requirements (Test Descriptions)
- [ ] `it creates S3Config with all required parameters`
- [ ] `it creates S3Config with optional endpoint for S3-compatible services`
- [ ] `it normalizes prefix by trimming leading and trailing slashes`
- [ ] `it allows empty prefix for bucket root`
- [ ] `it stores path_style_endpoint flag for MinIO compatibility`
- [ ] `it builds S3Client options array with correct credentials and region`
- [ ] `it includes endpoint in client options when custom endpoint is set`

## Acceptance Criteria
- S3Config is a readonly class with constructor property promotion
- All parameters have explicit types
- Prefix normalization is automatic in the constructor
- A method (e.g., `toClientOptions()`) returns the array suitable for `new S3Client($options)`
- When endpoint is null, the options array does not include `endpoint` key
- When endpoint is set, `use_path_style_endpoint` is included based on the flag
- composer.json requires `aws/aws-sdk-php`, `marko/filesystem`, PHP ^8.5
- Pest.php is created in tests/ directory
- Tests use standard Pest assertions, no real AWS calls

## Implementation Notes
(Left blank)
