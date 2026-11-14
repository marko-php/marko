<?php

declare(strict_types=1);

namespace Marko\Authentication\Hashing;

use Marko\Authentication\Contracts\PasswordHasherInterface;
use Marko\Authentication\Exceptions\InvalidPasswordException;
use Marko\Hashing\Exceptions\HasherNotFoundException;
use Marko\Hashing\HashManager;
use Random\RandomException;

/**
 * Password hasher backed by marko/hashing, so its configured driver (HASH_DRIVER) governs login passwords.
 *
 * The authentication module binds PasswordHasherInterface to this class when marko/hashing is loaded.
 * Hashes from any algorithm password_verify() understands still verify, and needsRehash() reports
 * every hash the configured driver did not make, so stored hashes migrate on the next login.
 */
class HashManagerPasswordHasher implements PasswordHasherInterface
{
    /**
     * Hash of a random value from the configured driver, made on the first dummy verification.
     */
    private ?string $dummyHash = null;

    public function __construct(
        private readonly HashManager $hashManager,
    ) {}

    /**
     * Under the bcrypt driver, a password longer than 72 bytes or containing a NUL byte throws
     * InvalidPasswordException, exactly as BcryptPasswordHasher does.
     *
     * @throws InvalidPasswordException|HasherNotFoundException
     */
    public function hash(
        string $password,
    ): string {
        if ($this->hashManager->hasher()->algorithm() === 'bcrypt') {
            if (strlen($password) > BcryptPasswordHasher::MAX_PASSWORD_BYTES) {
                throw InvalidPasswordException::tooLong(BcryptPasswordHasher::MAX_PASSWORD_BYTES, strlen($password));
            }

            if (str_contains($password, "\0")) {
                throw InvalidPasswordException::containsNulByte();
            }
        }

        return $this->hashManager->hash($password);
    }

    /**
     * Returns false for a password bcrypt cannot hash in full (over 72 bytes or containing a NUL byte)
     * when the stored hash is a bcrypt hash, whichever driver is configured: password_verify() would
     * otherwise accept anything sharing its first 72 bytes. The dummy hash is still checked so
     * rejecting such a password costs the same time as checking a real one.
     *
     * @throws HasherNotFoundException|RandomException
     */
    public function verify(
        string $password,
        string $hash,
    ): bool {
        // Every bcrypt variant ($2a$, $2b$, $2x$, $2y$); password_get_info() only recognises $2y$
        if (
            str_starts_with($hash, '$2')
            && !$this->isBcryptHashable($password)
        ) {
            $this->verifyDummy($password);

            return false;
        }

        return $this->hashManager->verify($password, $hash);
    }

    /**
     * @throws HasherNotFoundException
     */
    public function needsRehash(
        string $hash,
    ): bool {
        return $this->hashManager->needsRehash($hash);
    }

    /**
     * The first call hashes a random value with the configured driver, which costs the same as a
     * verification; later calls verify the password against that hash.
     *
     * @throws HasherNotFoundException|RandomException
     */
    public function verifyDummy(
        string $password,
    ): void {
        if ($this->dummyHash === null) {
            $this->dummyHash = $this->hashManager->hash(bin2hex(random_bytes(16)));

            return;
        }

        password_verify($password, $this->dummyHash);
    }

    private function isBcryptHashable(
        string $password,
    ): bool {
        return strlen($password) <= BcryptPasswordHasher::MAX_PASSWORD_BYTES
            && !str_contains($password, "\0");
    }
}
