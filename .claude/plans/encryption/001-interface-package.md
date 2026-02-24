# Task 001: Interface Package (marko/encryption)

**Status**: pending
**Depends on**: none
**Retry count**: 0

## Description
Create the encryption interface package with EncryptorInterface, exceptions, and config.

## Context
- Namespace: `Marko\Encryption\`
- Package: `marko/encryption`
- Dependencies: marko/core, marko/config
- Pattern: Same as marko/cache, marko/session (interface-only packages)

## Requirements (Test Descriptions)
- [ ] `it defines EncryptorInterface with encrypt and decrypt methods`
- [ ] `it defines EncryptionException with context and suggestion`
- [ ] `it defines DecryptionException extending EncryptionException`
- [ ] `it defines EncryptionConfig with key and cipher methods`
- [ ] `it has marko module flag in composer.json`
