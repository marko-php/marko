<?php

declare(strict_types=1);

use Marko\CodeIndexer\Cache\IndexCache;
use Marko\CodeIndexer\ValueObject\ConfigKeyEntry;
use Marko\Mcp\Tools\GetConfigSchemaTool;

function makeFakeIndexCache(array $configKeys = []): IndexCache
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

it('registers get_config_schema returning all indexed ConfigKeyEntry records', function (): void {
    $entries = [
        new ConfigKeyEntry(key: 'cache.driver', type: 'string', defaultValue: 'file', module: 'Marko_Cache', file: 'config/cache.php', line: 5),
        new ConfigKeyEntry(key: 'mail.host', type: 'string', defaultValue: 'localhost', module: 'Marko_Mail', file: 'config/mail.php', line: 3),
    ];
    $index = makeFakeIndexCache($entries);
    $tool = GetConfigSchemaTool::definition($index);

    expect($tool->name)->toBe('get_config_schema');

    $result = $tool->handler->handle([]);
    $text = $result['content'][0]['text'];

    expect($text)->toContain('cache.driver')
        ->toContain('mail.host')
        ->toContain('config/cache.php:5')
        ->toContain('config/mail.php:3');
});
