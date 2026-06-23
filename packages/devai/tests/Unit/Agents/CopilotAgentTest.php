<?php

declare(strict_types=1);

use Marko\DevAi\Agents\CopilotAgent;
use Marko\DevAi\Contract\AgentInterface;
use Marko\DevAi\ValueObject\McpRegistration;
use Marko\DevAi\Writing\GuidelinesWriter;

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

it('creates copilot-instructions.md via the writer when it does not exist', function (): void {
    (new CopilotAgent($this->tempRoot))->install(devaiContext('# Marko Guidelines'), $this->tempRoot);

    $path = $this->tempRoot . '/.github/copilot-instructions.md';
    expect(file_exists($path))->toBeTrue()
        ->and(file_get_contents($path))->toContain('# Marko Guidelines')
        ->and(file_get_contents($path))->toContain(GuidelinesWriter::MARKER_BEGIN)
        ->and(file_get_contents($path))->toContain(GuidelinesWriter::MARKER_END);
});

it('preserves user content outside the markers in an existing copilot-instructions.md', function (): void {
    $path = $this->tempRoot . '/.github/copilot-instructions.md';
    mkdir(dirname($path), 0755, true);
    $existing = "# My Custom Header\n\n"
        . GuidelinesWriter::MARKER_BEGIN . "\n"
        . "old generated content\n"
        . GuidelinesWriter::MARKER_END . "\n"
        . "\n# My Footer\n";
    file_put_contents($path, $existing);

    (new CopilotAgent($this->tempRoot))->install(devaiContext('# Marko Guidelines'), $this->tempRoot);

    $content = (string) file_get_contents($path);
    expect($content)->toContain('# My Custom Header')
        ->and($content)->toContain('# My Footer')
        ->and($content)->toContain('# Marko Guidelines')
        ->and($content)->toContain(GuidelinesWriter::MARKER_BEGIN)
        ->and($content)->toContain(GuidelinesWriter::MARKER_END);
});

it('creates AGENTS.md via the writer when it does not exist', function (): void {
    $agentsPath = $this->tempRoot . '/AGENTS.md';
    expect(file_exists($agentsPath))->toBeFalse();

    (new CopilotAgent($this->tempRoot))->install(devaiContext('# Marko Guidelines'), $this->tempRoot);

    expect(file_exists($agentsPath))->toBeTrue()
        ->and(file_get_contents($agentsPath))->toContain('# Marko Guidelines')
        ->and(file_get_contents($agentsPath))->toContain(GuidelinesWriter::MARKER_BEGIN)
        ->and(file_get_contents($agentsPath))->toContain(GuidelinesWriter::MARKER_END);
});

it('leaves a marker-stripped copilot-instructions.md untouched', function (): void {
    $path = $this->tempRoot . '/.github/copilot-instructions.md';
    mkdir(dirname($path), 0755, true);
    $markerlessContent = "# User-written guidelines, no markers here\n";
    file_put_contents($path, $markerlessContent);

    (new CopilotAgent($this->tempRoot))->install(devaiContext('# Marko Guidelines'), $this->tempRoot);

    expect(file_get_contents($path))->toBe($markerlessContent);
});

it('does not modify Copilot MCP registration behavior', function (): void {
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
