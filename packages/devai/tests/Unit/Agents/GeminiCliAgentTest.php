<?php

declare(strict_types=1);

use Marko\DevAi\Agents\GeminiCliAgent;
use Marko\DevAi\Contract\AgentInterface;
use Marko\DevAi\ValueObject\McpRegistration;

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

it('writes GEMINI.md with Marko guidelines on install', function (): void {
    (new GeminiCliAgent(devaiRunner()))->install(devaiContext('# Marko Guidelines'), $this->root);

    expect(file_get_contents($this->root . '/GEMINI.md'))->toBe('# Marko Guidelines');
});

it('ensures AGENTS.md is present and does not overwrite it on re-install', function (): void {
    $agent = new GeminiCliAgent(devaiRunner());
    $agent->install(devaiContext('# Marko Guidelines'), $this->root);
    expect(file_exists($this->root . '/AGENTS.md'))->toBeTrue();

    file_put_contents($this->root . '/AGENTS.md', 'existing');
    $agent->install(devaiContext('# New'), $this->root);
    expect(file_get_contents($this->root . '/AGENTS.md'))->toBe('existing');
});

it('registers marko-mcp via gemini mcp add command on install', function (): void {
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
            ['mcp', 'add', '-s', 'project', '-t', 'stdio', 'marko-mcp', 'npx', '-y', '@marko/mcp']
        );
});

it('distributes skills to the .gemini/skills directory on install', function (): void {
    $bundle = new Marko\DevAi\ValueObject\SkillBundle('marko', ['plan-create.md' => '# Plan Create']);
    (new GeminiCliAgent(devaiRunner()))->install(devaiContext(skills: [$bundle]), $this->root);

    expect(file_get_contents($this->root . '/.gemini/skills/plan-create.md'))->toBe('# Plan Create');
});
