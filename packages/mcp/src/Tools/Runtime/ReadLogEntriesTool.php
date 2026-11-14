<?php

declare(strict_types=1);

namespace Marko\Mcp\Tools\Runtime;

use Marko\Mcp\Tools\Runtime\Contracts\LogReaderInterface;
use Marko\Mcp\Tools\ToolDefinition;
use Marko\Mcp\Tools\ToolHandlerInterface;
use Throwable;

readonly class ReadLogEntriesTool implements ToolHandlerInterface
{
    public const int DEFAULT_COUNT = 50;

    public const int MAX_COUNT = 500;

    public function __construct(private LogReaderInterface $reader) {}

    public static function definition(
        LogReaderInterface $reader,
    ): ToolDefinition {
        return new ToolDefinition(
            name: 'read_log_entries',
            description: 'Return the last N log entries from the application log',
            inputSchema: [
                'type' => 'object',
                'properties' => [
                    'count' => [
                        'type' => 'integer',
                        'minimum' => 1,
                        'maximum' => self::MAX_COUNT,
                        'description' => 'Number of entries to return (default ' . self::DEFAULT_COUNT
                            . ', clamped to 1-' . self::MAX_COUNT . ')',
                    ],
                ],
            ],
            handler: new self($reader),
        );
    }

    public function handle(
        array $arguments,
    ): array {
        $requested = isset($arguments['count']) ? (int) $arguments['count'] : self::DEFAULT_COUNT;
        $count = max(1, min(self::MAX_COUNT, $requested));

        try {
            $entries = $this->reader->readLast($count);
        } catch (Throwable $e) {
            return [
                'content' => [['type' => 'text', 'text' => 'ERROR: ' . $e->getMessage()]],
                'isError' => true,
            ];
        }

        $text = $entries !== [] ? implode("\n", $entries) : '(no log entries found)';

        return ['content' => [['type' => 'text', 'text' => $text]]];
    }
}
