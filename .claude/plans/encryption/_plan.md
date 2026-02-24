# Plan: encryption Packages

## Created
2026-02-23

## Status
ready

## Objective
Build `marko/encryption` (interface) and `marko/encryption-openssl` (driver) — encryption services using the interface/driver split pattern.

## Scope
### In Scope
- EncryptorInterface with encrypt/decrypt methods
- OpenSSL-based implementation (AES-256-CBC)
- Key management via config
- Proper IV generation and storage
- Package scaffolding for both packages

### Out of Scope
- Key rotation
- Asymmetric encryption
- Hashing (already exists in marko/hashing)

## Task Overview
| Task | Description | Depends On | Status |
|------|-------------|------------|--------|
| 001 | Interface package (marko/encryption) | - | pending |
| 002 | OpenSSL package scaffolding and module tests | - | pending |
| 003 | OpenSslEncryptor implementation and tests | 001, 002 | pending |

## Architecture Notes
- EncryptorInterface: encrypt(string): string, decrypt(string): string
- Encrypted format: base64(iv + ciphertext + tag) for portability
- Key from config: ENCRYPTION_KEY env var (base64-encoded 32-byte key)
- AES-256-GCM cipher for authenticated encryption
- Throws DecryptionException on tampered/invalid data
