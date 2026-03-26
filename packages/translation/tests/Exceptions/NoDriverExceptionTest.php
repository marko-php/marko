<?php

declare(strict_types=1);

use Marko\Translation\Exceptions\NoDriverException;
use Marko\Translation\Exceptions\TranslationException;

it('has DRIVER_PACKAGES constant listing marko/translation-file', function (): void {
    $reflection = new ReflectionClass(NoDriverException::class);
    $constant = $reflection->getReflectionConstant('DRIVER_PACKAGES');

    expect($constant)->not->toBeFalse()
        ->and($constant->getValue())->toContain('marko/translation-file');
});

it('provides suggestion with composer require command', function (): void {
    $exception = NoDriverException::noDriverInstalled();

    expect($exception->getSuggestion())->toContain('composer require marko/translation-file');
});

it('includes context about resolving translation interfaces', function (): void {
    $exception = NoDriverException::noDriverInstalled();

    expect($exception->getContext())->toContain('Attempted to resolve a translation interface but no implementation is bound.');
});

it('extends TranslationException', function (): void {
    $exception = NoDriverException::noDriverInstalled();

    expect($exception)->toBeInstanceOf(TranslationException::class);
});
