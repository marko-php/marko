<?php

declare(strict_types=1);

namespace Marko\Authentication\Guard;

use DateMalformedStringException;
use DateTimeImmutable;
use Marko\Authentication\AuthenticatableInterface;
use Marko\Authentication\Contracts\CookieJarInterface;
use Marko\Authentication\Contracts\GuardInterface;
use Marko\Authentication\Contracts\LoginThrottleInterface;
use Marko\Authentication\Contracts\OtherDeviceLogoutInterface;
use Marko\Authentication\Contracts\RememberTokenStorageInterface;
use Marko\Authentication\Contracts\UserProviderInterface;
use Marko\Authentication\Event\FailedLoginEvent;
use Marko\Authentication\Event\LoginEvent;
use Marko\Authentication\Event\LogoutEvent;
use Marko\Authentication\Event\OtherDevicesLoggedOutEvent;
use Marko\Authentication\Exceptions\AuthException;
use Marko\Authentication\Exceptions\TooManyLoginAttemptsException;
use Marko\Authentication\Exceptions\UserPersisterException;
use Marko\Authentication\HasSessionVersionInterface;
use Marko\Authentication\Http\CurrentRequest;
use Marko\Authentication\Token\RememberTokenManager;
use Marko\Authentication\Token\RememberTokenRecord;
use Marko\Authentication\UserPersisterResolver;
use Marko\Config\Exceptions\ConfigNotFoundException;
use Marko\Core\Contracts\ResettableInterface;
use Marko\Core\Event\EventDispatcherInterface;
use Marko\Session\Contracts\SessionInterface;
use Override;
use Random\RandomException;

class SessionGuard implements GuardInterface, OtherDeviceLogoutInterface, ResettableInterface
{
    private ?AuthenticatableInterface $cachedUser = null;

    public function __construct(
        private readonly SessionInterface $session,
        public UserProviderInterface $provider {
            set {
                $this->provider = $value;
            }
        },
        private readonly string $name = 'session',
        private readonly ?CookieJarInterface $cookieJar = null,
        private readonly ?RememberTokenManager $tokenManager = null,
        private readonly ?EventDispatcherInterface $eventDispatcher = null,
        private readonly string $rememberCookiePrefix = 'remember_',
        private readonly ?LoginThrottleInterface $loginThrottle = null,
        private readonly ?RememberTokenStorageInterface $rememberTokenStorage = null,
        private readonly ?CurrentRequest $currentRequest = null,
        private readonly ?UserPersisterResolver $userPersisterResolver = null,
        private readonly ?string $providerName = null,
    ) {}

