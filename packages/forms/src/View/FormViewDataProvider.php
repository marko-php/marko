<?php

declare(strict_types=1);

namespace Marko\Forms\View;

use Marko\Validation\Contracts\FlashedFormStateInterface;
use Marko\View\Attributes\ViewData;
use Marko\View\ViewDataProviderInterface;

/**
 * Shares $errors (ValidationErrors) and $old (OldInput) with every template.
 *
 *     Latte: {if $errors->has('email')}{$errors->first('email')}{/if}  {$old['email']}
 *     Twig:  {% if errors.has('email') %}{{ errors.first('email') }}{% endif %}  {{ old.email }}
 */
#[ViewData]
readonly class FormViewDataProvider implements ViewDataProviderInterface
{
    public function __construct(
        private FlashedFormStateInterface $formState,
    ) {}

    public function data(): array
    {
        return [
            'errors' => $this->formState->errors(),
            'old' => new OldInput($this->formState),
        ];
    }
}
