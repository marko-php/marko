<?php

declare(strict_types=1);

use Marko\CodeIndexer\Cache\IndexCache;
use Marko\CodeIndexer\ValueObject\ConfigKeyEntry;
use Marko\Mcp\Tools\CheckConfigKeyTool;

function makeFakeIndexCacheForCheck(array $configKeys = []): IndexCache
{
    return new class ($configKeys) extends IndexCache
    {
        public function __construct(private array $keys)
        {
            // Skip parent constructor
        }

        public function getConfigKeys(): array
        {
            return $this->keys;
        }
    };
}

it('registers check_config_key', function (): void {
    $index = makeFakeIndexCacheForCheck();
    $tool = CheckConfigKeyTool::definition($index);

    expect($tool->name)->toBe('check_config_key');
});

it('returns exists true with metadata for a valid key', function (): void {
    $entries = [
        new ConfigKeyEntry(key: 'cache.driver', type: 'string', defaultValue: 'file', module: 'Marko_Cache', file: 'config/cache.php', line: 5),
    ];
    $index = makeFakeIndexCacheForCheck($entries);
    $tool = CheckConfigKeyTool::definition($index);

    $result = $tool->handler->handle(['key' => 'cache.driver']);
    $text = $result['content'][0]['text'];

    expect($text)
        ->toContain('exists: true')
        ->toContain('key: cache.driver')
        ->toContain('type: string')
        ->toContain('file: config/cache.php:5')
        ->toContain('module: Marko_Cache');
});

it('returns exists false for unknown keys with closest-match suggestions', function (): void {
    $entries = [
        new ConfigKeyEntry(key: 'cache.driver', type: 'string', defaultValue: 'file', module: 'Marko_Cache', file: 'config/cache.php', line: 5),
        new ConfigKeyEntry(key: 'cache.store', type: 'string', defaultValue: 'default', module: 'Marko_Cache', file: 'config/cache.php', line: 10),
    ];
    $index = makeFakeIndexCacheForCheck($entries);
    $tool = CheckConfigKeyTool::definition($index);

    $result = $tool->handler->handle(['key' => 'cache.drver']);
    $text = $result['content'][0]['text'];

    expect($text)
        ->toContain('exists: false')
        ->toContain('key: cache.drver')
        ->toContain('suggestions:')
        ->toContain('cache.driver');
});

it('includes source file and line for known keys', function (): void {
    $entries = [
        new ConfigKeyEntry(key: 'mail.host', type: 'string', defaultValue: 'localhost', module: 'Marko_Mail', file: 'config/mail.php', line: 12),
    ];
    $index = makeFakeIndexCacheForCheck($entries);
    $tool = CheckConfigKeyTool::definition($index);

    $result = $tool->handler->handle(['key' => 'mail.host']);
    $text = $result['content'][0]['text'];

    expect($text)
        ->toContain('file: config/mail.php:12');
});