    /**
     * @throws AuthException|DateMalformedStringException|RandomException
     */
    public function check(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @throws AuthException|DateMalformedStringException|RandomException
     */
    public function guest(): bool
    {
        return !$this->check();
    }

    /**
     * @throws AuthException|DateMalformedStringException|RandomException
     */
    public function user(): ?AuthenticatableInterface
    {
        if ($this->cachedUser !== null) {
            return $this->cachedUser;
        }

        $id = $this->session->get($this->getSessionKey());

        if ($id !== null) {
            $user = $this->provider->retrieveById($id);

            if ($user === null || !$this->hasStaleSessionVersion($user)) {
                $this->cachedUser = $user;

                return $this->cachedUser;
            }

            // The user logged out other devices since this session started
            $this->endStaleSession();
        }

        // Try to authenticate via remember token
        $this->cachedUser = $this->authenticateViaRememberCookie();

        return $this->cachedUser;
    }

    /**
     * Whether the session was started before the user's session version last changed.
     * A session without a stored version predates versioning and matches an unchanged
     * (empty) version.
     */
    private function hasStaleSessionVersion(
        AuthenticatableInterface $user,
    ): bool {
        if (!$user instanceof HasSessionVersionInterface) {
            return false;
        }

        $sessionVersion = $this->session->get($this->getSessionVersionKey());

        return (is_string($sessionVersion) ? $sessionVersion : '') !== $user->getSessionVersion();
    }

    /**
     * Log out a session whose version is stale, deleting its data under a new ID.
     */
    private function endStaleSession(): void
    {
        $this->session->remove($this->getSessionKey());
        $this->session->remove($this->getSessionVersionKey());
        $this->session->regenerate(true);
    }

    /**
     * Log the user in from the remember cookie, once per session.
     *
     * A valid, unexpired cookie goes through the same session login as a
     * password login (session key, regenerated ID, LoginEvent), so later
     * requests authenticate from the session and the cookie is not consumed
     * again until that session ends.
     *
     * With a RememberTokenStorageInterface bound, the cookie is a per-device
     * "selector:validator" token. Without one (or for a cookie issued before
     * the storage was installed) it is "id|token", checked against the user's
     * single remember_token column.
     *
     * @throws AuthException|DateMalformedStringException|RandomException
     */
    private function authenticateViaRememberCookie(): ?AuthenticatableInterface
    {
        if ($this->cookieJar === null || $this->tokenManager === null) {
            return null;
        }

        $cookieValue = $this->cookieJar->get($this->getRememberCookieName());

        if ($cookieValue === null) {
            return null;
        }

        if (str_contains($cookieValue, '|')) {
            return $this->authenticateViaUserToken($cookieValue, $this->cookieJar, $this->tokenManager);
        }

        if ($this->rememberTokenStorage !== null) {
            return $this->authenticateViaDeviceToken(
                $cookieValue,
                $this->cookieJar,
                $this->tokenManager,
                $this->rememberTokenStorage,
            );
        }

        return null;
    }

    /**
     * The single-column path: an "id|token" cookie checked against the user's remember_token.
     *
     * @throws AuthException|DateMalformedStringException|RandomException
     */
    private function authenticateViaUserToken(
        string $cookieValue,
        CookieJarInterface $cookieJar,
        RememberTokenManager $tokenManager,
    ): ?AuthenticatableInterface {
        [$id, $token] = explode('|', $cookieValue, 2);

        // Providers store (and compare against) the hash, never the plain token
        $user = $this->provider->retrieveByRememberToken($id, $tokenManager->hash($token));

        if ($user === null) {
            return null;
        }

        // Validate the token
        $storedHash = $user->getRememberToken();

        if ($storedHash === null || !$tokenManager->validate($token, $storedHash)) {
            return null;
        }

        // The expiry lives server-side: the cookie's own Expires is client-controlled
        $expiresAt = $user->getRememberTokenExpiresAt();

        if ($expiresAt === null || $tokenManager->hasExpired($expiresAt)) {
            $this->provider->updateRememberToken($user, null, null);
            $cookieJar->delete($this->getRememberCookieName());

            return null;
        }

        $this->startSession($user);

        if ($this->rememberTokenStorage !== null) {
            // Per-device storage is now installed: retire the column token, and the
            // rotation below moves this device onto its own token
            $this->provider->updateRememberToken($user, null, null);
        }

        // Rotate the token (prevents replay) but keep the original expiry, so a
        // remember-me login has a fixed lifetime however often it is used
        $this->createRememberToken($user, $expiresAt);

        $this->dispatchLoginEvent($user, true);

        return $user;
    }

    /**
     * The per-device path: a "selector:validator" cookie checked against this device's stored token.
     *
     * @throws AuthException|RandomException
     */
    private function authenticateViaDeviceToken(
        string $cookieValue,
        CookieJarInterface $cookieJar,
        RememberTokenManager $tokenManager,
        RememberTokenStorageInterface $rememberTokenStorage,
    ): ?AuthenticatableInterface {
        $parts = explode(':', $cookieValue, 2);

        if (count($parts) !== 2 || $parts[0] === '' || $parts[1] === '') {
            return null;
        }

        [$selector, $validator] = $parts;
        $record = $rememberTokenStorage->findBySelector($this->name, $selector);

        if ($record === null) {
            // Revoked, purged or never issued: this cookie can never log in again
            $cookieJar->delete($this->getRememberCookieName());

            return null;
        }

        // A wrong validator is a tampered cookie, or a request that lost a rotation
        // race to another request from the same device: reject it, keep the token
        if (!$tokenManager->validate($validator, $record->validatorHash)) {
            return null;
        }

        // The expiry lives server-side: the cookie's own Expires is client-controlled
        if ($tokenManager->hasExpired($record->expiresAt)) {
            $rememberTokenStorage->deleteBySelector($this->name, $selector);
            $cookieJar->delete($this->getRememberCookieName());

            return null;
        }

        $user = $this->provider->retrieveById($record->userId);

        if ($user === null) {
            $rememberTokenStorage->deleteBySelector($this->name, $selector);
            $cookieJar->delete($this->getRememberCookieName());

            return null;
        }

        // Rotate only the validator (prevents replay). The selector and expiry are
        // kept, so a remember-me login has a fixed lifetime however often it is used
        $newValidator = $tokenManager->generate();
        $rotated = $rememberTokenStorage->rotateValidator(
            $this->name,
            $selector,
            $record->validatorHash,
            $tokenManager->hash($newValidator),
        );

        if (!$rotated) {
            // Another request from this device rotated it first and logs it in
            return null;
        }

        $this->startSession($user);

        $cookieJar->set(
            $this->getRememberCookieName(),
            $selector . ':' . $newValidator,
            $tokenManager->minutesUntil($record->expiresAt),
        );

        $this->dispatchLoginEvent($user, true);

        return $user;
    }

    /**
     * @throws AuthException|DateMalformedStringException|RandomException
     */
    public function id(): int|string|null
    {
        return $this->user()?->getAuthIdentifier();
    }

    /**
     * Throttled by the login throttle when one is set: a locked-out attempt
     * throws before the user is looked up, every failure is counted, and a
     * success clears the count.
     *
     * @param array<string, mixed> $credentials
     *
     * @throws AuthException|DateMalformedStringException|RandomException|TooManyLoginAttemptsException
     */
    public function attempt(
        array $credentials,
    ): bool {
        $this->loginThrottle?->ensureNotLockedOut($this->name, $credentials);

        $user = $this->provider->retrieveByCredentials($credentials);

        if ($user === null) {
            $this->dispatchFailedLoginEvent($credentials);

            return false;
        }

        if (!$this->provider->validateCredentials($user, $credentials)) {
            $this->dispatchFailedLoginEvent($credentials);

            return false;
        }

        // Upgrade the stored hash while the plain password is at hand (cost or algorithm changed)
        $this->provider->rehashPasswordIfNeeded($user, $credentials);

        $this->loginThrottle?->clear($this->name, $credentials);
        $this->login($user);

        return true;
    }

    /**
     * @param array<string, mixed> $credentials
     */
    private function dispatchFailedLoginEvent(
        array $credentials,
    ): void {
        // Every failed attempt counts toward the throttle, whether or not the user exists
        $this->loginThrottle?->recordFailure($this->name, $credentials);

        $this->eventDispatcher?->dispatch(new FailedLoginEvent(
            credentials: $credentials,
            guard: $this->name,
        ));
    }

    /**
     * @throws AuthException|DateMalformedStringException|RandomException
     */
    public function login(
        AuthenticatableInterface $user,
        bool $remember = false,
    ): void {
        $this->startSession($user);
        $this->cachedUser = $user;

        if ($remember) {
            $this->createRememberToken($user);
        }

        $this->dispatchLoginEvent($user, $remember);
    }

    /**
     * Store the user in the session under a fresh session ID (prevents fixation).
     *
     * @throws AuthException
     */
    private function startSession(
        AuthenticatableInterface $user,
    ): void {
        $this->ensureSessionAvailable();
        $this->session->set($this->getSessionKey(), $user->getAuthIdentifier());

        if ($user instanceof HasSessionVersionInterface) {
            $this->session->set($this->getSessionVersionKey(), $user->getSessionVersion());
        } else {
            $this->session->remove($this->getSessionVersionKey());
        }

        $this->session->regenerate();
    }

    private function dispatchLoginEvent(
        AuthenticatableInterface $user,
        bool $remember,
    ): void {
        $this->eventDispatcher?->dispatch(new LoginEvent(
            user: $user,
            guard: $this->name,
            remember: $remember,
        ));
    }

    /**
     * Issue a new remember token, expiring at $expiresAt (default: a full lifetime from now).
     *
     * @throws AuthException|DateMalformedStringException|RandomException
     */
    private function createRememberToken(
        AuthenticatableInterface $user,
        ?DateTimeImmutable $expiresAt = null,
    ): void {
        if ($this->cookieJar === null || $this->tokenManager === null) {
            throw AuthException::rememberMeUnavailable($this->name);
        }

        $expiresAt ??= $this->tokenManager->expiresAt();

        $cookieValue = $this->rememberTokenStorage !== null
            ? $this->storeDeviceToken($user, $expiresAt, $this->tokenManager, $this->rememberTokenStorage)
            : $this->storeUserToken($user, $expiresAt, $this->tokenManager);

        $this->cookieJar->set(
            $this->getRememberCookieName(),
            $cookieValue,
            $this->tokenManager->minutesUntil($expiresAt),
        );
    }

    /**
     * Store a token for this device only, returning the "selector:validator" cookie value.
     *
     * @throws RandomException
     */
    private function storeDeviceToken(
        AuthenticatableInterface $user,
        DateTimeImmutable $expiresAt,
        RememberTokenManager $tokenManager,
        RememberTokenStorageInterface $rememberTokenStorage,
    ): string {
        $selector = $tokenManager->generateSelector();
        $validator = $tokenManager->generate();

        $rememberTokenStorage->store(new RememberTokenRecord(
            guard: $this->name,
            userId: $user->getAuthIdentifier(),
            selector: $selector,
            validatorHash: $tokenManager->hash($validator),
            expiresAt: $expiresAt,
            userAgent: $this->currentUserAgent(),
        ));

        return $selector . ':' . $validator;
    }

    /**
     * Store the token in the user's single remember_token column, returning the "id|token" cookie value.
     *
     * @throws AuthException|RandomException
     */
    private function storeUserToken(
        AuthenticatableInterface $user,
        DateTimeImmutable $expiresAt,
        RememberTokenManager $tokenManager,
    ): string {
        $token = $tokenManager->generate();
        $hashedToken = $tokenManager->hash($token);

        $this->provider->updateRememberToken($user, $hashedToken, $expiresAt);

        if (
            $user->getRememberToken() !== $hashedToken
            || $user->getRememberTokenExpiresAt()?->getTimestamp() !== $expiresAt->getTimestamp()
        ) {
            throw AuthException::rememberTokenNotStored($this->name, $this->provider::class);
        }

        return $user->getAuthIdentifier() . '|' . $token;
    }

    /**
     * The requesting browser's User-Agent, stored with a device token to tell a user's devices apart.
     */
    private function currentUserAgent(): ?string
    {
        $userAgent = $this->currentRequest?->get()?->header('User-Agent');

        return $userAgent === null || $userAgent === '' ? null : mb_substr($userAgent, 0, 255);
    }

    private function getSessionKey(): string
    {
        return 'auth_' . $this->name . '_user_id';
    }

    private function getSessionVersionKey(): string
    {
        return 'auth_' . $this->name . '_session_version';
    }

    private function getRememberCookieName(): string
    {
        return $this->rememberCookiePrefix . $this->name;
    }

    /**
     * @throws AuthException
     */
    private function ensureSessionAvailable(): void
    {
        if (!$this->session->isAvailable()) {
            throw new AuthException(
                message: 'Session not started',
                context: 'SessionGuard requires an active session',
                suggestion: 'Ensure the session middleware is applied before authentication',
            );
        }
    }

    /**
     * @throws AuthException|DateMalformedStringException|RandomException
     */
    public function loginById(
        int|string $id,
    ): ?AuthenticatableInterface {
        $user = $this->provider->retrieveById($id);

        if ($user === null) {
            return null;
        }

        $this->login($user);

        return $user;
    }

    /**
     * @throws AuthException|DateMalformedStringException|RandomException
     */
    public function logout(): void
    {
        $user = $this->user();

        if ($user !== null && $this->cookieJar !== null && $this->tokenManager !== null) {
            $this->forgetRememberToken($user, $this->cookieJar);
        }

        $this->session->remove($this->getSessionKey());
        $this->session->remove($this->getSessionVersionKey());
        $this->cachedUser = null;

        if ($user !== null) {
            // A new session ID, with the old session deleted, so the logged-out ID
            // cannot be reused (shared machines, planted session cookies)
            $this->session->regenerate(true);
            $this->dispatchLogoutEvent($user);
        }
    }

    /**
     * Revoke this device's remember token and delete its cookie. With per-device
     * storage the user's other devices stay remembered.
     */
    private function forgetRememberToken(
        AuthenticatableInterface $user,
        CookieJarInterface $cookieJar,
    ): void {
        if ($this->rememberTokenStorage === null) {
            $this->provider->updateRememberToken($user, null, null);
        } else {
            $this->deleteDeviceToken($user, $cookieJar, $this->rememberTokenStorage);
        }

        $cookieJar->delete($this->getRememberCookieName());
    }

    private function deleteDeviceToken(
        AuthenticatableInterface $user,
        CookieJarInterface $cookieJar,
        RememberTokenStorageInterface $rememberTokenStorage,
    ): void {
        $selector = $this->ownDeviceSelector($user, $cookieJar, $rememberTokenStorage);

        if ($selector !== null) {
            $rememberTokenStorage->deleteBySelector($this->name, $selector);
        }
    }

    /**
     * The selector of this device's stored token, when the remember cookie holds one
     * issued to $user. A planted cookie naming another user's device yields null, so
     * it can neither revoke nor protect that device.
     */
    private function ownDeviceSelector(
        AuthenticatableInterface $user,
        CookieJarInterface $cookieJar,
        RememberTokenStorageInterface $rememberTokenStorage,
    ): ?string {
        $cookieValue = $cookieJar->get($this->getRememberCookieName());

        if ($cookieValue === null || str_contains($cookieValue, '|') || !str_contains($cookieValue, ':')) {
            return null;
        }

        $selector = explode(':', $cookieValue, 2)[0];
        $record = $rememberTokenStorage->findBySelector($this->name, $selector);

        if ($record === null || (string) $record->userId !== (string) $user->getAuthIdentifier()) {
            return null;
        }

        return $selector;
    }

    /**
     * End the user's other sessions and remember-me devices on this guard.
     *
     * Sessions: a user implementing HasSessionVersionInterface gets a new session
     * version, saved through the user persister of the guard's provider. Every session
     * holding the old version is logged out on its next request; the current session
     * is moved to the new version when it belongs to this user.
     *
     * Remember-me: with per-device storage, every device token of the user except this
     * device's is deleted. Without it, the user's single column token is rotated when
     * this device holds it (keeping its expiry), or cleared otherwise, so no other
     * device's cookie matches it again.
     *
     * @throws AuthException|ConfigNotFoundException|DateMalformedStringException|RandomException|UserPersisterException
     */
    #[Override]
    public function logoutOtherDevices(
        AuthenticatableInterface $user,
    ): void {
        $currentUser = $this->user();
        $isCurrentUser = $currentUser !== null
            && (string) $currentUser->getAuthIdentifier() === (string) $user->getAuthIdentifier();

        if ($user instanceof HasSessionVersionInterface) {
            $this->changeSessionVersion($user, $isCurrentUser);
        }

        if ($this->rememberTokenStorage !== null) {
            $this->rememberTokenStorage->deleteAllForUser(
                $this->name,
                $user->getAuthIdentifier(),
                $this->cookieJar === null
                    ? null
                    : $this->ownDeviceSelector($user, $this->cookieJar, $this->rememberTokenStorage),
            );
        } else {
            $this->replaceUserToken($user);
        }

        $this->eventDispatcher?->dispatch(new OtherDevicesLoggedOutEvent(
            user: $user,
            guard: $this->name,
        ));
    }

    /**
     * @throws AuthException|ConfigNotFoundException|RandomException|UserPersisterException
     */
    private function changeSessionVersion(
        HasSessionVersionInterface $user,
        bool $isCurrentUser,
    ): void {
        if ($this->userPersisterResolver === null) {
            throw AuthException::sessionVersionUnpersistable($this->name);
        }

        $userPersister = $this->userPersisterResolver->forGuardProvider($this->name, $this->providerName);
        $version = bin2hex(random_bytes(16));

        $user->setSessionVersion($version);
        $userPersister->persist($user);

        if ($isCurrentUser) {
            $this->session->set($this->getSessionVersionKey(), $version);
        }
    }

    /**
     * Invalidate the single-column remember token on every other device: rotate it when
     * this device's "id|token" cookie holds it, otherwise clear it.
     *
     * @throws AuthException|DateMalformedStringException|RandomException
     */
    private function replaceUserToken(
        AuthenticatableInterface $user,
    ): void {
        $expiresAt = $user->getRememberTokenExpiresAt();

        if ($expiresAt !== null && $this->holdsUserToken($user)) {
            $this->createRememberToken($user, $expiresAt);

            return;
        }

        $this->provider->updateRememberToken($user, null, null);
    }

    /**
     * Whether this device's remember cookie carries $user's current, unexpired column token.
     */
    private function holdsUserToken(
        AuthenticatableInterface $user,
    ): bool {
        if ($this->cookieJar === null || $this->tokenManager === null) {
            return false;
        }

        $cookieValue = $this->cookieJar->get($this->getRememberCookieName());

        if ($cookieValue === null || !str_contains($cookieValue, '|')) {
            return false;
        }

        [$id, $token] = explode('|', $cookieValue, 2);
        $storedHash = $user->getRememberToken();
        $expiresAt = $user->getRememberTokenExpiresAt();

        return $id === (string) $user->getAuthIdentifier()
            && $storedHash !== null
            && $expiresAt !== null
            && $this->tokenManager->validate($token, $storedHash)
            && !$this->tokenManager->hasExpired($expiresAt);
    }

    private function dispatchLogoutEvent(
        AuthenticatableInterface $user,
    ): void {
        $this->eventDispatcher?->dispatch(new LogoutEvent(
            user: $user,
            guard: $this->name,
        ));
    }

    public function getName(): string
    {
        return $this->name;
    }

    #[Override]
    public function reset(): void
    {
        $this->cachedUser = null;
    }
}
