<?php

declare(strict_types=1);

use Marko\View\Exceptions\NoDriverException;
use Marko\View\Exceptions\ViewException;

it('NoDriverException has suggestion for installing driver', function () {
    $exception = NoDriverException::noDriverInstalled();

    expect($exception)->toBeInstanceOf(ViewException::class)
        ->and($exception->getMessage())->toContain('No view driver installed')
        ->and($exception->getSuggestion())->toContain('composer require')
        ->and($exception->getSuggestion())->toContain('marko/view-latte');
});
