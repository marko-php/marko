<?php

declare(strict_types=1);

use Marko\Mcp\Tools\Runtime\Contracts\LogReaderInterface;
use Marko\Mcp\Tools\Runtime\ReadLogEntriesTool;

function makeLogReader(array $entries): LogReaderInterface
{
    return new class ($entries) implements LogReaderInterface
    {
        public function __construct(private readonly array $entries) {}

        public function readLast(int $count): array
        {
            return array_slice($this->entries, -$count);
        }
    };
}

it('registers read_log_entries tool returning last N entries from LoggerInterface-compatible source', function (): void {
    $reader = makeLogReader([
        '[2024-01-01 00:00:01] ERROR: Something failed',
        '[2024-01-01 00:00:02] INFO: Request completed',
        '[2024-01-01 00:00:03] WARNING: High memory usage',
    ]);

    $definition = ReadLogEntriesTool::definition($reader);

    expect($definition->name)->toBe('read_log_entries');

    // Default count
    $result = $definition->handler->handle([]);
    $text = $result['content'][0]['text'];

    expect($text)->toContain('ERROR: Something failed')
        ->and($text)->toContain('INFO: Request completed')
        ->and($text)->toContain('WARNING: High memory usage');

    // Explicit count
    $result2 = $definition->handler->handle(['count' => 1]);
    $text2 = $result2['content'][0]['text'];

    expect($text2)->toContain('WARNING: High memory usage')
        ->and($text2)->not->toContain('ERROR: Something failed');
});
