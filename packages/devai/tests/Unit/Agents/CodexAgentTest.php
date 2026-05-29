<?php

declare(strict_types=1);

use Marko\DevAi\Agents\CodexAgent;
use Marko\DevAi\Contract\AgentInterface;
use Marko\DevAi\ValueObject\McpRegistration;
use Marko\DevAi\ValueObject\SkillBundle;

it('reports name as codex', function (): void {
    expect((new CodexAgent(devaiRunner()))->name())->toBe('codex');
});

it('detects installation when codex binary is on PATH', function (): void {
    expect((new CodexAgent(devaiRunner(onPath: true)))->isInstalled())->toBeTrue()
        ->and((new CodexAgent(devaiRunner(onPath: false)))->isInstalled())->toBeFalse();
});

it('implements AgentInterface', function (): void {
    expect(new CodexAgent(devaiRunner()))->toBeInstanceOf(AgentInterface::class);
});

it('writes canonical AGENTS.md with Marko guidelines on install', function (): void {
    $root = devaiTempDir();

    try {
        (new CodexAgent(devaiRunner()))->install(devaiContext('# Marko Guidelines'), $root);

        expect(file_get_contents($root . '/AGENTS.md'))->toBe('# Marko Guidelines');
    } finally {
        devaiRemoveDir($root);
    }
});

it('registers marko-mcp via codex mcp add with the correct argument separator', function (): void {
    $root = devaiTempDir();

    try {
        $runner = devaiRunner();
        $mcp = new McpRegistration('marko-mcp', 'php', ['marko', 'mcp:serve']);
        (new CodexAgent($runner))->install(devaiContext(mcp: $mcp), $root);

        $addCall = null;
        foreach ($runner->calls as $call) {
            if ($call['command'] === 'codex' && ($call['args'][0] ?? '') === 'mcp' && ($call['args'][1] ?? '') === 'add') {
                $addCall = $call;
            }
        }

        expect($addCall)->not->toBeNull();
        $args = $addCall['args'];
        expect($args[0])->toBe('mcp')
            ->and($args[1])->toBe('add')
            ->and($args[2])->toBe('marko-mcp')
            ->and($args[3])->toBe('--')
            ->and($args[4])->toBe('php')
            ->and($args[5])->toBe('marko')
            ->and($args[6])->toBe('mcp:serve');
    } finally {
        devaiRemoveDir($root);
    }
});

it('distributes skills to the .agents/skills directory on install', function (): void {
    $root = devaiTempDir();

    try {
        $bundle = new SkillBundle(
            'marko',
            ['plan-create.md' => '# Plan Create', 'plan-orchestrate.md' => '# Plan Orchestrate']
        );
        (new CodexAgent(devaiRunner()))->install(devaiContext(skills: [$bundle]), $root);

        $skillsDir = $root . '/.agents/skills';
        expect(is_dir($skillsDir))->toBeTrue()
            ->and(file_get_contents($skillsDir . '/plan-create.md'))->toBe('# Plan Create')
            ->and(file_get_contents($skillsDir . '/plan-orchestrate.md'))->toBe('# Plan Orchestrate');
    } finally {
        devaiRemoveDir($root);
    }
});
