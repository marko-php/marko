# Task 016: Config refactor — session-file, encryption-openssl, filesystem packages

**Status**: pending
**Depends on**: none
**Retry count**: 0

## Description
Replace ConfigRepositoryInterface anonymous stubs in session-file, encryption-openssl, and filesystem test files with FakeConfigRepository. Add `marko/testing` to require-dev for each package.

## Context
- Related files:
  - `packages/session-file/tests/Unit/FileSessionHandlerTest.php` — ConfigRepositoryInterface (line 35)
  - `packages/encryption-openssl/tests/Unit/OpenSslEncryptorTest.php` — ConfigRepositoryInterface (line 21)
  - `packages/filesystem/tests/Unit/Config/FilesystemConfigTest.php` — ConfigRepositoryInterface (line 12)
  - `packages/encryption/tests/Unit/Config/EncryptionConfigTest.php` — ConfigRepositoryInterface (line 12)
  - 4 composer.json files to update (session-file, encryption-openssl, encryption, filesystem)

## Requirements (Test Descriptions)
- [ ] `it uses FakeConfigRepository in FileSessionHandlerTest`
- [ ] `it uses FakeConfigRepository in OpenSslEncryptorTest`
- [ ] `it uses FakeConfigRepository in FilesystemConfigTest`
- [ ] `it uses FakeConfigRepository in EncryptionConfigTest`
- [ ] `it preserves all existing test assertions and behaviors`

## Acceptance Criteria
- All existing package tests pass unchanged
- Config stubs removed from all 4 files
- `marko/testing` added to require-dev in all 4 composer.json files
- Run: `./vendor/bin/pest packages/session-file/tests/ packages/encryption-openssl/tests/ packages/encryption/tests/ packages/filesystem/tests/ --parallel`
