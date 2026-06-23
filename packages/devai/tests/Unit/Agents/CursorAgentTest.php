<?php

declare(strict_types=1);

use Marko\DevAi\Agents\CursorAgent;
use Marko\DevAi\Contract\AgentInterface;
use Marko\DevAi\ValueObject\McpRegistration;
use Marko\DevAi\Writing\GuidelinesWriter;

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

it('creates the cursor mdc rule file with frontmatter and the body inside the marker region', function (): void {
    (new CursorAgent(devaiRunner()))->install(devaiContext('# Marko Guidelines'), $this->root);

    $written = (string) file_get_contents($this->root . '/.cursor/rules/marko.mdc');
    expect($written)->toStartWith('---')
        ->and($written)->toContain("---\ndescription: Marko Framework guidelines\nalwaysApply: true\n---")
        ->and($written)->toContain(GuidelinesWriter::MARKER_BEGIN)
        ->and($written)->toContain(GuidelinesWriter::MARKER_END)
        ->and($written)->toContain('# Marko Guidelines');
});

it('preserves the frontmatter and any user content outside the markers in an existing mdc file', function (): void {
    $mdcPath = $this->root . '/.cursor/rules/marko.mdc';
    mkdir($this->root . '/.cursor/rules', 0755, true);
    $frontmatter = "---\ndescription: Marko Framework guidelines\nalwaysApply: true\n---\n\n";
    $userNote = "\n\n> User note added below markers.\n";
    file_put_contents(
        $mdcPath,
        $frontmatter
        . GuidelinesWriter::MARKER_BEGIN . "\nOld body\n" . GuidelinesWriter::MARKER_END
        . $userNote,
    );

    (new CursorAgent(devaiRunner()))->install(devaiContext('# New Guidelines'), $this->root);

    $written = (string) file_get_contents($mdcPath);
    expect($written)->toStartWith('---')
        ->and($written)->toContain($frontmatter)
        ->and($written)->toContain('# New Guidelines')
        ->and($written)->not->toContain('Old body')
        ->and($written)->toContain($userNote);
});

it('creates AGENTS.md via the writer when it does not exist', function (): void {
    $agentsPath = $this->root . '/AGENTS.md';

    (new CursorAgent(devaiRunner()))->install(devaiContext('# Marko Guidelines'), $this->root);

    $written = (string) file_get_contents($agentsPath);
    expect($written)->toContain('# Marko Guidelines')
        ->and($written)->toContain(GuidelinesWriter::MARKER_BEGIN)
        ->and($written)->toContain(GuidelinesWriter::MARKER_END);
});

it('leaves a marker-stripped mdc file untouched', function (): void {
    $mdcPath = $this->root . '/.cursor/rules/marko.mdc';
    mkdir($this->root . '/.cursor/rules', 0755, true);
    $original = "---\ndescription: Marko Framework guidelines\nalwaysApply: true\n---\n\nNo markers here.\n";
    file_put_contents($mdcPath, $original);

    (new CursorAgent(devaiRunner()))->install(devaiContext('# Marko Guidelines'), $this->root);

    expect((string) file_get_contents($mdcPath))->toBe($original);
});

it('leaves a pre-existing marker-less AGENTS.md untouched', function (): void {
    $agentsPath = $this->root . '/AGENTS.md';
    file_put_contents($agentsPath, '# Custom content');

    (new CursorAgent(devaiRunner()))->install(devaiContext('# Marko Guidelines'), $this->root);

    expect((string) file_get_contents($agentsPath))->toBe('# Custom content');
});

it('does not modify cursor MCP registration behavior', function (): void {
    mkdir($this->root . '/.cursor', 0755, true);
    file_put_contents(
        $this->root . '/.cursor/mcp.json',
        json_encode(
            ['mcpServers' => ['other-server' => ['command' => 'other', 'args' => [], 'env' => []]]],
            JSON_PRETTY_PRINT,
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
