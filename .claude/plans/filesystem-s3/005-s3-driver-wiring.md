# Task 005: S3 Driver Wiring

**Status**: pending
**Depends on**: 002, 003, 004
**Retry count**: 0

## Description
Create the `S3FilesystemFactory` with the `#[FilesystemDriver('s3')]` attribute for auto-discovery by `DriverDiscovery`. Create the `composer.json` with correct dependencies and autoloading. Write the `PackageStructureTest` to validate the package is properly configured. This task wires everything together so that configuring a disk with `'driver' => 's3'` in `config/filesystem.php` automatically uses the S3 driver.

## Context
- Reference: `packages/filesystem-local/src/Factory/LocalFilesystemFactory.php` for factory pattern
- Reference: `packages/filesystem-local/composer.json` for composer.json structure
- Reference: `packages/filesystem-local/tests/PackageStructureTest.php` for package validation tests
- Reference: `packages/filesystem/src/Attributes/FilesystemDriver.php` for the attribute
- Reference: `packages/filesystem/src/Contracts/FilesystemDriverFactoryInterface.php` for the factory interface
- The factory receives the disk config array from `FilesystemManager::createDisk()`
- The factory must validate required config keys (bucket, region, key, secret) and throw `FilesystemException` with helpful messages
- The factory creates `S3Config` from the config array, builds the S3Client, and returns `S3Filesystem`
- No module.php needed -- the `#[FilesystemDriver('s3')]` attribute on the factory class is sufficient for discovery by `DriverDiscovery` which scans all module src directories

## Requirements (Test Descriptions)
- [ ] `it has valid composer.json with correct package name marko/filesystem-s3`
- [ ] `it requires aws/aws-sdk-php and marko/filesystem in composer.json`
- [ ] `it has PSR-4 autoloading configured for Marko\\Filesystem\\S3 namespace`
- [ ] `it has S3FilesystemFactory with FilesystemDriver attribute named s3`
- [ ] `it has S3FilesystemFactory implementing FilesystemDriverFactoryInterface`
- [ ] `it creates S3Filesystem from config array with all required parameters`
- [ ] `it throws FilesystemException when required config keys are missing`

## Acceptance Criteria
- `S3FilesystemFactory` at `src/Factory/S3FilesystemFactory.php`
- Factory class has `#[FilesystemDriver('s3')]` attribute
- Factory implements `FilesystemDriverFactoryInterface`
- `create(array $config)` validates: bucket, region, key, secret are present
- Missing config throws `FilesystemException` with message naming the missing key, context showing the provided config (with secret redacted), and suggestion to check environment variables
- Factory extracts all config values including optional ones (prefix, endpoint, url, path_style_endpoint)
- Factory creates `S3Config`, then `S3Client`, then returns `S3Filesystem`
- `composer.json` has: name `marko/filesystem-s3`, type `marko-module`, requires php ^8.5, marko/filesystem @dev, aws/aws-sdk-php ^3.0
- PSR-4 autoload maps `Marko\\Filesystem\\S3\\` to `src/`
- `extra.marko.module` is `true`
- `PackageStructureTest.php` validates all of the above

## Implementation Notes
(Left blank)
