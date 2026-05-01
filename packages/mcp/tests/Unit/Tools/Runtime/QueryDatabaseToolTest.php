<?php

declare(strict_types=1);

use Marko\Mcp\Tools\Runtime\Contracts\QueryConnectionInterface;
use Marko\Mcp\Tools\Runtime\QueryDatabaseTool;

function makeQueryConnection(array $rows = []): QueryConnectionInterface
{
    return new class ($rows) implements QueryConnectionInterface
    {
        public function __construct(private readonly array $rows) {}

        public function query(string $sql, array $params = []): array
        {
            return $this->rows;
        }
    };
}

it('registers query_database tool using a read-only connection by default', function (): void {
    $connection = makeQueryConnection([['id' => 1, 'name' => 'Alice']]);

    $definition = QueryDatabaseTool::definition($connection);

    expect($definition->name)->toBe('query_database');

    $result = $definition->handler->handle(['sql' => 'SELECT * FROM users']);
    $text = $result['content'][0]['text'];

    expect($text)->toContain('Alice');
});

it('enforces a SELECT/WITH/SHOW/EXPLAIN/DESCRIBE prefix allowlist as secondary defense', function (): void {
    $connection = makeQueryConnection();

    $result = QueryDatabaseTool::definition($connection)->handler->handle(['sql' => 'INSERT INTO users VALUES (1)']);

    expect($result['content'][0]['text'])->toContain('not permitted')
        ->and($result['isError'] ?? false)->toBeTrue();
});

it('rejects write SQL even when the allowlist is bypassed because the connection itself is read-only', function (): void {
    // This test documents that the connection should be read-only; the tool propagates errors from the connection.
    $connection = new class () implements QueryConnectionInterface
    {
        public function query(string $sql, array $params = []): array
        {
            throw new RuntimeException('ERROR: cannot execute INSERT in a read-only transaction');
        }
    };

    $result = QueryDatabaseTool::definition($connection)->handler->handle([
        'sql' => 'INSERT INTO users VALUES (1)',
        'allowWrite' => true,
        'confirm' => true,
    ]);

    expect($result['content'][0]['text'])->toContain('ERROR')
        ->and($result['isError'] ?? false)->toBeTrue();
});

it('allows write SQL only when both allowWrite and confirm flags are set', function (): void {
    $connection = makeQueryConnection([['affected' => 1]]);

    $result = QueryDatabaseTool::definition($connection)->handler->handle([
        'sql' => 'INSERT INTO users VALUES (1)',
        'allowWrite' => true,
        'confirm' => true,
    ]);

    $text = $result['content'][0]['text'];

    expect($text)->toContain('affected')
        ->and(isset($result['isError']) && $result['isError'])->toBeFalse();
});

it('returns a loud warning in the response body when allowWrite is used', function (): void {
    $connection = makeQueryConnection([['affected' => 1]]);

    $result = QueryDatabaseTool::definition($connection)->handler->handle([
        'sql' => 'INSERT INTO users VALUES (1)',
        'allowWrite' => true,
        'confirm' => true,
    ]);

    expect($result['content'][0]['text'])->toContain('WRITE OPERATION');
});
