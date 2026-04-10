<?php

declare(strict_types=1);

use Marko\Layout\Exceptions\LayoutException;
use Marko\Layout\Exceptions\SlotNotFoundException;

it('has a SlotNotFoundException with a static factory method providing message, context, and suggestion', function (): void {
    $exception = SlotNotFoundException::forSlot('sidebar', 'main-layout');

    expect($exception)->toBeInstanceOf(LayoutException::class)
        ->and($exception->getMessage())->toContain('sidebar')
        ->and($exception->getContext())->not->toBeEmpty()
        ->and($exception->getSuggestion())->not->toBeEmpty();
});
