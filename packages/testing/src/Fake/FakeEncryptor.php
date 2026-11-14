<?php

declare(strict_types=1);

namespace Marko\Testing\Fake;

use Marko\Encryption\Contracts\EncryptorInterface;
use Marko\Encryption\Exceptions\DecryptionException;
use Marko\Testing\Exceptions\AssertionFailedException;

/**
 * Reversible stand-in for a real encryptor. The ciphertext carries the associated
 * data it was encrypted with, so, like a real AEAD cipher, decrypt() throws unless it
 * is given the same associated data. Any instance can decrypt another's output.
 */
class FakeEncryptor implements EncryptorInterface
{
    private const string PREFIX = 'fake-encrypted:';

    /** @var list<array{value: string, aad: string, encrypted: string}> */
    public private(set) array $encrypted = [];

    /** @var list<array{encrypted: string, aad: string, value: string}> */
    public private(set) array $decrypted = [];

    private int $counter = 0;

    public function encrypt(
        string $value,
        string $aad = '',
    ): string {
        $this->counter++;

        // The counter makes each ciphertext unique, as a real cipher's random nonce does.
        $encrypted = self::PREFIX . $this->counter . ':' . base64_encode($aad) . ':' . base64_encode($value);

        $this->encrypted[] = ['value' => $value, 'aad' => $aad, 'encrypted' => $encrypted];

        return $encrypted;
    }

    /**
     * @throws DecryptionException
     */
    public function decrypt(
        string $encrypted,
        string $aad = '',
    ): string {
        if (!str_starts_with($encrypted, self::PREFIX)) {
            throw DecryptionException::invalidPayload();
        }

        $parts = explode(':', substr($encrypted, strlen(self::PREFIX)));

        if (count($parts) !== 3) {
            throw DecryptionException::invalidPayload();
        }

        $encryptedAad = base64_decode($parts[1], true);
        $value = base64_decode($parts[2], true);

        if (
            $encryptedAad === false
            || $value === false
        ) {
            throw DecryptionException::invalidPayload();
        }

        if ($encryptedAad !== $aad) {
            throw new DecryptionException(
                message: "FakeEncryptor: decrypt() was given associated data '$aad', "
                    . "but the value was encrypted with '$encryptedAad'",
                context: 'Decrypting with different associated data than was used for encryption; '
                    . 'a real AEAD encryptor rejects this too',
                suggestion: 'Pass the same $aad to decrypt() that was passed to encrypt() for this value',
            );
        }

        $this->decrypted[] = ['encrypted' => $encrypted, 'aad' => $aad, 'value' => $value];

        return $value;
    }

    /**
     * Assert encrypt() was called, optionally with this value and/or associated data.
     *
     * @throws AssertionFailedException
     */
    public function assertEncrypted(
        ?string $value = null,
        ?string $aad = null,
    ): void {
        $this->assertRecorded($this->encrypted, 'encrypted value', $value, $aad);
    }

    /**
     * Assert decrypt() succeeded, optionally returning this value and/or with this associated data.
     *
     * @throws AssertionFailedException
     */
    public function assertDecrypted(
        ?string $value = null,
        ?string $aad = null,
    ): void {
        $this->assertRecorded($this->decrypted, 'decrypted value', $value, $aad);
    }

    /**
     * @throws AssertionFailedException
     */
    public function assertNothingEncrypted(): void
    {
        if ($this->encrypted !== []) {
            throw AssertionFailedException::expectedEmpty('encrypted values');
        }
    }

    /**
     * @throws AssertionFailedException
     */
    public function assertNothingDecrypted(): void
    {
        if ($this->decrypted !== []) {
            throw AssertionFailedException::expectedEmpty('decrypted values');
        }
    }

    public function clear(): void
    {
        $this->encrypted = [];
        $this->decrypted = [];
    }

    /**
     * @param list<array{value: string, aad: string, encrypted: string}> $calls
     *
     * @throws AssertionFailedException
     */
    private function assertRecorded(
        array $calls,
        string $type,
        ?string $value,
        ?string $aad,
    ): void {
        if ($calls === []) {
            throw AssertionFailedException::unexpectedEmpty($type);
        }

        $found = array_any(
            $calls,
            fn (array $call): bool => ($value === null
                || $call['value'] === $value)
                && (
                    $aad === null
                    || $call['aad'] === $aad
                ),
        );

        if ($found) {
            return;
        }

        $criteria = [];

        if ($value !== null) {
            $criteria[] = "value '$value'";
        }

        if ($aad !== null) {
            $criteria[] = "associated data '$aad'";
        }

        throw AssertionFailedException::expectedContains($type, implode(' with ', $criteria));
    }
}
