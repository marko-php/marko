<?php

declare(strict_types=1);

use Marko\View\Exceptions\NoDriverException;
use Marko\View\Exceptions\ViewException;

it('has DRIVER_PACKAGES constant listing marko/view-latte', function (): void {
    $reflection = new ReflectionClass(NoDriverException::class);
    $constant = $reflection->getReflectionConstant('DRIVER_PACKAGES');

    expect($constant)->not->toBeFalse()
        ->and($constant->getValue())->toContain('marko/view-latte');
});

it('provides suggestion with composer require commands for all driver packages', function (): void {
    $exception = NoDriverException::noDriverInstalled();

    expect($exception->getSuggestion())->toContain('composer require marko/view-latte');
});

it('includes context about resolving ViewInterface', function (): void {
    $exception = NoDriverException::noDriverInstalled();

    expect($exception->getContext())->toContain('Attempted to resolve ViewInterface but no implementation is bound.');
});

it('extends ViewException', function (): void {
    $exception = NoDriverException::noDriverInstalled();

    expect($exception)->toBeInstanceOf(ViewException::class);
});
