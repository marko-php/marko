<?php

declare(strict_types=1);

use Marko\Encryption\Config\EncryptionConfig;
use Marko\Encryption\Contracts\EncryptorInterface;
use Marko\Encryption\Exceptions\DecryptionException;
use Marko\Encryption\Exceptions\EncryptionException;
use Marko\Encryption\OpenSsl\OpenSslEncryptor;
use Marko\Testing\Fake\FakeConfigRepository;

function createTestEncryptionConfig(
    string $key = '',
    string $cipher = 'aes-256-gcm',
): EncryptionConfig {
    if ($key === '') {
        $key = base64_encode(random_bytes(32));
    }

    $repository = new FakeConfigRepository([
        'encryption.key' => $key,
        'encryption.cipher' => $cipher,
    ]);

    return new EncryptionConfig($repository);
}

describe('OpenSslEncryptor', function (): void {
    it('implements EncryptorInterface', function (): void {
        $encryptor = new OpenSslEncryptor(createTestEncryptionConfig());

        expect($encryptor)->toBeInstanceOf(EncryptorInterface::class);
    });

    it('encrypts string value', function (): void {
        $encryptor = new OpenSslEncryptor(createTestEncryptionConfig());
        $plaintext = 'Hello, World!';

        $encrypted = $encryptor->encrypt($plaintext);

        expect($encrypted)->not->toBe($plaintext)
            ->and($encrypted)->toBeString()
            ->and($encrypted)->not->toBeEmpty();
    });

    it('decrypts back to original value', function (): void {
        $encryptor = new OpenSslEncryptor(createTestEncryptionConfig());
        $plaintext = 'Hello, World!';

        $encrypted = $encryptor->encrypt($plaintext);
        $decrypted = $encryptor->decrypt($encrypted);

        expect($decrypted)->toBe($plaintext);
    });

    it('produces different ciphertext for same plaintext', function (): void {
        $encryptor = new OpenSslEncryptor(createTestEncryptionConfig());
        $plaintext = 'Same text';

        $encrypted1 = $encryptor->encrypt($plaintext);
        $encrypted2 = $encryptor->encrypt($plaintext);

        expect($encrypted1)->not->toBe($encrypted2);
    });

    it('encrypts and decrypts empty string', function (): void {
        $encryptor = new OpenSslEncryptor(createTestEncryptionConfig());

        $encrypted = $encryptor->encrypt('');
        $decrypted = $encryptor->decrypt($encrypted);

        expect($decrypted)->toBe('');
    });

    it('encrypts and decrypts long text', function (): void {
        $encryptor = new OpenSslEncryptor(createTestEncryptionConfig());
        $plaintext = str_repeat('A long piece of text. ', 50);

        $encrypted = $encryptor->encrypt($plaintext);
        $decrypted = $encryptor->decrypt($encrypted);

        expect($decrypted)->toBe($plaintext)
            ->and(strlen($plaintext))->toBeGreaterThan(1000);
    });

    it('encrypts and decrypts unicode text', function (): void {
        $encryptor = new OpenSslEncryptor(createTestEncryptionConfig());
        $plaintext = 'Привет мир! 你好世界! こんにちは世界!';

        $encrypted = $encryptor->encrypt($plaintext);
        $decrypted = $encryptor->decrypt($encrypted);

        expect($decrypted)->toBe($plaintext);
    });

    it('throws DecryptionException for tampered ciphertext', function (): void {
        $encryptor = new OpenSslEncryptor(createTestEncryptionConfig());
        $encrypted = $encryptor->encrypt('secret data');

        $json = base64_decode($encrypted, true);
        $payload = json_decode($json, true);
        $payload['value'] = base64_encode('tampered');
        $tampered = base64_encode(json_encode($payload));

        $encryptor->decrypt($tampered);
    })->throws(DecryptionException::class);

    it('throws DecryptionException for invalid base64', function (): void {
        $encryptor = new OpenSslEncryptor(createTestEncryptionConfig());

        $encryptor->decrypt('not-valid-base64!!!');
    })->throws(DecryptionException::class);

    it('throws DecryptionException for wrong key', function (): void {
        $key1 = base64_encode(random_bytes(32));
        $key2 = base64_encode(random_bytes(32));

        $encryptor1 = new OpenSslEncryptor(createTestEncryptionConfig(key: $key1));
        $encryptor2 = new OpenSslEncryptor(createTestEncryptionConfig(key: $key2));

        $encrypted = $encryptor1->encrypt('secret data');

        $encryptor2->decrypt($encrypted);
    })->throws(DecryptionException::class);

    it('throws EncryptionException for invalid key', function (): void {
        new OpenSslEncryptor(createTestEncryptionConfig(key: 'not-valid-base64'));
    })->throws(EncryptionException::class, 'Invalid encryption key');

    it('throws EncryptionException for key with wrong length', function (): void {
        $shortKey = base64_encode(random_bytes(16));

        new OpenSslEncryptor(createTestEncryptionConfig(key: $shortKey));
    })->throws(EncryptionException::class, 'Invalid encryption key');

    it('throws EncryptionException at construction when the configured cipher is not AEAD', function (): void {
        new OpenSslEncryptor(createTestEncryptionConfig(cipher: 'aes-256-cbc'));
    })->throws(EncryptionException::class);

    it(
        'throws EncryptionException at construction when the configured cipher is unknown to openssl',
        function (): void {
            new OpenSslEncryptor(createTestEncryptionConfig(cipher: 'not-a-real-cipher'));
        },
    )->throws(EncryptionException::class);

    it('constructs successfully with the default aes-256-gcm cipher', function (): void {
        $encryptor = new OpenSslEncryptor(createTestEncryptionConfig(cipher: 'aes-256-gcm'));

        expect($encryptor)->toBeInstanceOf(OpenSslEncryptor::class);
    });

    it('throws DecryptionException invalidPayload when a payload field is not a string', function (): void {
        $encryptor = new OpenSslEncryptor(createTestEncryptionConfig());
        $encrypted = $encryptor->encrypt('secret');

        $json = base64_decode($encrypted, true);
        $payload = json_decode($json, true);
        $payload['iv'] = 12345;
        $tampered = base64_encode(json_encode($payload));

        $encryptor->decrypt($tampered);
    })->throws(DecryptionException::class);

    it('throws DecryptionException invalidPayload when a required payload field is missing', function (): void {
        $encryptor = new OpenSslEncryptor(createTestEncryptionConfig());
        $encrypted = $encryptor->encrypt('secret');

        $json = base64_decode($encrypted, true);
        $payload = json_decode($json, true);
        unset($payload['tag']);
        $tampered = base64_encode(json_encode($payload));

        $encryptor->decrypt($tampered);
    })->throws(DecryptionException::class);

    it('round-trips encrypt then decrypt with the default AEAD cipher', function (): void {
        $encryptor = new OpenSslEncryptor(createTestEncryptionConfig(cipher: 'aes-256-gcm'));
        $plaintext = 'Hello AEAD world!';

        $decrypted = $encryptor->decrypt($encryptor->encrypt($plaintext));

        expect($decrypted)->toBe($plaintext);
    });

    it('throws DecryptionException rather than a TypeError for a crafted non-string iv', function (): void {
        $encryptor = new OpenSslEncryptor(createTestEncryptionConfig());
        $encrypted = $encryptor->encrypt('secret');

        $json = base64_decode($encrypted, true);
        $payload = json_decode($json, true);
        $payload['iv'] = ['not', 'a', 'string'];
        $tampered = base64_encode(json_encode($payload));

        $encryptor->decrypt($tampered);
    })->throws(DecryptionException::class);
});
