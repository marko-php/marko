<?php

declare(strict_types=1);

use Marko\DevAi\Agents\GeminiCliAgent;
use Marko\DevAi\Contract\AgentInterface;
use Marko\DevAi\ValueObject\McpRegistration;
use Marko\DevAi\ValueObject\SkillBundle;
use Marko\DevAi\Writing\GuidelinesWriter;

beforeEach(function (): void {
    $this->root = devaiTempDir();
});

afterEach(function (): void {
    devaiRemoveDir($this->root);
});

it('reports name as gemini-cli', function (): void {
    expect((new GeminiCliAgent(devaiRunner()))->name())->toBe('gemini-cli');
});

it('detects installation when gemini binary is on PATH', function (): void {
    expect((new GeminiCliAgent(devaiRunner(onPath: true)))->isInstalled())->toBeTrue()
        ->and((new GeminiCliAgent(devaiRunner(onPath: false)))->isInstalled())->toBeFalse();
});

it('implements AgentInterface', function (): void {
    expect(new GeminiCliAgent(devaiRunner()))->toBeInstanceOf(AgentInterface::class);
});

it('creates GEMINI.md via the writer when it does not exist', function (): void {
    (new GeminiCliAgent(devaiRunner()))->install(devaiContext('# Marko Guidelines'), $this->root);

    $contents = (string) file_get_contents($this->root . '/GEMINI.md');
    expect($contents)->toContain('# Marko Guidelines')
        ->and($contents)->toContain(GuidelinesWriter::MARKER_BEGIN)
        ->and($contents)->toContain(GuidelinesWriter::MARKER_END);
});

it('preserves user content outside the markers in an existing GEMINI.md', function (): void {
    $geminiPath = $this->root . '/GEMINI.md';
    $userHeader = "# My custom header\n\n";
    file_put_contents(
        $geminiPath,
        $userHeader
        . GuidelinesWriter::MARKER_BEGIN . "\nOld generated\n"
        . GuidelinesWriter::MARKER_END . "\n",
    );

    (new GeminiCliAgent(devaiRunner()))->install(devaiContext('# New Guidelines'), $this->root);

    $contents = (string) file_get_contents($geminiPath);
    expect($contents)->toContain('# New Guidelines')
        ->and($contents)->toContain($userHeader)
        ->and($contents)->not->toContain('Old generated');
});

it('creates AGENTS.md via the writer when it does not exist', function (): void {
    (new GeminiCliAgent(devaiRunner()))->install(devaiContext('# Marko Guidelines'), $this->root);

    $contents = (string) file_get_contents($this->root . '/AGENTS.md');
    expect($contents)->toContain('# Marko Guidelines')
        ->and($contents)->toContain(GuidelinesWriter::MARKER_BEGIN)
        ->and($contents)->toContain(GuidelinesWriter::MARKER_END);
});

it('leaves a marker-stripped GEMINI.md untouched', function (): void {
    $geminiPath = $this->root . '/GEMINI.md';
    $original = "# User content only — no markers here\n";
    file_put_contents($geminiPath, $original);

    (new GeminiCliAgent(devaiRunner()))->install(devaiContext('# New'), $this->root);

    expect((string) file_get_contents($geminiPath))->toBe($original);
});

it('does not modify Gemini MCP registration or skill distribution behavior', function (): void {
    $runner = devaiRunner();
    $reg = new McpRegistration('marko-mcp', 'npx', ['-y', '@marko/mcp']);
    (new GeminiCliAgent($runner))->install(devaiContext(mcp: $reg), $this->root);

    $addCall = null;
    foreach ($runner->calls as $call) {
        if ($call['command'] === 'gemini' && ($call['args'][0] ?? '') === 'mcp') {
            $addCall = $call;
        }
    }

    expect($addCall)->not->toBeNull()
        ->and($addCall['args'])->toBe(
            ['mcp', 'add', '-s', 'project', '-t', 'stdio', 'marko-mcp', 'npx', '-y', '@marko/mcp'],
        );
});

it('distributes skills to the .gemini/skills directory on install', function (): void {
    $bundle = new SkillBundle('marko', ['plan-create.md' => '# Plan Create']);
    (new GeminiCliAgent(devaiRunner()))->install(devaiContext(skills: [$bundle]), $this->root);

    expect(file_get_contents($this->root . '/.gemini/skills/plan-create.md'))->toBe('# Plan Create');
});
