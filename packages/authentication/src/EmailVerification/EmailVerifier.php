<?php

declare(strict_types=1);

namespace Marko\Authentication\EmailVerification;

use Marko\Authentication\Config\AuthConfig;
use Marko\Authentication\Contracts\EmailVerifierInterface;
use Marko\Authentication\Contracts\VerificationNotifierInterface;
use Marko\Authentication\Event\EmailVerifiedEvent;
use Marko\Authentication\Exceptions\AuthException;
use Marko\Authentication\Exceptions\UnauthenticatedException;
use Marko\Authentication\Exceptions\UserPersisterException;
use Marko\Authentication\MustVerifyEmailInterface;
use Marko\Authentication\UserPersisterResolver;
use Marko\Config\Exceptions\ConfigNotFoundException;
use Marko\Core\Event\EventDispatcherInterface;
use Marko\RateLimiter\Contracts\RateLimiterInterface;
use Marko\Routing\Exceptions\InvalidSignatureException;
use Marko\Routing\Exceptions\UrlGenerationException;
use Marko\Routing\Exceptions\UrlSigningException;
use Marko\Routing\Http\Request;
use Marko\Routing\Signing\UrlSignerInterface;
use Psr\Clock\ClockInterface;

/**
 * Sends signed email verification links and verifies the users who follow them.
 *
 * A link points at the route named authentication.verification.verify_route,
 * carries the user's id and a SHA-256 hash of their email as `id` and `hash`
 * query parameters, and is signed by UrlSignerInterface to expire after
 * authentication.verification.expire_seconds. Changing the email changes the
 * hash, so links sent to the old address stop working.
 *
 * verify() acts on the logged-in user of the default guard, so the verify
 * route belongs behind AuthMiddleware. It saves the user through the
 * persister of the guard's user provider.
 */
readonly class EmailVerifier implements EmailVerifierInterface
{
    public function __construct(
        private AuthConfig $authConfig,
        private CurrentVerifiableUser $currentUser,
        private UserPersisterResolver $userPersisterResolver,
        private UrlSignerInterface $urlSigner,
        private VerificationNotifierInterface $verificationNotifier,
        private RateLimiterInterface $rateLimiter,
        private EventDispatcherInterface $eventDispatcher,
        private ClockInterface $clock,
    ) {}

    /**
     * @throws AuthException|ConfigNotFoundException|UrlGenerationException|UrlSigningException
     */
    public function sendVerificationLink(
        MustVerifyEmailInterface $user,
    ): VerificationLinkStatus {
        if ($user->hasVerifiedEmail()) {
            return VerificationLinkStatus::AlreadyVerified;
        }

        $attempt = $this->rateLimiter->attempt(
            'email_verification:' . $user::class . ':' . $user->getAuthIdentifier(),
            1,
            $this->authConfig->verificationThrottleSeconds(),
        );

        if (!$attempt->allowed()) {
            return VerificationLinkStatus::Throttled;
        }

        $url = $this->urlSigner->signedRoute(
            $this->authConfig->verificationVerifyRoute(),
            [
                'id' => (string) $user->getAuthIdentifier(),
                'hash' => $this->emailHash($user),
            ],
            $this->clock->now()->modify('+' . $this->authConfig->verificationExpireSeconds() . ' seconds'),
        );
        $this->verificationNotifier->send($user, $url);

        return VerificationLinkStatus::Sent;
    }

    /**
     * @throws InvalidSignatureException|UnauthenticatedException|AuthException|UserPersisterException|UrlSigningException|ConfigNotFoundException
     */
    public function verify(
        Request $request,
    ): VerificationStatus {
        $user = $this->currentUser->get();
        $this->urlSigner->assertValidSignature($request);

        $id = $request->query('id');
        $hash = $request->query('hash');

        if (
            !is_string($id)
            || !is_string($hash)
            || !hash_equals((string) $user->getAuthIdentifier(), $id)
            || !hash_equals($this->emailHash($user), $hash)
        ) {
            throw InvalidSignatureException::invalid();
        }

        if ($user->hasVerifiedEmail()) {
            return VerificationStatus::AlreadyVerified;
        }

        $guard = $this->currentUser->guardName();
        $userPersister = $this->userPersisterResolver->forGuardProvider(
            $guard,
            $this->authConfig->guardProviderName($guard, $this->authConfig->guardConfig($guard)),
        );

        $user->markEmailAsVerified($this->clock->now());
        $userPersister->persist($user);
        $this->eventDispatcher->dispatch(new EmailVerifiedEvent($user));

        return VerificationStatus::Verified;
    }

    private function emailHash(
        MustVerifyEmailInterface $user,
    ): string {
        return hash('sha256', $user->getEmailForVerification());
    }
}
