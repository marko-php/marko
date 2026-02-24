<?php

declare(strict_types=1);

use Marko\Core\Exceptions\MarkoException;
use Marko\Translation\Exceptions\MissingTranslationException;
use Marko\Translation\Exceptions\TranslationException;

it('defines TranslationException extending MarkoException with context and suggestion', function () {
    $exception = new TranslationException(
        message: 'Translation error occurred',
        context: 'Attempted to load group "messages" for locale "fr"',
        suggestion: 'Ensure the translation file exists at lang/fr/messages.php',
    );

    expect($exception)->toBeInstanceOf(MarkoException::class)
        ->and($exception->getMessage())->toBe('Translation error occurred')
        ->and($exception->getContext())->toBe('Attempted to load group "messages" for locale "fr"')
        ->and($exception->getSuggestion())->toBe('Ensure the translation file exists at lang/fr/messages.php');
});

it(
    'defines MissingTranslationException extending TranslationException with factory method for missing key',
    function () {
        $exception = MissingTranslationException::forKey('messages.welcome', 'fr');

        expect($exception)->toBeInstanceOf(TranslationException::class)
            ->and($exception)->toBeInstanceOf(MissingTranslationException::class);
    },
);

it('includes key, locale, and resolution path in MissingTranslationException context', function () {
    $exception = MissingTranslationException::forKey('messages.welcome', 'fr');

    $context = $exception->getContext();

    expect($context)->toContain('messages.welcome')
        ->and($context)->toContain('fr');
});

it('suggests creating the translation file in MissingTranslationException suggestion', function () {
    $exception = MissingTranslationException::forKey('messages.welcome', 'fr');

    $suggestion = $exception->getSuggestion();

    expect($suggestion)->toContain('lang/fr/messages.php');
});
