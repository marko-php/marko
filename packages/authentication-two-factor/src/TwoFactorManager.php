<?php

declare(strict_types=1);

namespace Marko\AuthenticationTwoFactor;

use DateMalformedStringException;
use Marko\Authentication\AuthManager;
use Marko\Authentication\Config\AuthConfig;
use Marko\Authentication\Exceptions\AuthException;
use Marko\Authentication\Exceptions\UserPersisterException;
use Marko\Authentication\UserPersisterResolver;
use Marko\AuthenticationTwoFactor\Config\TwoFactorConfig;
use Marko\AuthenticationTwoFactor\Contracts\TotpInterface;
use Marko\AuthenticationTwoFactor\Contracts\TwoFactorAuthenticatableInterface;
use Marko\AuthenticationTwoFactor\Contracts\TwoFactorManagerInterface;
use Marko\AuthenticationTwoFactor\Event\RecoveryCodeUsedEvent;
use Marko\AuthenticationTwoFactor\Event\TwoFactorDisabledEvent;
use Marko\AuthenticationTwoFactor\Event\TwoFactorEnabledEvent;
use Marko\AuthenticationTwoFactor\Exceptions\TwoFactorException;
use Marko\Config\Exceptions\ConfigNotFoundException;
use Marko\Core\Event\EventDispatcherInterface;
use Psr\Clock\ClockInterface;
use Random\RandomException;

/**
 * Enables, confirms, verifies and disables two-factor authentication.
 *
 * Recovery codes look like "a1b2c-3d4e5" (40 random bits, single use). They are
 * stored only as SHA-256 hashes of their lowercase form: the app never shows
 * them again, so they need not be reversible like the TOTP secret.
 */
