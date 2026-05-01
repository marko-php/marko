<?php

declare(strict_types=1);

use Marko\DevAi\Agents\CursorAgent;
use Marko\DevAi\Contract\SupportsGuidelines;
use Marko\DevAi\Contract\SupportsMcp;
use Marko\DevAi\Process\CommandRunnerInterface;
use Marko\DevAi\ValueObject\GuidelinesContent;
use Marko\DevAi\ValueObject\McpRegistration;

function makeCursorRunner(bool $installed = false): CommandRunnerInterface
{
    return new class ($installed) implements CommandRunnerInterface
    {
        public function __construct(private bool $installed) {}

        public function run(string $command, array $args = []): array
        {
            return ['exitCode' => 0, 'stdout' => '', 'stderr' => ''];
        }

        public function isOnPath(string $binary): bool
        {
            return $this->installed;
        }
    };
}

it('reports name as cursor', function () {
    $agent = new CursorAgent(makeCursorRunner());
    expect($agent->name())->toBe('cursor');
});

it('detects Cursor installation', function () {
    $installed = new CursorAgent(makeCursorRunner(true));
    $notInstalled = new CursorAgent(makeCursorRunner(false));

    expect($installed->isInstalled())->toBeTrue()
        ->and($notInstalled->isInstalled())->toBeFalse();
});

it('writes or merges .cursor/mcp.json entry for marko-mcp', function () {
    $dir = sys_get_temp_dir() . '/cursor_agent_mcp_' . uniqid();
    mkdir($dir . '/.cursor', 0755, true);

    // Pre-populate with an existing server entry
    $existing = ['mcpServers' => ['other-server' => ['command' => 'other', 'args' => [], 'env' => []]]];
    file_put_contents($dir . '/.cursor/mcp.json', json_encode($existing, JSON_PRETTY_PRINT));

    $agent = new CursorAgent(makeCursorRunner());
    $reg = new McpRegistration('marko-mcp', 'php', ['artisan', 'mcp:serve'], ['APP_ENV' => 'test']);
    $agent->registerMcpServer($reg, $dir);

    $mcpPath = $dir . '/.cursor/mcp.json';
    expect(is_file($mcpPath))->toBeTrue();

    $decoded = json_decode(file_get_contents($mcpPath), true);
    expect($decoded['mcpServers'])->toHaveKey('other-server')
        ->and($decoded['mcpServers'])->toHaveKey('marko-mcp')
        ->and($decoded['mcpServers']['marko-mcp']['command'])->toBe('php')
        ->and($decoded['mcpServers']['marko-mcp']['args'])->toBe(['artisan', 'mcp:serve'])
        ->and($decoded['mcpServers']['marko-mcp']['env'])->toBe(['APP_ENV' => 'test']);

    // Cleanup
    unlink($mcpPath);
    rmdir($dir . '/.cursor');
    rmdir($dir);
});

it('writes .cursor/rules/marko.mdc with Marko guidelines', function () {
    $dir = sys_get_temp_dir() . '/cursor_agent_test_' . uniqid();
    mkdir($dir, 0755, true);

    $agent = new CursorAgent(makeCursorRunner());
    $content = new GuidelinesContent('# Marko Guidelines');
    $agent->writeGuidelines($content, $dir);

    $mdcPath = $dir . '/.cursor/rules/marko.mdc';
    expect(is_file($mdcPath))->toBeTrue();

    $written = file_get_contents($mdcPath);
    expect($written)->toContain("---\ndescription: Marko Framework guidelines\nalwaysApply: true\n---")
        ->and($written)->toContain('# Marko Guidelines');

    // Cleanup
    array_map('unlink', glob($dir . '/.cursor/rules/*'));
    rmdir($dir . '/.cursor/rules');
    rmdir($dir . '/.cursor');
    unlink($dir . '/AGENTS.md');
    rmdir($dir);
});

it('writes AGENTS.md if not present', function () {
    $dir = sys_get_temp_dir() . '/cursor_agent_agents_' . uniqid();
    mkdir($dir, 0755, true);

    $agent = new CursorAgent(makeCursorRunner());
    $content = new GuidelinesContent('# Marko Guidelines');

    // First call: AGENTS.md should be created
    $agent->writeGuidelines($content, $dir);
    $agentsPath = $dir . '/AGENTS.md';
    expect(is_file($agentsPath))->toBeTrue()
        ->and(file_get_contents($agentsPath))->toBe('# Marko Guidelines');

    // Overwrite AGENTS.md with custom content
    file_put_contents($agentsPath, '# Custom content');

    // Second call: AGENTS.md should NOT be overwritten
    $agent->writeGuidelines($content, $dir);
    expect(file_get_contents($agentsPath))->toBe('# Custom content');

    // Cleanup
    array_map('unlink', glob($dir . '/.cursor/rules/*'));
    rmdir($dir . '/.cursor/rules');
    rmdir($dir . '/.cursor');
    unlink($agentsPath);
    rmdir($dir);
});

it('supports Guidelines Mcp capabilities', function () {
    $agent = new CursorAgent(makeCursorRunner());

    expect($agent)->toBeInstanceOf(SupportsGuidelines::class)
        ->and($agent)->toBeInstanceOf(SupportsMcp::class);
});
