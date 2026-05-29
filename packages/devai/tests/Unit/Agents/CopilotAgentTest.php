<?php

declare(strict_types=1);

use Marko\DevAi\Agents\CopilotAgent;
use Marko\DevAi\Contract\AgentInterface;
use Marko\DevAi\ValueObject\McpRegistration;

beforeEach(function (): void {
    $this->tempRoot = devaiTempDir();
});

afterEach(function (): void {
    devaiRemoveDir($this->tempRoot);
});

it('reports name as copilot', function (): void {
    expect((new CopilotAgent($this->tempRoot))->name())->toBe('copilot');
});

it('detects a .github directory in the project', function (): void {
    $agent = new CopilotAgent($this->tempRoot);
    expect($agent->isInstalled())->toBeFalse();
    mkdir($this->tempRoot . '/.github', 0755, true);
    expect($agent->isInstalled())->toBeTrue();
});

it('implements AgentInterface', function (): void {
    expect(new CopilotAgent($this->tempRoot))->toBeInstanceOf(AgentInterface::class);
});

it('writes .github/copilot-instructions.md with Marko guidelines on install', function (): void {
    (new CopilotAgent($this->tempRoot))->install(devaiContext('# Marko Guidelines'), $this->tempRoot);

    $path = $this->tempRoot . '/.github/copilot-instructions.md';
    expect(file_exists($path))->toBeTrue()
        ->and(file_get_contents($path))->toBe('# Marko Guidelines');
});

it('writes AGENTS.md as a shared canonical source without overwriting on re-install', function (): void {
    $agent = new CopilotAgent($this->tempRoot);
    $agentsPath = $this->tempRoot . '/AGENTS.md';
    expect(file_exists($agentsPath))->toBeFalse();

    $agent->install(devaiContext('# Marko Guidelines'), $this->tempRoot);
    expect(file_get_contents($agentsPath))->toBe('# Marko Guidelines');

    $agent->install(devaiContext('updated content'), $this->tempRoot);
    expect(file_get_contents($agentsPath))->toBe('# Marko Guidelines');
});

it('writes a .vscode/mcp.json entry for marko-mcp on install', function (): void {
    $reg = new McpRegistration(
        serverName: 'marko-mcp',
        command: 'php',
        args: ['artisan', 'mcp:serve'],
        env: ['APP_ENV' => 'local'],
    );

    (new CopilotAgent($this->tempRoot))->install(devaiContext(mcp: $reg), $this->tempRoot);

    $config = json_decode((string) file_get_contents($this->tempRoot . '/.vscode/mcp.json'), true);
    expect($config['servers']['marko-mcp'])->toBe([
        'type' => 'stdio',
        'command' => 'php',
        'args' => ['artisan', 'mcp:serve'],
        'env' => ['APP_ENV' => 'local'],
    ]);
});

it('merges into existing .vscode/mcp.json without removing other entries', function (): void {
    $vscodeDir = $this->tempRoot . '/.vscode';
    mkdir($vscodeDir, 0755, true);
    file_put_contents($vscodeDir . '/mcp.json', json_encode([
        'servers' => [
            'other-mcp' => ['type' => 'stdio', 'command' => 'other', 'args' => [], 'env' => []],
        ],
    ]));

    $reg = new McpRegistration('marko-mcp', 'php', ['artisan', 'mcp:serve']);
    (new CopilotAgent($this->tempRoot))->install(devaiContext(mcp: $reg), $this->tempRoot);

    $config = json_decode((string) file_get_contents($vscodeDir . '/mcp.json'), true);
    expect($config['servers'])->toHaveKey('other-mcp')
        ->and($config['servers'])->toHaveKey('marko-mcp');
});
