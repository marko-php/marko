# Task 004: OpenSSL AEAD enforcement + payload type validation

**Status**: complete
**Depends on**: [none]
**Retry count**: 0

## Description
`OpenSslEncryptor` does not enforce that the configured cipher is AEAD: a non-AEAD cipher (e.g. `aes-256-cbc`) silently produces an empty `$tag` that is ignored on decrypt, yielding malleable ciphertext. It also does not handle `openssl_cipher_iv_length()` returning `false` (which makes `random_bytes(false)` raise a `TypeError`), and its `isset()` payload checks don't validate field types, so a crafted payload like `{"iv":1,...}` raises a `TypeError` from `base64_decode` instead of a loud `DecryptionException`. Validate the cipher is AEAD at construction, handle the false iv-length, and validate payload field types loudly.

## Context
- Related files:
  - `/Users/markshust/Sites/marko/packages/encryption-openssl/src/OpenSslEncryptor.php` (constructor; `encrypt()` ~42-47; `decrypt()` ~93-101)
  - `/Users/markshust/Sites/marko/packages/encryption/src/Config/EncryptionConfig.php` (`cipher()` getter)
  - `/Users/markshust/Sites/marko/packages/encryption/src/Exceptions/EncryptionException.php` (add `nonAeadCipher()` / `invalidCipher()` factories)
  - `/Users/markshust/Sites/marko/packages/encryption/src/Exceptions/DecryptionException.php` (reuse `invalidPayload()`)
  - Tests: `/Users/markshust/Sites/marko/packages/encryption-openssl/tests/` (mirror existing `OpenSslEncryptor` tests)
- Patterns to follow:
  - IMPORTANT: the current code does NOT store the cipher — it calls `$this->config->cipher()` afresh inside both `encrypt()` and `decrypt()`. To validate "at construction" (a stated success criterion), the constructor must fetch the cipher ONCE, validate it, and store it in a private readonly property (e.g. `$this->cipher`); `encrypt()`/`decrypt()` then use `$this->cipher` instead of re-calling the config. This is the only way the non-AEAD rejection happens at construction rather than first-encrypt.
  - Validation: cipher must be in `openssl_get_cipher_methods()` AND be an AEAD mode (name ends with `-gcm` or `-ccm`, case-insensitive). `openssl_get_cipher_methods()` returns lowercase names — normalize the configured cipher to lowercase before the membership and suffix checks.
  - Loud errors with `message`/`context`/`suggestion`; `EncryptionException` for construction-time failures (note: `EncryptionException` extends `\Exception`, NOT `MarkoException`, but shares the same 3-param shape — match `DecryptionException::invalidPayload()`'s factory style). Use `DecryptionException::invalidPayload()` for malformed inbound payloads.
  - Handle `openssl_cipher_iv_length($cipher) === false` at CONSTRUCTION (right after the AEAD validation, since the cipher is now known and fixed) → throw `EncryptionException` (clear message, not a `TypeError` from `random_bytes(false)`). Optionally store the iv length too.
  - Replace `isset($payload['iv'], $payload['value'], $payload['tag'])` with explicit `is_string()` checks on each field before `base64_decode` (so a non-string field throws `DecryptionException::invalidPayload()`, never a `TypeError`).
  - `encrypt()` calls `random_bytes()`, which throws `RandomException`. The existing `encrypt()` does NOT declare `@throws RandomException` — since this task edits `encrypt()`, add the missing `@throws RandomException` (or catch-and-convert to `EncryptionException`) per the loud-errors/handle-checked-exceptions standard. Pick one and document it.
  - The default cipher `aes-256-gcm` must continue to work end-to-end.

## Requirements (Test Descriptions)
- [x] `it throws EncryptionException at construction when the configured cipher is not AEAD`
- [x] `it throws EncryptionException at construction when the configured cipher is unknown to openssl`
- [x] `it constructs successfully with the default aes-256-gcm cipher`
- [x] `it throws DecryptionException invalidPayload when a payload field is not a string`
- [x] `it throws DecryptionException invalidPayload when a required payload field is missing`
- [x] `it round-trips encrypt then decrypt with the default AEAD cipher`
- [x] `it throws DecryptionException rather than a TypeError for a crafted non-string iv`

## Acceptance Criteria
- All requirements have passing tests
- Code follows code standards
- No decrease in test coverage

## Implementation Notes
- Added `nonAeadCipher()` and `invalidCipher()` static factory methods to `EncryptionException`
- `OpenSslEncryptor` constructor now fetches cipher once via `strtolower($this->config->cipher())`, validates it is in `openssl_get_cipher_methods()` (throws `invalidCipher()`), and checks it ends with `-gcm` or `-ccm` (throws `nonAeadCipher()`); stores result in `private readonly string $cipher`
- `openssl_cipher_iv_length()` returning `false` now throws `EncryptionException` at construction; iv length stored in `private readonly int $ivLength`
- `encrypt()` uses `$this->cipher` and `$this->ivLength` instead of re-calling config; added `@throws RandomException` per loud-errors standard
- `decrypt()` replaced `isset()` checks with explicit `is_string($payload['iv'] ?? null)` etc. to catch non-string fields before `base64_decode` is called
- `OpenSslEncryptor` kept as plain `class` (not `readonly class`) per task guardrails — designed for extension
- Default cipher `aes-256-gcm` continues to work end-to-end; all 30 package tests pass
