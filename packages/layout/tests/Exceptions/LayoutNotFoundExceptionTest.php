<?php

declare(strict_types=1);

use Marko\Layout\Exceptions\LayoutException;
use Marko\Layout\Exceptions\LayoutNotFoundException;

it('has a LayoutNotFoundException with a static factory method providing message, context, and suggestion', function (): void {
    $exception = LayoutNotFoundException::forLayout('admin');

    expect($exception)->toBeInstanceOf(LayoutException::class)
        ->and($exception->getMessage())->toContain('admin')
        ->and($exception->getContext())->not->toBeEmpty()
        ->and($exception->getSuggestion())->not->toBeEmpty();
});
