<?php

declare(strict_types=1);

use Marko\Mcp\Tools\Runtime\Contracts\LogReaderInterface;
use Marko\Mcp\Tools\Runtime\ReadLogEntriesTool;

function makeLogReader(
    array $entries,
): LogReaderInterface {
    return new class ($entries) implements LogReaderInterface
    {
        public function __construct(private readonly array $entries) {}

        public function readLast(
            int $count,
        ): array {
            return array_slice($this->entries, -$count);
        }
    };
}

it(
    'registers read_log_entries tool returning last N entries from LoggerInterface-compatible source',
    function (): void {
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
    },
);

/**
 * A reader that records every count it is asked for.
 */
function makeRecordingLogReader(
    ArrayObject $counts,
): LogReaderInterface {
    return new readonly class ($counts) implements LogReaderInterface
    {
        public function __construct(private ArrayObject $counts) {}

        public function readLast(
            int $count,
        ): array {
            $this->counts->append($count);

            return ['line'];
        }
    };
}

it('clamps count to between 1 and 500 before reading', function (): void {
    $counts = new ArrayObject();
    $handler = ReadLogEntriesTool::definition(makeRecordingLogReader($counts))->handler;

    $handler->handle(['count' => -10]);
    $handler->handle(['count' => 0]);
    $handler->handle(['count' => 1_000_000]);
    $handler->handle(['count' => 25]);
    $handler->handle([]);

    expect($counts->getArrayCopy())->toBe([1, 1, 500, 25, 50]);
});

it('advertises the count bounds in its input schema', function (): void {
    $schema = ReadLogEntriesTool::definition(makeLogReader([]))->inputSchema;

    expect($schema['properties']['count']['minimum'])->toBe(1)
        ->and($schema['properties']['count']['maximum'])->toBe(500);
});
