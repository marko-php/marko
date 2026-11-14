<?php

declare(strict_types=1);

namespace Marko\Authentication\Hashing;

use Marko\Authentication\Contracts\PasswordHasherInterface;
use Marko\Authentication\Exceptions\InvalidPasswordException;

class BcryptPasswordHasher implements PasswordHasherInterface
{
    public const int DEFAULT_COST = 12;

    /**
     * Bcrypt ignores every byte past the 72nd, so longer passwords are rejected rather than truncated.
     */
    public const int MAX_PASSWORD_BYTES = 72;

    /**
     * Salt and digest of a bcrypt hash no user password is expected to match; the cost prefix is
     * added per instance so a dummy verification costs exactly as much as a real one.
     */
    private const string DUMMY_HASH_BODY = 'ZxSPF.27pFWWE6Ew0jDzE.HiJNsq8davb61hvjFFesMqHyLDmjNbe';

    private int $cost;

    public function __construct(
        ?int $cost = null,
    ) {
        $this->cost = $cost ?? self::DEFAULT_COST;
    }

    /**
     * @throws InvalidPasswordException When the password is longer than 72 bytes or contains a NUL byte
     */
    public function hash(
        string $password,
    ): string {
        if (strlen($password) > self::MAX_PASSWORD_BYTES) {
            throw InvalidPasswordException::tooLong(self::MAX_PASSWORD_BYTES, strlen($password));
        }

        if (str_contains($password, "\0")) {
            throw InvalidPasswordException::containsNulByte();
        }

        return password_hash($password, PASSWORD_BCRYPT, ['cost' => $this->cost]);
    }

    /**
     * Returns false for a password bcrypt cannot hash in full (over 72 bytes or containing a NUL byte),
     * since no hash() result can belong to it. The dummy hash is still checked so rejecting such a
     * password costs the same time as checking a real one.
     */
    public function verify(
        string $password,
        string $hash,
    ): bool {
        if (!$this->isHashable($password)) {
            $this->verifyDummy($password);

            return false;
        }

        return password_verify($password, $hash);
    }

    public function needsRehash(
        string $hash,
    ): bool {
        return password_needs_rehash($hash, PASSWORD_BCRYPT, ['cost' => $this->cost]);
    }

    public function verifyDummy(
        string $password,
    ): void {
        password_verify($password, $this->dummyHash());
    }

    private function isHashable(
        string $password,
    ): bool {
        return strlen($password) <= self::MAX_PASSWORD_BYTES
            && !str_contains($password, "\0");
    }

    private function dummyHash(): string
    {
        return sprintf('$2y$%02d$%s', $this->cost, self::DUMMY_HASH_BODY);
    }
}
