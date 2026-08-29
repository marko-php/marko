<?php

declare(strict_types=1);

namespace Marko\Roadrunner\Tests\Fixtures\Demo\Http\Controllers;

use Marko\Authentication\Contracts\GuardInterface;
use Marko\Authentication\Exceptions\AuthException;
use Marko\Routing\Attributes\Get;
use Marko\Routing\Attributes\Middleware;
use Marko\Routing\Attributes\Post;
use Marko\Routing\Http\Response;
use Marko\Security\Contracts\CsrfTokenManagerInterface;
use Marko\Security\Middleware\CsrfMiddleware;
use Marko\Session\Contracts\SessionInterface;
use Marko\Session\Exceptions\SessionNotStartedException;
use Random\RandomException;
use RuntimeException;

/**
 * Fixture controller exercising session and authentication state so a
 * cross-request leak is directly assertable from the response body.
 *
 * @noinspection PhpUnused Route actions are invoked via reflection by the Router.
 */
class DemoController
{
    private const int FIXTURE_USER_ID = 1;

    public function __construct(
        private readonly SessionInterface $session,
        private readonly GuardInterface $guard,
        private readonly CsrfTokenManagerInterface $csrfTokenManager,
    ) {}

    /**
     * @throws SessionNotStartedException|AuthException|RandomException
     */
    #[Get('/session/write')]
    public function write(): Response
    {
        $visits = (int) $this->session->get('visits', 0) + 1;
        $this->session->set('visits', $visits);
        $this->guard->loginById(self::FIXTURE_USER_ID);

        return new Response($this->describeState($visits));
    }

    /**
     * @throws SessionNotStartedException|RandomException
     */
    #[Get('/session/read')]
    public function read(): Response
    {
        $visits = (int) $this->session->get('visits', 0);

        return new Response($this->describeState($visits));
    }

    /**
     * Writes to the session, then throws before the request completes
     * normally — exercises whether the session is left PHP_SESSION_ACTIVE
     * for the next request when a controller never reaches save().
     *
     * @throws SessionNotStartedException|RandomException|RuntimeException
     */
    #[Get('/session/throw')]
    public function throwAfterSessionWrite(): Response
    {
        $this->session->set('visits', 999);

        throw new RuntimeException('Simulated failure after session write, before normal completion');
    }

    /**
     * Issues a CSRF token tied to the caller's session, so the RoadRunner
     * end-to-end suite can drive a real GET-token-then-POST-submit flow
     * through one real rr worker process.
     */
    #[Get('/csrf/token')]
    public function csrfToken(): Response
    {
        return new Response($this->csrfTokenManager->get());
    }

    /**
     * Only reachable once CsrfMiddleware has validated the submitted token
     * against the caller's session — an invalid or missing token never
     * reaches this method.
     */
    #[Post('/csrf/submit')]
    #[Middleware(CsrfMiddleware::class)]
    public function csrfSubmit(): Response
    {
        return new Response('csrf-ok');
    }

    private function describeState(
        int $visits,
    ): string {
        $userId = $this->guard->id() ?? 'guest';

        return "session={$this->session->getId()};visits=$visits;user=$userId";
    }
}
