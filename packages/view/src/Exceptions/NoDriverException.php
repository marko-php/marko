<?php

declare(strict_types=1);

namespace Marko\View\Exceptions;

class NoDriverException extends ViewException
{
    public static function noDriverInstalled(): self
    {
        return new self(
            message: 'No view driver installed.',
            context: 'Attempted to resolve ViewInterface but no implementation is bound.',
            suggestion: 'Install a view driver: composer require marko/view-latte',
        );
    }
}
