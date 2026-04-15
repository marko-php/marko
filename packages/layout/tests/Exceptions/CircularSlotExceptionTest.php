<?php

declare(strict_types=1);

use Marko\Layout\Exceptions\CircularSlotException;
use Marko\Layout\Exceptions\LayoutException;

it('has a CircularSlotException with a static factory method providing message, context, and suggestion', function (): void {
    $exception = CircularSlotException::forSlot('main', ['main', 'nested', 'main']);

    expect($exception)->toBeInstanceOf(LayoutException::class)
        ->and($exception->getMessage())->toContain('main')
        ->and($exception->getContext())->not->toBeEmpty()
        ->and($exception->getSuggestion())->not->toBeEmpty();
});
