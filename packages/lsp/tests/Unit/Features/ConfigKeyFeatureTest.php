<?php

declare(strict_types=1);

use Marko\CodeIndexer\Cache\IndexCache;
use Marko\CodeIndexer\ValueObject\ConfigKeyEntry;
use Marko\Lsp\Features\ConfigKeyFeature;

function makeIndex(array $entries): IndexCache
{
    return new class ($entries) extends IndexCache
    {
        public function __construct(private array $entries)
        {
            // Skip parent constructor
        }

        public function getConfigKeys(): array
        {
            return $this->entries;
        }
    };
}

function makeEntry(string $key, string $type = 'string', mixed $default = null, string $module = 'TestModule', string $file = '/config/test.php', int $line = 10): ConfigKeyEntry
{
    return new ConfigKeyEntry($key, $type, $default, $module, $file, $line);
}

it('offers completion for config keys inside getString string literal', function () {
    $index = makeIndex([
        makeEntry('mail.driver'),
        makeEntry('cache.ttl', 'int', 3600),
    ]);
    $feature = new ConfigKeyFeature($index);

    $items = $feature->complete('');

    expect($items)->toHaveCount(2)
        ->and($items[0]['label'])->toBe('mail.driver')
        ->and($items[1]['label'])->toBe('cache.ttl');
});

it('includes documentation and default value in completion item', function () {
    $index = makeIndex([
        makeEntry('mail.driver', 'string', 'smtp', 'Mailer', '/app/config/mail.php', 5),
    ]);
    $feature = new ConfigKeyFeature($index);

    $items = $feature->complete('');

    expect($items)->toHaveCount(1)
        ->and($items[0]['kind'])->toBe(14)
        ->and($items[0]['detail'])->toBe("string = 'smtp'")
        ->and($items[0]['documentation'])->toContain('Mailer')
        ->and($items[0]['documentation'])->toContain('/app/config/mail.php:5')
        ->and($items[0]['insertText'])->toBe('mail.driver');
});

it('resolves goto-definition to the config file and line', function () {
    $index = makeIndex([
        makeEntry('mail.driver', 'string', 'smtp', 'Mailer', '/app/config/mail.php', 7),
    ]);
    $feature = new ConfigKeyFeature($index);

    $result = $feature->gotoDefinition('mail.driver');

    expect($result)->not->toBeNull()
        ->and($result['uri'])->toBe('file:///app/config/mail.php')
        ->and($result['range']['start']['line'])->toBe(6)
        ->and($result['range']['end']['line'])->toBe(6);
});

it('publishes a diagnostic when a config key literal does not exist in the index', function () {
    $index = makeIndex([
        makeEntry('mail.driver'),
    ]);
    $feature = new ConfigKeyFeature($index);

    $doc = '<?php $config->getString(\'unknown.key\');';
    $diagnostics = $feature->diagnostics($doc);

    expect($diagnostics)->toHaveCount(1)
        ->and($diagnostics[0]['severity'])->toBe(1)
        ->and($diagnostics[0]['message'])->toBe('Unknown config key: unknown.key')
        ->and($diagnostics[0]['code'])->toBe('marko.config.unknown_key');
});

it('suggests closest-match keys in the diagnostic code action', function () {
    $index = makeIndex([
        makeEntry('mail.driver'),
        makeEntry('mail.host'),
        makeEntry('mail.port'),
        makeEntry('cache.ttl'),
    ]);
    $feature = new ConfigKeyFeature($index);

    $suggestions = $feature->suggestSimilar('mail.drivr');

    expect($suggestions)->toHaveCount(3)
        ->and($suggestions[0])->toBe('mail.driver');
});

it('handles scoped config cascade for multi-tenant keys', function () {
    $index = makeIndex([
        makeEntry('default.mail.driver'),
        makeEntry('default.mail.host'),
    ]);
    $feature = new ConfigKeyFeature($index);

    // scopes.tenant1.mail.driver cascades to default.mail.driver — should NOT produce diagnostic
    $doc = '<?php $config->getString(\'scopes.tenant1.mail.driver\');';
    $diagnostics = $feature->diagnostics($doc);

    expect($diagnostics)->toHaveCount(0);
});

it('filters completion items by dot-prefix typed so far', function () {
    $index = makeIndex([
        makeEntry('mail.driver'),
        makeEntry('mail.host'),
        makeEntry('cache.ttl', 'int', 3600),
    ]);
    $feature = new ConfigKeyFeature($index);

    $items = $feature->complete('mail.');

    expect($items)->toHaveCount(2)
        ->and($items[0]['label'])->toBe('mail.driver')
        ->and($items[1]['label'])->toBe('mail.host');
});
