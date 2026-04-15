<?php

declare(strict_types=1);

use Marko\Layout\Exceptions\ComponentNotFoundException;
use Marko\Layout\Exceptions\LayoutException;

it('has a ComponentNotFoundException with a static factory method providing message, context, and suggestion', function (): void {
    $exception = ComponentNotFoundException::forComponent('alert');

    expect($exception)->toBeInstanceOf(LayoutException::class)
        ->and($exception->getMessage())->toContain('alert')
        ->and($exception->getContext())->not->toBeEmpty()
        ->and($exception->getSuggestion())->not->toBeEmpty();
});
