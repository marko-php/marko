<?php

declare(strict_types=1);

namespace Marko\Testing\Http;

use Closure;
use Marko\Authentication\AuthenticatableInterface;
use Marko\Authentication\AuthManager;
use Marko\Authentication\Contracts\StatelessGuardInterface;
use Marko\Session\Config\SessionConfig;
use Marko\Session\Contracts\SessionInterface;
use Marko\Session\Exceptions\InvalidSessionIdException;
use Marko\Testing\Exceptions\TestClientException;
use Psr\Container\ContainerExceptionInterface;

/**
 * Answers "what would the next request on this client see?" for the session and
 * the guards, without sending a request.
 *
 * Each probe runs the request-state reset a request runs (honouring
 * withoutResetting()), so guard caches are cleared and the guard reads the
 * session. It then resumes the session from the client's session cookie (the one
 * a repeat of the last request would carry), the way SessionMiddleware does,
 * reads it, and discards it: a probe never writes the session. Without a session
 * cookie (none set, or the last response expired it) the session is empty and
 * no user is logged in.
 *
 * The raw session data is read, never through FlashBag::get(), so asserting on
 * flash data does not consume it.
 *
 * The remember-me cookie is not consulted, because there is no current request.
 */
class SessionProbe
{
    public function __construct(
        private readonly TestClient $client,
    ) {}

    /**
     * Throw unless the response with this request sequence number is the client's latest,
     * so a session assertion never reads state written by a later request.
     *
     * @throws TestClientException
     */
    public function ensureLatest(
        int $requestSequence,
    ): void {
        if ($requestSequence !== $this->client->requestCount()) {
            throw TestClientException::staleResponse($requestSequence, $this->client->requestCount());
        }
    }

    /**
     * The raw session data the next request would start with.
     *
     * @return array<string, mixed>
     * @throws TestClientException|ContainerExceptionInterface
     */
    public function sessionData(): array
    {
        return $this->probe(static fn (SessionInterface $session): array => $session->all());
    }

    /**
     * The user the guard named $guard (the default guard when null) would see on the next request.
     *
     * @throws TestClientException|ContainerExceptionInterface
     */
    public function user(
        ?string $guard = null,
    ): ?AuthenticatableInterface {
        return $this->probe(function () use ($guard): ?AuthenticatableInterface {
            $instance = $this->client->application()->container->get(AuthManager::class)->guard($guard);

            if ($instance instanceof StatelessGuardInterface) {
                throw TestClientException::statelessGuard($instance->getName());
            }

            return $instance->user();
        });
    }

    /**
     * Run $read against the session resumed from the client's session cookie, then
     * discard the session and reset request state again, so nothing is written and
     * nothing the probe loaded is left behind.
     *
     * @template T
     * @param Closure(SessionInterface): T $read
     * @return T
     * @throws ContainerExceptionInterface
     */
    private function probe(
        Closure $read,
    ): mixed {
        $container = $this->client->application()->container;

        $this->client->resetRequestState();
        $session = $container->get(SessionInterface::class);
        $this->resume($session, $container->get(SessionConfig::class)->cookieName());

        try {
            return $read($session);
        } finally {
            $session->discard();
            $this->client->resetRequestState();
        }
    }

    /**
     * The steps SessionMiddleware takes before a request: start the session from a
     * well-formed session cookie, otherwise only arm it, so a read starts an empty session.
     */
    private function resume(
        SessionInterface $session,
        string $cookieName,
    ): void {
        $id = $this->client->lastRequestCookie($cookieName);

        if ($id === null || $id === '') {
            $session->arm();

            return;
        }

        try {
            $session->setId($id);
        } catch (InvalidSessionIdException) {
            $session->arm();

            return;
        }

        $session->start();
    }
}
