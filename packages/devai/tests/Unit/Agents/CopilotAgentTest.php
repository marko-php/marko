<?php

declare(strict_types=1);

use Marko\DevAi\Agents\CopilotAgent;
use Marko\DevAi\Contract\SupportsGuidelines;
use Marko\DevAi\Contract\SupportsMcp;
use Marko\DevAi\ValueObject\GuidelinesContent;
use Marko\DevAi\ValueObject\McpRegistration;

beforeEach(function () {
    $this->tempRoot = sys_get_temp_dir() . '/devai-copilot-' . uniqid();
    mkdir($this->tempRoot, 0755, true);
});

afterEach(function () {
    // Clean up temp directory recursively
    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($this->tempRoot, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST,
    );
    foreach ($files as $file) {
        $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
    }
    rmdir($this->tempRoot);
});

it('reports name as copilot', function () {
    $agent = new CopilotAgent($this->tempRoot);
    expect($agent->name())->toBe('copilot');
});

it('detects a .github directory in the project', function () {
    $agent = new CopilotAgent($this->tempRoot);
    expect($agent->isInstalled())->toBeFalse();
    mkdir($this->tempRoot . '/.github', 0755, true);
    expect($agent->isInstalled())->toBeTrue();
});

it('writes .github/copilot-instructions.md with Marko guidelines', function () {
    $agent = new CopilotAgent($this->tempRoot);
    $content = new GuidelinesContent('# Marko Guidelines');

    $agent->writeGuidelines($content, $this->tempRoot);

    $path = $this->tempRoot . '/.github/copilot-instructions.md';
    expect(file_exists($path))->toBeTrue();
    expect(file_get_contents($path))->toBe('# Marko Guidelines');
});

it('writes or ensures AGENTS.md exists as a shared canonical source', function () {
    $agent = new CopilotAgent($this->tempRoot);
    $content = new GuidelinesContent('# Marko Guidelines');

    $agentsPath = $this->tempRoot . '/AGENTS.md';
    expect(file_exists($agentsPath))->toBeFalse();

    $agent->writeGuidelines($content, $this->tempRoot);

    expect(file_exists($agentsPath))->toBeTrue();
    expect(file_get_contents($agentsPath))->toBe('# Marko Guidelines');

    // Calling again should NOT overwrite existing AGENTS.md
    $agent->writeGuidelines(new GuidelinesContent('updated content'), $this->tempRoot);
    expect(file_get_contents($agentsPath))->toBe('# Marko Guidelines');
});

it('writes .vscode/mcp.json entry for marko-mcp', function () {
    $agent = new CopilotAgent($this->tempRoot);
    $reg = new McpRegistration(
        serverName: 'marko-mcp',
        command: 'php',
        args: ['artisan', 'mcp:serve'],
        env: ['APP_ENV' => 'local'],
    );

    $agent->registerMcpServer($reg, $this->tempRoot);

    $mcpPath = $this->tempRoot . '/.vscode/mcp.json';
    expect(file_exists($mcpPath))->toBeTrue();

    $config = json_decode(file_get_contents($mcpPath), true);
    expect($config['servers']['marko-mcp'])->toBe([
        'type' => 'stdio',
        'command' => 'php',
        'args' => ['artisan', 'mcp:serve'],
        'env' => ['APP_ENV' => 'local'],
    ]);
});

it('merges into existing .vscode/mcp.json without removing other entries', function () {
    $agent = new CopilotAgent($this->tempRoot);

    $vscodeDir = $this->tempRoot . '/.vscode';
    mkdir($vscodeDir, 0755, true);
    file_put_contents($vscodeDir . '/mcp.json', json_encode([
        'servers' => [
            'other-mcp' => ['type' => 'stdio', 'command' => 'other', 'args' => [], 'env' => []],
        ],
    ]));

    $reg = new McpRegistration(
        serverName: 'marko-mcp',
        command: 'php',
        args: ['artisan', 'mcp:serve'],
    );

    $agent->registerMcpServer($reg, $this->tempRoot);

    $config = json_decode(file_get_contents($vscodeDir . '/mcp.json'), true);
    expect($config['servers'])->toHaveKey('other-mcp');
    expect($config['servers'])->toHaveKey('marko-mcp');
});

it('supports Guidelines Mcp capabilities', function () {
    $agent = new CopilotAgent($this->tempRoot);
    expect($agent)->toBeInstanceOf(SupportsGuidelines::class);
    expect($agent)->toBeInstanceOf(SupportsMcp::class);
});
