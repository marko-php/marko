# Task 002: OpenSSL Package Scaffolding and Module Tests

**Status**: pending
**Depends on**: none
**Retry count**: 0

## Description
Create the encryption-openssl package scaffolding with composer.json, module.php, and module tests.

## Context
- Namespace: `Marko\Encryption\OpenSsl\`
- Package: `marko/encryption-openssl`
- Dependencies: marko/core, marko/config, marko/encryption
- Reference: packages/cache-redis/ (recently created driver package)

## Requirements (Test Descriptions)
- [ ] `it binds EncryptorInterface to OpenSslEncryptor`
- [ ] `it returns valid module configuration array`
- [ ] `it has marko module flag in composer.json`
