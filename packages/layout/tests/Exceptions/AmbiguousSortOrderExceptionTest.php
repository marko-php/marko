<?php

declare(strict_types=1);

use Marko\Layout\Exceptions\AmbiguousSortOrderException;
use Marko\Layout\Exceptions\LayoutException;

it('has an AmbiguousSortOrderException with a static factory method providing message, context, and suggestion', function (): void {
    $exception = AmbiguousSortOrderException::forComponents('header', 10, ['ComponentA', 'ComponentB']);

    expect($exception)->toBeInstanceOf(LayoutException::class)
        ->and($exception->getMessage())->toContain('header')
        ->and($exception->getContext())->not->toBeEmpty()
        ->and($exception->getSuggestion())->not->toBeEmpty();
});
