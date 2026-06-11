<?php

declare(strict_types=1);

use Marko\Encryption\Config\EncryptionConfig;
use Marko\Queue\Exceptions\SerializationException;
use Marko\Queue\JobEnvelope;
use Marko\Testing\Fake\FakeConfigRepository;

function createJobEnvelope(
    string $key = 'test-key-for-hmac-verification',
): JobEnvelope {
    $config = new EncryptionConfig(new FakeConfigRepository(['encryption.key' => $key]));

    return new JobEnvelope($config);
}

describe('JobEnvelope', function (): void {
    it('wraps a serialized job in an HMAC-signed envelope', function (): void {
        $envelope = createJobEnvelope();
        $serialized = 'O:8:"TestJob":1:{s:7:"message";s:4:"test";}';

        $wrapped = $envelope->wrap($serialized);

        expect($wrapped)->toBeString()
            ->and(strlen($wrapped))->toBe(64 + 1 + strlen($serialized))
            ->and($wrapped[64])->toBe('.');
    });

    it('verifies and unwraps a legitimately signed envelope', function (): void {
        $envelope = createJobEnvelope();
        $serialized = 'O:8:"TestJob":1:{s:7:"message";s:4:"test";}';

        $wrapped = $envelope->wrap($serialized);
        $unwrapped = $envelope->verifyAndUnwrap($wrapped);

        expect($unwrapped)->toBe($serialized);
    });

    it('throws SerializationException when the envelope HMAC does not verify', function (): void {
        $envelope = createJobEnvelope();
        $fakeHmac = str_repeat('a', 64);
        $payload = $fakeHmac . '.O:8:"TestJob":1:{s:7:"message";s:4:"test";}';

        expect(fn () => $envelope->verifyAndUnwrap($payload))
            ->toThrow(SerializationException::class);
    });

    it('refuses to unwrap a payload that has been tampered with', function (): void {
        $envelope = createJobEnvelope();
        $serialized = 'O:8:"TestJob":1:{s:7:"message";s:4:"test";}';

        $wrapped = $envelope->wrap($serialized);

        // Tamper with the payload part (after the HMAC)
        $tampered = substr($wrapped, 0, 65) . 'O:8:"EvilJob":0:{}';

        expect(fn () => $envelope->verifyAndUnwrap($tampered))
            ->toThrow(SerializationException::class);
    });

    it('throws loudly when the signing key is empty', function (): void {
        $envelope = createJobEnvelope(key: '');

        expect(fn () => $envelope->wrap('O:8:"TestJob":0:{}'))
            ->toThrow(SerializationException::class);
    });
});
