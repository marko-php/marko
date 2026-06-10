# Task 012: S3 root-prefix fix, continuation-token paging, and loud per-key delete errors

**Status**: pending
**Depends on**: [none]
**Retry count**: 0

## Description
The S3 driver has three listing/deletion defects: `listDirectory()` constructs a double-slash prefix for the root and only ever issues a single `listObjectsV2` call, ignoring `IsTruncated`/`NextContinuationToken`; and `deleteDirectory()` lists once (capped at the 1000-object S3 page limit), deletes that page, and ignores the per-object `Errors` array in the `deleteObjects` response. Fix the root-prefix construction, follow continuation tokens for both list and delete so results past 1000 objects are handled, and surface per-key delete failures loudly.

## Context
- Related files: `packages/filesystem-s3/src/Filesystem/S3Filesystem.php` (listDirectory ~409-440 prefix ~413-417 + `listObjectsV2` ~419, deleteDirectory ~510-535 list ~512 + `deleteObjects` ~525, prefixPath ~650, stripPrefix ~662, `Aws\Result`/`S3Client`/`S3Exception` imports), `packages/filesystem-s3/src/Exceptions/FilesystemException.php`, `packages/filesystem-s3/tests/Support/MockS3Client.php`, `packages/filesystem-s3/tests/Unit/Filesystem/S3ReadOperationsTest.php` (MockS3Client::create + `new Result([...])` pattern)
- Patterns to follow: build the root prefix without a leading/double slash (when `path` is root, prefix is the config prefix or empty, not `/`); loop `listObjectsV2` while `IsTruncated`, passing `ContinuationToken => NextContinuationToken`, accumulating `Contents`/`CommonPrefixes`; for delete, page through the listing and call `deleteObjects` per ≤1000-key batch; inspect the `Errors` array on each `deleteObjects` result and throw a `FilesystemException` (message/context/suggestion listing the failed keys) when any key fails; tests use `MockS3Client::create([...])` returning successive `Aws\Result` objects.

## Requirements (Test Descriptions)
- [ ] `it lists entries at the prefixed root without a double-slash prefix`
- [ ] `it follows the continuation token to return objects beyond the first listing page`
- [ ] `it aggregates common prefixes across multiple truncated listing pages`
- [ ] `it deletes more than one thousand objects by paging through continuation tokens`
- [ ] `it throws a loud FilesystemException naming the keys when deleteObjects reports per-key errors`
- [ ] `it returns true and makes no delete call when the directory prefix is empty`

## Acceptance Criteria
- All requirements have passing tests
- Code follows code standards
- No decrease in test coverage

## Implementation Notes
(Left blank - filled in by programmer during implementation)
