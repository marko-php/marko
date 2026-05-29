<?php

declare(strict_types=1);

use Marko\Mcp\Tools\Runtime\Adapters\MarkoConsoleDispatcher;
use Marko\Mcp\Tools\Runtime\Adapters\MarkoQueryConnection;
use Marko\Mcp\Tools\Runtime\Contracts\LogReaderInterface;
use Marko\Mcp\Tools\Runtime\QueryDatabaseTool;
use Marko\Mcp\Tools\Runtime\ReadLogEntriesTool;
use Marko\Mcp\Tools\Runtime\RunConsoleCommandTool;

it('handles each tool\'s failure mode with a loud error content block', function (): void {
    // RunConsoleCommandTool — dispatcher throws
    $throwingDispatcher = new readonly class () extends MarkoConsoleDispatcher
    {
        public function __construct() {}

        public function dispatch(
            string $command,
            array $args = [],
        ): array
        {
            throw new RuntimeException('Command not found: nonexistent');
        }
    };

    $consoleResult = RunConsoleCommandTool::definition($throwingDispatcher)->handler->handle(
        ['command' => 'nonexistent']
    );
    expect($consoleResult['content'][0]['text'])->toContain('ERROR')
        ->and($consoleResult['isError'] ?? false)->toBeTrue();

    // QueryDatabaseTool — blocked by allowlist (no allowWrite flag)
    $queryResult = QueryDatabaseTool::definition(new readonly class () extends MarkoQueryConnection
    {
        public function __construct() {}

        public function query(
            string $sql,
            array $params = [],
        ): array
        {
            return [];
        }
    })->handler->handle(['sql' => 'DROP TABLE users']);
    expect($queryResult['content'][0]['text'])->toContain('not permitted')
        ->and($queryResult['isError'] ?? false)->toBeTrue();

    // ReadLogEntriesTool — reader throws
    $throwingReader = new class () implements LogReaderInterface
    {
        public function readLast(int $count): array
        {
            throw new RuntimeException('Log file not accessible');
        }
    };

    $logResult = ReadLogEntriesTool::definition($throwingReader)->handler->handle([]);
    expect($logResult['content'][0]['text'])->toContain('ERROR')
        ->and($logResult['isError'] ?? false)->toBeTrue();
});
