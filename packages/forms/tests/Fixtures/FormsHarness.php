<?php

declare(strict_types=1);

namespace Marko\Forms\Tests\Fixtures;

use Marko\Forms\Config\FormsConfig;
use Marko\Forms\FlashedFormStateWriter;
use Marko\Forms\SessionFlashedFormState;
use Marko\Forms\ValidationErrorResponder;
use Marko\Routing\Http\Request;
use Marko\Routing\Http\Response;
use Marko\Session\Flash\SessionFlashData;
use Marko\Testing\Fake\FakeConfigRepository;
use Marko\Testing\Fake\FakeSession;
use Marko\Validation\Exceptions\ValidationException;
use Marko\Validation\Validation\ValidationErrors;

/**
 * Wires the marko/forms services over a FakeSession, which ages flash data on
 * every start() like a real session loading on a new request.
 */
class FormsHarness
{
    public FakeSession $session;

    public SessionFlashData $flashData;

    public FormsConfig $config;

    public FlashedFormStateWriter $writer;

    public SessionFlashedFormState $state;

    public ValidationErrorResponder $responder;

    /**
     * @param array<string, mixed> $config
     */
    public function __construct(
        array $config = [],
    ) {
        $this->session = new FakeSession();
        $this->session->start();
        $this->flashData = new SessionFlashData($this->session);
        $this->config = new FormsConfig(new FakeConfigRepository(array_replace([
            'forms.never_flash' => ['password', 'password_confirmation', 'current_password'],
            'forms.fallback_redirect' => '/home',
        ], $config)));
        $this->writer = new FlashedFormStateWriter($this->flashData, $this->config);
        $this->state = new SessionFlashedFormState($this->flashData, $this->session);
        $this->responder = new ValidationErrorResponder($this->session, $this->writer, $this->config);
    }

    /**
     * End the current request and start the next one.
     */
    public function nextRequest(): void
    {
        $this->session->save();
        $this->session->start();
    }

    /**
     * Ask the responder to handle a failed validation for the request.
     *
     * @param array<string, array<string>> $errors
     */
    public function failValidation(
        Request $request,
        array $errors = ['email' => ['The email field is required.']],
    ): ?Response {
        return $this->responder->respond(ValidationException::withErrors(new ValidationErrors($errors)), $request);
    }

    /**
     * @param array<string, mixed> $post
     * @param array<string, string> $headers HTTP_* server keys
     */
    public static function post(
        array $post = [],
        array $headers = [],
    ): Request {
        return new Request(
            server: array_replace([
                'REQUEST_METHOD' => 'POST',
                'REQUEST_URI' => '/register',
                'HTTP_HOST' => 'example.com',
            ], $headers),
            post: $post,
        );
    }
}
