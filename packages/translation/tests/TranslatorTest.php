<?php

declare(strict_types=1);

use Marko\Translation\Contracts\TranslationLoaderInterface;
use Marko\Translation\Contracts\TranslatorInterface;
use Marko\Translation\Exceptions\MissingTranslationException;
use Marko\Translation\Translator;

function createMockLoader(
    array $translations = [],
): TranslationLoaderInterface {
    return new class ($translations) implements TranslationLoaderInterface
    {
        /**
         * @param array<string, array<string, array<string, mixed>>> $translations
         */
        public function __construct(
            private readonly array $translations,
        ) {}

        public function load(
            string $locale,
            string $group,
            ?string $namespace = null,
        ): array {
            $key = $namespace !== null ? "$namespace::$locale.$group" : "$locale.$group";

            return $this->translations[$key] ?? [];
        }
    };
}

it('implements TranslatorInterface', function () {
    $loader = createMockLoader();
    $translator = new Translator($loader, 'en', 'en');

    expect($translator)->toBeInstanceOf(TranslatorInterface::class);
});

it('resolves simple translation key via loader', function () {
    $loader = createMockLoader([
        'en.messages' => [
            'welcome' => 'Welcome!',
        ],
    ]);

    $translator = new Translator($loader, 'en', 'en');

    expect($translator->get('messages.welcome'))->toBe('Welcome!');
});

it('resolves nested dot-notation keys from loaded arrays', function () {
    $loader = createMockLoader([
        'en.messages' => [
            'nested' => [
                'deep' => 'Nested value',
            ],
        ],
    ]);

    $translator = new Translator($loader, 'en', 'en');

    expect($translator->get('messages.nested.deep'))->toBe('Nested value');
});

it('replaces :placeholder tokens with provided replacements', function () {
    $loader = createMockLoader([
        'en.messages' => [
            'welcome' => 'Welcome, :name!',
            'greeting' => 'Hello :name, welcome to :place!',
        ],
    ]);

    $translator = new Translator($loader, 'en', 'en');

    expect($translator->get('messages.welcome', ['name' => 'Mark']))->toBe('Welcome, Mark!')
        ->and($translator->get('messages.greeting', ['name' => 'Mark', 'place' => 'Marko']))->toBe(
            'Hello Mark, welcome to Marko!',
        );
});

it('falls back to fallback locale when key missing in primary locale', function () {
    $loader = createMockLoader([
        'en.messages' => [
            'welcome' => 'Welcome!',
        ],
        'fr.messages' => [],
    ]);

    $translator = new Translator($loader, 'fr', 'en');

    expect($translator->get('messages.welcome'))->toBe('Welcome!');
});

it('throws MissingTranslationException when key missing in all locales', function () {
    $loader = createMockLoader([
        'en.messages' => [],
        'fr.messages' => [],
    ]);

    $translator = new Translator($loader, 'fr', 'en');

    $translator->get('messages.nonexistent');
})->throws(MissingTranslationException::class);

it('selects correct plural form for zero, one, and other counts', function () {
    $loader = createMockLoader([
        'en.messages' => [
            'items' => 'zero:No items|one:One item|other::count items',
        ],
    ]);

    $translator = new Translator($loader, 'en', 'en');

    expect($translator->choice('messages.items', 0))->toBe('No items')
        ->and($translator->choice('messages.items', 1))->toBe('One item')
        ->and($translator->choice('messages.items', 5, ['count' => '5']))->toBe('5 items');
});
