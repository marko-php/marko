<?php

declare(strict_types=1);

namespace Marko\AuthenticationPasskey\Contracts;

use Marko\AuthenticationPasskey\Entity\PasskeyCredential;

/**
 * Stores registered passkeys. The default binding, PasskeyCredentialRepository,
 * keeps them in the passkey_credentials table through marko/database.
 */
interface PasskeyCredentialRepositoryInterface
{
    /**
     * The passkey with this base64url credential id, or null.
     */
    public function findByCredentialId(
        string $id,
    ): ?PasskeyCredential;

    /**
     * Every passkey of one user of one provider, oldest first.
     *
     * @return list<PasskeyCredential>
     */
    public function forUser(
        string $provider,
        int|string $userId,
    ): array;

    /**
     * Insert a new passkey or update a stored one.
     */
    public function save(
        PasskeyCredential $credential,
    ): void;

    public function delete(
        PasskeyCredential $credential,
    ): void;
}
