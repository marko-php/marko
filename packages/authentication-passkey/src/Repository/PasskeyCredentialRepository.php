<?php

declare(strict_types=1);

namespace Marko\AuthenticationPasskey\Repository;

use Marko\AuthenticationPasskey\Contracts\PasskeyCredentialRepositoryInterface;
use Marko\AuthenticationPasskey\Entity\PasskeyCredential;
use Marko\Database\Exceptions\EntityException;
use Marko\Database\Repository\Repository;

/**
 * Passkeys in the passkey_credentials table. save() and delete() are the
 * marko/database Repository's own.
 *
 * @extends Repository<PasskeyCredential>
 */
class PasskeyCredentialRepository extends Repository implements PasskeyCredentialRepositoryInterface
{
    protected const string ENTITY_CLASS = PasskeyCredential::class;

    /**
     * @throws EntityException
     */
    public function findByCredentialId(
        string $id,
    ): ?PasskeyCredential {
        return $this->findOneBy(['credentialId' => $id]);
    }

    /**
     * @return list<PasskeyCredential>
     * @throws EntityException
     */
    public function forUser(
        string $provider,
        int|string $userId,
    ): array {
        $credentials = $this->findBy(['provider' => $provider, 'userId' => (string) $userId])->toArray();

        usort(
            $credentials,
            static fn (PasskeyCredential $a, PasskeyCredential $b): int => $a->id <=> $b->id,
        );

        return $credentials;
    }
}
