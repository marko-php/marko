<?php

declare(strict_types=1);

namespace Marko\Testing\Tests\HttpApp\Http\Controllers;

use JsonException;
use Marko\Routing\Attributes\Get;
use Marko\Routing\Attributes\Post;
use Marko\Routing\Http\Request;
use Marko\Routing\Http\Response;
use Marko\Validation\Contracts\FlashedFormStateInterface;
use Marko\Validation\Contracts\ValidatorInterface;
use Marko\Validation\Exceptions\ValidationException;

/**
 * A server-rendered form round trip through marko/forms: a failed POST flashes
 * the errors and input and redirects back to the form, which shows them.
 *
 * @noinspection PhpUnused Route actions are invoked via reflection by the Router.
 */
class ContactController
{
    public function __construct(
        private readonly ValidatorInterface $validator,
        private readonly FlashedFormStateInterface $formState,
    ) {}

    /**
     * The form page: the flashed errors and the old email input, as JSON.
     *
     * @throws JsonException
     */
    #[Get('/contact')]
    public function show(): Response
    {
        return Response::json([
            'errors' => $this->formState->errors()->all(),
            'email' => $this->formState->old('email'),
        ]);
    }

    /**
     * Validate. On failure marko/forms' ValidationErrorResponder flashes the
     * errors and input and redirects back to the form (303).
     *
     * @throws JsonException|ValidationException
     */
    #[Post('/contact')]
    public function submit(
        Request $request,
    ): Response {
        $this->validator->validateOrFail($request->post(), [
            'name' => 'required',
            'email' => 'required|email',
            'address.city' => 'required',
        ]);

        return Response::json(['sent' => true]);
    }
}
