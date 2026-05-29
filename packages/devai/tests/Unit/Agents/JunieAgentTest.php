<?php

declare(strict_types=1);

use Marko\DevAi\Agents\JunieAgent;
use Marko\DevAi\Contract\AgentInterface;
use Marko\DevAi\ValueObject\SkillBundle;

beforeEach(function (): void {
    $this->tempRoot = devaiTempDir();
});

afterEach(function (): void {
    devaiRemoveDir($this->tempRoot);
});

it('reports name as junie', function (): void {
    expect((new JunieAgent($this->tempRoot))->name())->toBe('junie');
});

it('detects JetBrains IDE or existing junie/ directory', function (): void {
    $agent = new JunieAgent($this->tempRoot);
    expect($agent->isInstalled())->toBeFalse();

    mkdir($this->tempRoot . '/.idea', 0755, true);
    expect($agent->isInstalled())->toBeTrue();

    rmdir($this->tempRoot . '/.idea');
    expect($agent->isInstalled())->toBeFalse();

    mkdir($this->tempRoot . '/junie', 0755, true);
    expect($agent->isInstalled())->toBeTrue();
});

it('implements AgentInterface', function (): void {
    expect(new JunieAgent($this->tempRoot))->toBeInstanceOf(AgentInterface::class);
});

it('writes the junie/ layout with Marko guidelines on install', function (): void {
    (new JunieAgent($this->tempRoot))->install(devaiContext('# Marko Guidelines'), $this->tempRoot);

    expect(is_dir($this->tempRoot . '/junie'))->toBeTrue()
        ->and(file_get_contents($this->tempRoot . '/junie/guidelines.md'))->toBe('# Marko Guidelines');
});

it('ensures AGENTS.md is present and does not overwrite it on re-install', function (): void {
    $agent = new JunieAgent($this->tempRoot);
    $agent->install(devaiContext('# Marko Guidelines'), $this->tempRoot);
    expect(file_get_contents($this->tempRoot . '/AGENTS.md'))->toBe('# Marko Guidelines');

    file_put_contents($this->tempRoot . '/AGENTS.md', '# Custom');
    $agent->install(devaiContext('# Marko Guidelines'), $this->tempRoot);
    expect(file_get_contents($this->tempRoot . '/AGENTS.md'))->toBe('# Custom');
});

it('distributes skills to the junie/skills directory on install', function (): void {
    $bundles = [
        new SkillBundle('marko-skills', [
            'plan-create.md' => '# Plan Create skill',
            'plan-orchestrate.md' => '# Plan Orchestrate skill',
        ]),
    ];
    (new JunieAgent($this->tempRoot))->install(devaiContext(skills: $bundles), $this->tempRoot);

    expect(file_get_contents($this->tempRoot . '/junie/skills/plan-create.md'))->toBe('# Plan Create skill')
        ->and(file_get_contents($this->tempRoot . '/junie/skills/plan-orchestrate.md'))->toBe(
            '# Plan Orchestrate skill'
        );
});
