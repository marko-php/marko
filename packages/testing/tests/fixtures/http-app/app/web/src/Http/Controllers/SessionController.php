<?php

declare(strict_types=1);

namespace Marko\Testing\Tests\HttpApp\Http\Controllers;

use JsonException;
use Marko\Authentication\Contracts\GuardInterface;
use Marko\Routing\Attributes\Get;
use Marko\Routing\Attributes\Post;
use Marko\Routing\Http\Request;
use Marko\Routing\Http\Response;
use Marko\Session\Contracts\SessionInterface;
use Marko\Validation\Contracts\ValidatorInterface;
use Marko\Validation\Exceptions\ValidationException;

/**
 * Writes to the real session, logs out of the real session guard and fails
 * validation, so the tests can assert on what the next request would see.
 *
 * @noinspection PhpUnused Route actions are invoked via reflection by the Router.
 */
class SessionController
{
    public function __construct(
        private readonly SessionInterface $session,
        private readonly GuardInterface $guard,
        private readonly ValidatorInterface $validator,
    ) {}

    /**
     * Store every query parameter in the session.
     */
    #[Get('/session/put')]
    public function put(
        Request $request,
    ): Response {
        foreach ($request->query() as $key => $value) {
            $this->session->set((string) $key, $value);
        }

        return new Response('stored');
    }

    #[Get('/session/flash')]
    public function flash(
        Request $request,
    ): Response {
        $this->session->flash()->add((string) $request->query('type'), (string) $request->query('message'));

        return new Response('flashed');
    }

    /**
     * Read (and so clear) the flash messages of a type.
     */
    #[Get('/session/flash/read')]
    public function readFlash(
        Request $request,
    ): Response {
        return new Response(implode('|', $this->session->flash()->get((string) $request->query('type'))));
    }

    #[Get('/session/destroy')]
    public function destroy(): Response
    {
        $this->session->destroy();

        return new Response('destroyed');
    }

    #[Post('/logout')]
    public function logout(): Response
    {
        $this->guard->logout();

        return Response::redirect('/');
    }

    /**
     * @throws ValidationException|JsonException
     */
    #[Post('/profile')]
    public function profile(
        Request $request,
    ): Response {
        $this->validator->validateOrFail($request->post(), [
            'name' => 'required',
            'email' => 'required|email',
        ]);

        return Response::json(['saved' => true]);
    }
}
