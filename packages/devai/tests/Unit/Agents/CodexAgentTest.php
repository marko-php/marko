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

        expect(file_get_contents($root . '/AGENTS.md'))->toContain('# Marko Guidelines')
            ->and(file_get_contents($root . '/AGENTS.md'))->toContain('<!-- BEGIN marko:devai -->');
    } finally {
        devaiRemoveDir($root);
    }
});

it('creates AGENTS.md via the writer when it does not exist', function (): void {
    $root = devaiTempDir();

    try {
        (new CodexAgent(devaiRunner()))->install(devaiContext('# Marko Guidelines'), $root);

        expect(file_exists($root . '/AGENTS.md'))->toBeTrue()
            ->and(file_get_contents($root . '/AGENTS.md'))->toContain('# Marko Guidelines')
            ->and(file_get_contents($root . '/AGENTS.md'))->toContain('<!-- BEGIN marko:devai -->');
    } finally {
        devaiRemoveDir($root);
    }
});

it('preserves user content outside the markers in an existing AGENTS.md', function (): void {
    $root = devaiTempDir();

    try {
        $agentsPath = $root . '/AGENTS.md';
        file_put_contents(
            $agentsPath,
            "# My custom header\n\n<!-- BEGIN marko:devai -->\nold body\n<!-- END marko:devai -->\n\n## My custom footer\n",
        );

        (new CodexAgent(devaiRunner()))->install(devaiContext('# New Guidelines'), $root);

        $content = (string) file_get_contents($agentsPath);
        expect($content)->toContain('# My custom header')
            ->and($content)->toContain('## My custom footer')
            ->and($content)->toContain('# New Guidelines');
    } finally {
        devaiRemoveDir($root);
    }
});

it('marker-merges the guideline body into AGENTS.md on update', function (): void {
    $root = devaiTempDir();

    try {
        $agentsPath = $root . '/AGENTS.md';
        file_put_contents(
            $agentsPath,
            "<!-- BEGIN marko:devai -->\nold body\n<!-- END marko:devai -->\n",
        );

        (new CodexAgent(devaiRunner()))->install(devaiContext('# Updated Guidelines'), $root);

        $content = (string) file_get_contents($agentsPath);
        expect($content)->toContain('# Updated Guidelines')
            ->and($content)->not->toContain('old body')
            ->and($content)->toContain('<!-- BEGIN marko:devai -->')
            ->and($content)->toContain('<!-- END marko:devai -->');
    } finally {
        devaiRemoveDir($root);
    }
});

it('does not modify MCP registration or skill distribution behavior', function (): void {
    $root = devaiTempDir();

    try {
        $runner = devaiRunner();
        $mcp = new McpRegistration('marko-mcp', 'php', ['marko', 'mcp:serve']);
        $bundle = new SkillBundle('marko', ['skill.md' => '# Skill']);
        (new CodexAgent($runner))->install(devaiContext(mcp: $mcp, skills: [$bundle]), $root);

        $addCall = null;
        foreach ($runner->calls as $call) {
            if ($call['command'] === 'codex' && ($call['args'][0] ?? '') === 'mcp' && ($call['args'][1] ?? '') === 'add') {
                $addCall = $call;
            }
        }

        expect($addCall)->not->toBeNull()
            ->and($addCall['args'][2])->toBe('marko-mcp')
            ->and(is_dir($root . '/.agents/skills'))->toBeTrue()
            ->and(file_get_contents($root . '/.agents/skills/skill.md'))->toBe('# Skill');
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
            ['plan-create.md' => '# Plan Create', 'plan-orchestrate.md' => '# Plan Orchestrate'],
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
