<?php

declare(strict_types=1);

namespace Marko\Mcp\Tools\Runtime;

use Marko\Mcp\Tools\Runtime\Contracts\ErrorTrackerInterface;
use Marko\Mcp\Tools\ToolDefinition;
use Marko\Mcp\Tools\ToolHandlerInterface;
use Throwable;

readonly class LastErrorTool implements ToolHandlerInterface
{
    public function __construct(private ErrorTrackerInterface $tracker) {}

    public static function definition(ErrorTrackerInterface $tracker): ToolDefinition
    {
        return new ToolDefinition(
            name: 'last_error',
            description: 'Return the most recent application error with its stack trace',
            inputSchema: ['type' => 'object', 'properties' => []],
            handler: new self($tracker),
        );
    }

    public function handle(array $arguments): array
    {
        try {
            $error = $this->tracker->lastError();
        } catch (Throwable $e) {
            return [
                'content' => [['type' => 'text', 'text' => 'ERROR: ' . $e->getMessage()]],
                'isError' => true,
            ];
        }

        if ($error === null) {
            return ['content' => [['type' => 'text', 'text' => 'No error recorded.']]];
        }

        $timestamp = date('Y-m-d H:i:s', $error['timestamp']);
        $text = implode("\n", [
            "timestamp: $timestamp",
            "message: {$error['message']}",
            "file: {$error['file']}:{$error['line']}",
            '',
            'Stack trace:',
            $error['trace'],
        ]);

        return ['content' => [['type' => 'text', 'text' => $text]]];
    }
}
