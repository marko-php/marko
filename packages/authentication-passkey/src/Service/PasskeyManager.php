<?php

declare(strict_types=1);

namespace Marko\AuthenticationPasskey\Service;

use Marko\Authentication\AuthenticatableInterface;
use Marko\Authentication\Contracts\PasswordConfirmationInterface;
use Marko\Authentication\Exceptions\AuthException;
use Marko\Authentication\Exceptions\PasswordConfirmationRequiredException;
use Marko\Authentication\Exceptions\UnauthenticatedException;
use Marko\AuthenticationPasskey\Contracts\PasskeyCredentialRepositoryInterface;
use Marko\AuthenticationPasskey\Entity\PasskeyCredential;
use Marko\AuthenticationPasskey\Event\PasskeyDeletedEvent;
use Marko\AuthenticationPasskey\Exceptions\InvalidPasskeyNameException;
use Marko\AuthenticationPasskey\Exceptions\PasskeyConfigurationException;
use Marko\AuthenticationPasskey\Exceptions\PasskeyNotFoundException;
use Marko\AuthenticationPasskey\Guard\PasskeyGuard;
use Marko\Config\Exceptions\ConfigNotFoundException;
use Marko\Core\Event\EventDispatcherInterface;

/**
 * The signed-in user's passkeys: list, rename and delete. Every method acts on
 * the signed-in user's own passkeys only; another user's credential id is
 * reported as not found.
 */
readonly class PasskeyManager
{
    public function __construct(
        private PasskeyGuard $passkeyGuard,
        private PasskeyCredentialRepositoryInterface $passkeyCredentialRepository,
        private PasswordConfirmationInterface $passwordConfirmation,
        private EventDispatcherInterface $eventDispatcher,
    ) {}

    /**
     * @return list<PasskeyCredential>
     * @throws UnauthenticatedException|PasskeyConfigurationException|AuthException|ConfigNotFoundException
     */
    public function forCurrentUser(): array
    {
        $user = $this->passkeyGuard->user();

        return $this->passkeyCredentialRepository->forUser(
            $this->passkeyGuard->providerName(),
            $user->getAuthIdentifier(),
        );
    }

    /**
     * @throws PasskeyNotFoundException|InvalidPasskeyNameException|UnauthenticatedException|PasskeyConfigurationException|AuthException|ConfigNotFoundException
     */
    public function rename(
        string $credentialId,
        string $name,
    ): PasskeyCredential {
        $name = PasskeyName::normalise($name);
        $passkey = $this->ownPasskey($this->passkeyGuard->user(), $credentialId);

        $passkey->name = $name;
        $this->passkeyCredentialRepository->save($passkey);

        return $passkey;
    }

    /**
     * Delete one of the user's passkeys. Needs a recent password confirmation
     * (a password, or a passkey through PasskeyAuthentication::confirm()):
     * put RequirePasswordConfirmationMiddleware on the route, and this
     * method enforces it too.
     *
     * @throws PasswordConfirmationRequiredException|PasskeyNotFoundException|UnauthenticatedException|PasskeyConfigurationException|AuthException|ConfigNotFoundException
     */
    public function delete(
        string $credentialId,
    ): void {
        $user = $this->passkeyGuard->user();

        if (!$this->passwordConfirmation->isRecentlyConfirmed()) {
            throw PasswordConfirmationRequiredException::create();
        }

        $passkey = $this->ownPasskey($user, $credentialId);
        $this->passkeyCredentialRepository->delete($passkey);

        $this->eventDispatcher->dispatch(new PasskeyDeletedEvent(
            user: $user,
            credential: $passkey,
            guard: $this->passkeyGuard->guard()->getName(),
        ));
    }

    /**
     * @throws PasskeyNotFoundException|PasskeyConfigurationException|ConfigNotFoundException
     */
    private function ownPasskey(
        AuthenticatableInterface $user,
        string $credentialId,
    ): PasskeyCredential {
        $passkey = $this->passkeyCredentialRepository->findByCredentialId($credentialId);

        if ($passkey === null || !$this->passkeyGuard->owns($user, $passkey)) {
            throw PasskeyNotFoundException::forCredentialId($credentialId);
        }

        return $passkey;
    }
}
