<?php

declare(strict_types=1);

namespace Marko\Mcp\Tools\Runtime;

use Marko\Mcp\Tools\Runtime\Contracts\QueryConnectionInterface;
use Marko\Mcp\Tools\ToolDefinition;
use Marko\Mcp\Tools\ToolHandlerInterface;
use Throwable;

readonly class QueryDatabaseTool implements ToolHandlerInterface
{
    private const array ALLOWED_PREFIXES = ['SELECT', 'WITH', 'SHOW', 'EXPLAIN', 'DESCRIBE'];

    public function __construct(private QueryConnectionInterface $connection) {}

    public static function definition(QueryConnectionInterface $connection): ToolDefinition
    {
        return new ToolDefinition(
            name: 'query_database',
            description: 'Query the database. Read-only by default; use allowWrite+confirm for write operations.',
            inputSchema: [
                'type' => 'object',
                'required' => ['sql'],
                'properties' => [
                    'sql' => ['type' => 'string', 'description' => 'SQL statement to execute'],
                    'allowWrite' => ['type' => 'boolean', 'description' => 'Set true to allow write operations'],
                    'confirm' => ['type' => 'boolean', 'description' => 'Set true to confirm write operation'],
                ],
            ],
            handler: new self($connection),
        );
    }

    public function handle(array $arguments): array
    {
        $sql = trim((string) ($arguments['sql'] ?? ''));
        $allowWrite = (bool) ($arguments['allowWrite'] ?? false);
        $confirm = (bool) ($arguments['confirm'] ?? false);

        if (! $allowWrite) {
            if (! $this->isAllowedPrefix($sql)) {
                return [
                    'content' => [['type' => 'text', 'text' => 'SQL statement not permitted. Only SELECT, WITH, SHOW, EXPLAIN, and DESCRIBE are allowed without allowWrite=true.']],
                    'isError' => true,
                ];
            }
        } elseif (! $confirm) {
            return [
                'content' => [['type' => 'text', 'text' => 'Write operation requires confirm=true to proceed. Set both allowWrite=true and confirm=true.']],
                'isError' => true,
            ];
        }

        try {
            $rows = $this->connection->query($sql);
        } catch (Throwable $e) {
            return [
                'content' => [['type' => 'text', 'text' => 'ERROR: ' . $e->getMessage()]],
                'isError' => true,
            ];
        }

        $prefix = $allowWrite ? "WARNING: WRITE OPERATION executed.\n\n" : '';
        $text = $prefix . $this->formatRows($rows);

        return ['content' => [['type' => 'text', 'text' => $text]]];
    }

    private function isAllowedPrefix(string $sql): bool
    {
        $first = strtoupper(strtok($sql, " \t\n\r") ?: '');

        return in_array($first, self::ALLOWED_PREFIXES, strict: true);
    }

    /** @param list<array<string, mixed>> $rows */
    private function formatRows(array $rows): string
    {
        if ($rows === []) {
            return '(no rows returned)';
        }

        return implode("\n", array_map(
            fn (array $row) => implode(', ', array_map(
                fn (string $k, mixed $v) => "$k: $v",
                array_keys($row),
                array_values($row),
            )),
            $rows,
        ));
    }
}
