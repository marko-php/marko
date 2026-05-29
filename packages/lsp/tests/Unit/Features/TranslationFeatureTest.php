<?php

declare(strict_types=1);

use Marko\CodeIndexer\Cache\IndexCache;
use Marko\CodeIndexer\ValueObject\TranslationEntry;
use Marko\Lsp\Features\TranslationFeature;

function makeTranslationIndex(array $entries): IndexCache
{
    return new class ($entries) extends IndexCache
    {
        public function __construct(private array $entries)
        {
            // Skip parent constructor
        }

        public function getTranslationKeys(): array
        {
            return $this->entries;
        }
    };
}

function makeTranslationEntry(
    string $key,
    string $group = 'messages',
    string $locale = 'en',
    ?string $namespace = null,
    string $file = '/resources/translations/en/messages.php',
    int $line = 5,
    string $module = 'TestModule',
): TranslationEntry {
    return new TranslationEntry($key, $group, $locale, $namespace, $file, $line, $module);
}

/**
 * Fixtures:
 * - messages.welcome (en)
 * - messages.posts.title (en)
 * - messages.welcome (es) — default-locale filter should exclude this
 * - auth::login.title (en, namespaced)
 */
function makeStandardIndex(): IndexCache
{
    return makeTranslationIndex([
        makeTranslationEntry('welcome', 'messages', 'en'),
        makeTranslationEntry('title', 'messages.posts', 'en', null, '/resources/translations/en/messages.php', 10),
        makeTranslationEntry('welcome', 'messages', 'es', null, '/resources/translations/es/messages.php', 5),
        makeTranslationEntry('title', 'login', 'en', 'auth', '/modules/auth/resources/translations/en/login.php', 3),
    ]);
}

it('offers completion for translation keys inside get', function () {
    $feature = new TranslationFeature(makeStandardIndex());

    $items = $feature->complete('');

    // Only default locale (en) — es entry excluded; expect 3 en entries
    expect($items)->toHaveCount(3);
    $labels = array_column($items, 'label');
    expect($labels)->toContain('messages.welcome')
        ->and($labels)->toContain('messages.posts.title')
        ->and($labels)->toContain('auth::login.title');
    // Verify es duplicate is excluded — only one 'messages.welcome' entry
    expect(count(array_filter($labels, fn ($l) => $l === 'messages.welcome')))->toBe(1);
});

it('handles namespaced translation keys with double-colon syntax', function () {
    $feature = new TranslationFeature(makeStandardIndex());

    $items = $feature->complete('auth::');

    expect($items)->toHaveCount(1)
        ->and($items[0]['label'])->toBe('auth::login.title')
        ->and($items[0]['insertText'])->toBe('auth::login.title');
});

it('resolves goto-definition to the translation file and line', function () {
    $feature = new TranslationFeature(makeStandardIndex());

    $result = $feature->gotoDefinition('messages.welcome');

    expect($result)->not->toBeNull()
        ->and($result['uri'])->toBe('file:///resources/translations/en/messages.php')
        ->and($result['range']['start']['line'])->toBe(4)
        ->and($result['range']['end']['line'])->toBe(4);
});

it('publishes a diagnostic when a translation key is missing from the default locale', function () {
    $feature = new TranslationFeature(makeStandardIndex());

    $doc = "<?php \$translator->get('auth.unknown_key');";
    $diagnostics = $feature->diagnostics($doc);

    expect($diagnostics)->toHaveCount(1)
        ->and($diagnostics[0]['severity'])->toBe(1)
        ->and($diagnostics[0]['message'])->toBe('Unknown translation key: auth.unknown_key')
        ->and($diagnostics[0]['code'])->toBe('marko.translation.unknown_key');
});

it('suggests closest-match keys in the diagnostic code action', function () {
    $feature = new TranslationFeature(makeStandardIndex());

    $suggestions = $feature->suggestSimilar('messages.welcom');

    expect($suggestions)->toHaveCount(3)
        ->and($suggestions[0])->toBe('messages.welcome');
});
