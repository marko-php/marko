<?php

declare(strict_types=1);

namespace Marko\Forms\View;

use Marko\Security\Contracts\CsrfTokenManagerInterface;
use Marko\Security\Middleware\CsrfMiddleware;
use Marko\Validation\Contracts\FlashedFormStateInterface;
use Marko\View\Attributes\TemplateFunctions;
use Marko\View\TemplateFunction;
use Marko\View\TemplateFunctionProviderInterface;

/**
 * Adds the form helpers to every template:
 *
 *     csrf_field()          the hidden CSRF token input, printed unescaped
 *     csrf_token()          the CSRF token itself
 *     old(field, default)   the flashed input at a dot path
 *     errors()              the flashed ValidationErrors
 */
#[TemplateFunctions]
readonly class FormTemplateFunctionProvider implements TemplateFunctionProviderInterface
{
    public function __construct(
        private CsrfTokenManagerInterface $csrfTokenManager,
        private FlashedFormStateInterface $formState,
    ) {}

    public function functions(): array
    {
        return [
            new TemplateFunction('csrf_field', $this->csrfField(...), isSafeHtml: true),
            new TemplateFunction('csrf_token', $this->csrfTokenManager->get(...)),
            new TemplateFunction('old', $this->formState->old(...)),
            new TemplateFunction('errors', $this->formState->errors(...)),
        ];
    }

    public function csrfField(): string
    {
        $token = htmlspecialchars($this->csrfTokenManager->get(), ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');

        return '<input type="hidden" name="' . CsrfMiddleware::TOKEN_FIELD . '" value="' . $token . '">';
    }
}