readonly class TwoFactorManager implements TwoFactorManagerInterface
{
    private const int RECOVERY_CODE_HALF_BYTES = 5;

    public function __construct(
        private AuthManager $authManager,
        private AuthConfig $authConfig,
        private UserPersisterResolver $userPersisterResolver,
        private TotpInterface $totp,
        private TwoFactorConfig $twoFactorConfig,
        private EventDispatcherInterface $eventDispatcher,
        private ClockInterface $clock,
    ) {}

    /**
     * @throws AuthException|ConfigNotFoundException|RandomException|TwoFactorException|UserPersisterException
     */
    public function enable(
        TwoFactorAuthenticatableInterface $user,
        string $label,
        ?string $guard = null,
    ): TwoFactorSetup {
        if ($this->isEnabled($user)) {
            throw TwoFactorException::alreadyEnabled($user->getAuthIdentifier());
        }

        $issuer = $this->twoFactorConfig->issuer();
        $secret = $this->totp->generateSecret();
        $recoveryCodes = $this->generateRecoveryCodes();

        $user->setTwoFactorSecret($secret);
        $user->setTwoFactorRecoveryCodes($this->hashRecoveryCodes($recoveryCodes));
        $user->setTwoFactorConfirmedAt(null);
        $user->setTwoFactorLastUsedStep(null);
        $this->persist($user, $guard);

        return new TwoFactorSetup(
            secret: $secret,
            provisioningUri: $this->totp->provisioningUri($secret, $label, $issuer),
            recoveryCodes: $recoveryCodes,
        );
    }

    /**
     * Activating two-factor also ends the user's other sessions and remember-me
     * devices on the guard (AuthManager::logoutOtherDevices()), since none of
     * them passed a two-factor challenge.
     *
     * @throws AuthException|ConfigNotFoundException|DateMalformedStringException|RandomException|TwoFactorException|UserPersisterException
     */
    public function confirm(
        TwoFactorAuthenticatableInterface $user,
        string $code,
        ?string $guard = null,
    ): bool {
        if ($this->isEnabled($user)) {
            throw TwoFactorException::alreadyEnabled($user->getAuthIdentifier());
        }

        $secret = $user->getTwoFactorSecret();

        if ($secret === null) {
            throw TwoFactorException::setupNotStarted($user->getAuthIdentifier());
        }

        $step = $this->totp->verify($secret, $code, $user->getTwoFactorLastUsedStep());

        if ($step === null) {
            return false;
        }

        $user->setTwoFactorLastUsedStep($step);
        $user->setTwoFactorConfirmedAt($this->clock->now());
        $this->persist($user, $guard);
        // Sessions and remember-me cookies from before two-factor never passed it: end them,
        // keeping only the device that just confirmed
        $this->authManager->logoutOtherDevices($user, $guard);
        $this->eventDispatcher->dispatch(new TwoFactorEnabledEvent($user));

        return true;
    }

    /**
     * @throws AuthException|ConfigNotFoundException|UserPersisterException
     */
    public function disable(
        TwoFactorAuthenticatableInterface $user,
        ?string $guard = null,
    ): void {
        $wasEnabled = $this->isEnabled($user);

        $user->setTwoFactorSecret(null);
        $user->setTwoFactorRecoveryCodes([]);
        $user->setTwoFactorConfirmedAt(null);
        $user->setTwoFactorLastUsedStep(null);
        $this->persist($user, $guard);

        if ($wasEnabled) {
            $this->eventDispatcher->dispatch(new TwoFactorDisabledEvent($user));
        }
    }

    /**
     * @throws AuthException|ConfigNotFoundException|RandomException|TwoFactorException|UserPersisterException
     */
    public function regenerateRecoveryCodes(
        TwoFactorAuthenticatableInterface $user,
        ?string $guard = null,
    ): array {
        $this->ensureEnabled($user);

        $recoveryCodes = $this->generateRecoveryCodes();
        $user->setTwoFactorRecoveryCodes($this->hashRecoveryCodes($recoveryCodes));
        $this->persist($user, $guard);

        return $recoveryCodes;
    }

    public function isEnabled(
        TwoFactorAuthenticatableInterface $user,
    ): bool {
        return $user->getTwoFactorSecret() !== null && $user->getTwoFactorConfirmedAt() !== null;
    }

    /**
     * @throws AuthException|ConfigNotFoundException|TwoFactorException|UserPersisterException
     */
    public function verify(
        TwoFactorAuthenticatableInterface $user,
        string $code,
        ?string $guard = null,
    ): bool {
        $secret = $this->ensureEnabled($user);
        $step = $this->totp->verify($secret, $code, $user->getTwoFactorLastUsedStep());

        if ($step !== null) {
            $user->setTwoFactorLastUsedStep($step);
            $this->persist($user, $guard);

            return true;
        }

        return $this->useRecoveryCode($user, $code, $guard);
    }

    /**
     * Spend a matching recovery code. Every stored hash is compared, so the
     * time taken does not reveal which code matched.
     *
     * @throws AuthException|ConfigNotFoundException|UserPersisterException
     */
    private function useRecoveryCode(
        TwoFactorAuthenticatableInterface $user,
        string $code,
        ?string $guard,
    ): bool {
        $hash = $this->hashRecoveryCode($code);
        $remaining = [];
        $matched = false;

        foreach ($user->getTwoFactorRecoveryCodes() as $storedHash) {
            if (hash_equals($storedHash, $hash) && !$matched) {
                $matched = true;

                continue;
            }

            $remaining[] = $storedHash;
        }

        if (!$matched) {
            return false;
        }

        $user->setTwoFactorRecoveryCodes($remaining);
        $this->persist($user, $guard);
        $this->eventDispatcher->dispatch(new RecoveryCodeUsedEvent($user, count($remaining)));

        return true;
    }

    /**
     * @throws TwoFactorException
     */
    private function ensureEnabled(
        TwoFactorAuthenticatableInterface $user,
    ): string {
        $secret = $user->getTwoFactorSecret();

        if ($secret === null || $user->getTwoFactorConfirmedAt() === null) {
            throw TwoFactorException::notEnabled($user->getAuthIdentifier());
        }

        return $secret;
    }

    /**
     * @return list<string>
     * @throws ConfigNotFoundException|RandomException|TwoFactorException
     */
    private function generateRecoveryCodes(): array
    {
        $codes = [];

        while (count($codes) < $this->twoFactorConfig->recoveryCodeCount()) {
            $code = bin2hex(random_bytes(self::RECOVERY_CODE_HALF_BYTES))
                . '-' . bin2hex(random_bytes(self::RECOVERY_CODE_HALF_BYTES));
            $codes[$code] = $code;
        }

        return array_values($codes);
    }

    /**
     * @param list<string> $codes
     * @return list<string>
     */
    private function hashRecoveryCodes(
        array $codes,
    ): array {
        return array_map($this->hashRecoveryCode(...), $codes);
    }

    private function hashRecoveryCode(
        string $code,
    ): string {
        return hash('sha256', strtolower(trim($code)));
    }

    /**
     * Save through the persister of the guard's user provider.
     *
     * @throws AuthException|ConfigNotFoundException|UserPersisterException
     */
    private function persist(
        TwoFactorAuthenticatableInterface $user,
        ?string $guard,
    ): void {
        $guardName = $guard ?? $this->authConfig->defaultGuard();
        $providerName = $this->authConfig->guardProviderName($guardName, $this->authConfig->guardConfig($guardName));

        $this->userPersisterResolver->forGuardProvider($guardName, $providerName)->persist($user);
    }
}
