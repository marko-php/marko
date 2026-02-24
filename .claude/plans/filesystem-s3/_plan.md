# Plan: Filesystem S3 Driver

## Created
2026-02-24

## Status
done

## Objective
Build `marko/filesystem-s3` -- an S3 filesystem driver implementing `FilesystemInterface` using the AWS SDK. Supports Amazon S3 and S3-compatible services (MinIO, DigitalOcean Spaces, Backblaze B2, etc.) via custom endpoint configuration. Follows the same driver pattern as `marko/filesystem-local` with attribute-based factory discovery.

## Scope
### In Scope
- `S3Config` value object for bucket, region, credentials, prefix, endpoint, and URL configuration
- `S3Filesystem` implementing all `FilesystemInterface` methods via AWS S3 SDK
- `S3FilesystemFactory` with `#[FilesystemDriver('s3')]` attribute for auto-discovery
- Content-type detection for uploads (extension-based + override via options)
- Streaming support for large file reads and writes via `readStream`/`writeStream`
- Temporary URL generation (pre-signed URLs) with configurable expiration
- Public URL generation for publicly accessible objects
- Support for S3-compatible services via custom endpoint configuration
- Path prefix support for organizing objects within a bucket
- Visibility mapping to S3 ACLs (public-read / private)
- Package scaffolding: composer.json, module.php, Pest tests

### Out of Scope
- Multipart uploads (rely on SDK's built-in handling via PutObject for now)
- S3 event notifications or lifecycle policies
- Cross-region replication configuration
- Bucket creation or management
- Server-side encryption configuration (use bucket defaults)
- Transfer acceleration
- S3 Select or Glacier operations

## Success Criteria
- [x] `S3Config` encapsulates all S3 connection parameters with validation
- [x] `S3Filesystem` implements every method of `FilesystemInterface`
- [x] `S3FilesystemFactory` is discovered via `#[FilesystemDriver('s3')]` attribute
- [x] Pre-signed temporary URLs generated with configurable expiration
- [x] Custom endpoint support enables MinIO/DigitalOcean Spaces/Backblaze B2
- [x] Content-type detection works for common file types
- [x] Streaming operations work for large files without loading into memory
- [x] Path prefix correctly scopes all operations within a bucket
- [x] Visibility maps to S3 ACLs correctly
- [x] All tests passing with mocked S3 client (no real AWS calls)
- [x] Code follows project standards (strict types, constructor promotion, no final)

## Task Overview
| Task | Description | Depends On | Status |
|------|-------------|------------|--------|
| 001 | S3Config value object and AWS SDK client setup | - | done |
| 002 | S3 read operations (read, exists, size, lastModified, listContents, info, mimeType) | 001 | done |
| 003 | S3 write operations (write, writeStream, delete, copy, move, append, directories, visibility) | 001 | done |
| 004 | URL generation (url, temporaryUrl with pre-signed URLs) | 001 | done |
| 005 | Driver wiring (S3FilesystemFactory, composer.json, module.php, package structure tests) | 002, 003, 004 | done |

## Architecture Notes

### Package Structure
```
packages/
  filesystem-s3/
    src/
      Config/
        S3Config.php
      Filesystem/
        S3Filesystem.php
      Factory/
        S3FilesystemFactory.php
    tests/
      Unit/
        Config/
          S3ConfigTest.php
        Filesystem/
          S3ReadOperationsTest.php
          S3WriteOperationsTest.php
          S3UrlGenerationTest.php
      PackageStructureTest.php
      Pest.php
    composer.json
```

### Namespace
- `Marko\Filesystem\S3\` -- follows the pattern of `Marko\Filesystem\Local\`

### S3Config Value Object
```php
readonly class S3Config
{
    public function __construct(
        public string $bucket,
        public string $region,
        public string $key,
        public string $secret,
        public string $prefix = '',
        public ?string $endpoint = null,
        public ?string $url = null,
        public bool $pathStyleEndpoint = false,
    ) {}
}
```

### Config Integration
```php
// config/filesystem.php
return [
    'disks' => [
        's3' => [
            'driver' => 's3',
            'bucket' => env('AWS_BUCKET'),
            'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'prefix' => '',
            'endpoint' => env('AWS_ENDPOINT'),       // null for real S3
            'url' => env('AWS_URL'),                  // custom base URL
            'path_style_endpoint' => false,           // true for MinIO
        ],
    ],
];
```

### S3 Client Creation
The factory creates an `S3Client` from the AWS SDK using the config array. Custom endpoint enables S3-compatible services. Path-style endpoint is needed for MinIO and some compatible services.

### Visibility Mapping
- `public` -> `public-read` ACL
- `private` -> `private` ACL

### Path Handling
All paths are prefixed with `S3Config::$prefix` before S3 operations. The prefix is stripped from returned paths. Forward slashes are normalized.

### Content-Type Detection
Uses a built-in extension-to-MIME map for common types, falling back to `application/octet-stream`. The `ContentType` parameter is set on `PutObject` calls.

### Temporary URLs
Uses the AWS SDK's `createPresignedRequest` on a `GetObject` command with configurable expiration (default: 1 hour).

### Testing Strategy
All tests mock the AWS `S3Client` -- no real AWS calls. Tests verify correct SDK method calls, parameter passing, error handling, and response parsing.

## Risks & Mitigations

| Risk | Mitigation |
|------|-----------|
| **AWS SDK dependency size** | Required only in this package, not in the interface package |
| **Network errors during S3 operations** | Wrap SDK exceptions in FilesystemException with helpful context and suggestions |
| **Missing credentials at runtime** | S3Config validates required fields; factory throws loud error with clear suggestion |
| **Path prefix collisions** | Normalize prefix (strip leading/trailing slashes) consistently |
| **S3-compatible service differences** | Test with endpoint override; document known compatibility notes |
| **Large file memory usage** | Stream-based operations (readStream/writeStream) avoid loading full files into memory |
| **Pre-signed URL clock skew** | Document that server time must be synchronized; SDK handles retry |
