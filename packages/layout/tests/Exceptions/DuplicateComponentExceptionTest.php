<?php

declare(strict_types=1);

use Marko\Layout\Exceptions\DuplicateComponentException;
use Marko\Layout\Exceptions\LayoutException;

it('has a DuplicateComponentException with a static factory method providing message, context, and suggestion', function (): void {
    $exception = DuplicateComponentException::forComponent('header', 'ModuleA', 'ModuleB');

    expect($exception)->toBeInstanceOf(LayoutException::class)
        ->and($exception->getMessage())->toContain('header')
        ->and($exception->getContext())->not->toBeEmpty()
        ->and($exception->getSuggestion())->not->toBeEmpty();
});
