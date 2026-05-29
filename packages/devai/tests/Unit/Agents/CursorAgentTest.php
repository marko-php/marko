<?php

declare(strict_types=1);

use Marko\DevAi\Agents\CursorAgent;
use Marko\DevAi\Contract\AgentInterface;
use Marko\DevAi\ValueObject\McpRegistration;

beforeEach(function (): void {
    $this->root = devaiTempDir();
});

afterEach(function (): void {
    devaiRemoveDir($this->root);
});

it('reports name as cursor', function (): void {
    expect((new CursorAgent(devaiRunner()))->name())->toBe('cursor');
});

it('detects Cursor installation', function (): void {
    expect((new CursorAgent(devaiRunner(onPath: true)))->isInstalled())->toBeTrue()
        ->and((new CursorAgent(devaiRunner(onPath: false)))->isInstalled())->toBeFalse();
});

it('implements AgentInterface', function (): void {
    expect(new CursorAgent(devaiRunner()))->toBeInstanceOf(AgentInterface::class);
});

it('writes or merges a .cursor/mcp.json entry for marko-mcp on install', function (): void {
    mkdir($this->root . '/.cursor', 0755, true);
    file_put_contents(
        $this->root . '/.cursor/mcp.json',
        json_encode(
            ['mcpServers' => ['other-server' => ['command' => 'other', 'args' => [], 'env' => []]]],
            JSON_PRETTY_PRINT
        ),
    );

    $reg = new McpRegistration('marko-mcp', 'php', ['artisan', 'mcp:serve'], ['APP_ENV' => 'test']);
    (new CursorAgent(devaiRunner()))->install(devaiContext(mcp: $reg), $this->root);

    $decoded = json_decode((string) file_get_contents($this->root . '/.cursor/mcp.json'), true);
    expect($decoded['mcpServers'])->toHaveKey('other-server')
        ->and($decoded['mcpServers'])->toHaveKey('marko-mcp')
        ->and($decoded['mcpServers']['marko-mcp']['command'])->toBe('php')
        ->and($decoded['mcpServers']['marko-mcp']['args'])->toBe(['artisan', 'mcp:serve'])
        ->and($decoded['mcpServers']['marko-mcp']['env'])->toBe(['APP_ENV' => 'test']);
});

it('writes .cursor/rules/marko.mdc with Marko guidelines on install', function (): void {
    (new CursorAgent(devaiRunner()))->install(devaiContext('# Marko Guidelines'), $this->root);

    $written = (string) file_get_contents($this->root . '/.cursor/rules/marko.mdc');
    expect($written)->toContain("---\ndescription: Marko Framework guidelines\nalwaysApply: true\n---")
        ->and($written)->toContain('# Marko Guidelines');
});

it('writes AGENTS.md if not present and does not overwrite it on re-install', function (): void {
    $agent = new CursorAgent(devaiRunner());
    $agentsPath = $this->root . '/AGENTS.md';

    $agent->install(devaiContext('# Marko Guidelines'), $this->root);
    expect(file_get_contents($agentsPath))->toBe('# Marko Guidelines');

    file_put_contents($agentsPath, '# Custom content');
    $agent->install(devaiContext('# Marko Guidelines'), $this->root);
    expect(file_get_contents($agentsPath))->toBe('# Custom content');
});
