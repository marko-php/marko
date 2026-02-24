# Task 003: OpenSslEncryptor Implementation and Tests

**Status**: pending
**Depends on**: 001, 002
**Retry count**: 0

## Description
Implement OpenSslEncryptor using AES-256-GCM with proper IV generation. Encrypted output is base64-encoded for safe storage/transport.

## Context
- Reference: packages/hashing/src/Bcrypt/BcryptHasher.php (similar service pattern)
- Uses EncryptionConfig for key retrieval
- Cipher: aes-256-gcm (authenticated encryption)
- Format: base64(json({iv, value, tag})) for portability

## Requirements (Test Descriptions)
- [ ] `it implements EncryptorInterface`
- [ ] `it encrypts string value`
- [ ] `it decrypts back to original value`
- [ ] `it produces different ciphertext for same plaintext`
- [ ] `it encrypts and decrypts empty string`
- [ ] `it encrypts and decrypts long text`
- [ ] `it encrypts and decrypts unicode text`
- [ ] `it throws DecryptionException for tampered ciphertext`
- [ ] `it throws DecryptionException for invalid base64`
- [ ] `it throws DecryptionException for wrong key`
