<?php

declare(strict_types=1);

use Marko\DevAi\Agents\JunieAgent;
use Marko\DevAi\Contract\AgentInterface;
use Marko\DevAi\ValueObject\SkillBundle;
use Marko\DevAi\Writing\GuidelinesWriter;

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

it('creates junie guidelines.md via the writer when it does not exist', function (): void {
    (new JunieAgent($this->tempRoot))->install(devaiContext('# Marko Guidelines'), $this->tempRoot);

    $written = (string) file_get_contents($this->tempRoot . '/junie/guidelines.md');
    expect(is_dir($this->tempRoot . '/junie'))->toBeTrue()
        ->and($written)->toContain('# Marko Guidelines')
        ->and($written)->toContain(GuidelinesWriter::MARKER_BEGIN)
        ->and($written)->toContain(GuidelinesWriter::MARKER_END);
});

it('preserves user content outside the markers in an existing junie guidelines.md', function (): void {
    $beginMarker = GuidelinesWriter::MARKER_BEGIN;
    $endMarker = GuidelinesWriter::MARKER_END;
    $existing = "# My Header\n\n$beginMarker\nOld content\n$endMarker\n\n## My Footer\n";
    mkdir($this->tempRoot . '/junie', 0755, true);
    file_put_contents($this->tempRoot . '/junie/guidelines.md', $existing);

    (new JunieAgent($this->tempRoot))->install(devaiContext('# New Guidelines'), $this->tempRoot);

    $written = (string) file_get_contents($this->tempRoot . '/junie/guidelines.md');
    expect($written)->toContain('# My Header')
        ->and($written)->toContain('## My Footer')
        ->and($written)->toContain('# New Guidelines');
});

it('creates AGENTS.md via the writer when it does not exist', function (): void {
    (new JunieAgent($this->tempRoot))->install(devaiContext('# Marko Guidelines'), $this->tempRoot);

    $written = (string) file_get_contents($this->tempRoot . '/AGENTS.md');
    expect($written)->toContain('# Marko Guidelines')
        ->and($written)->toContain(GuidelinesWriter::MARKER_BEGIN)
        ->and($written)->toContain(GuidelinesWriter::MARKER_END);
});

it('leaves a marker-stripped junie guidelines.md untouched', function (): void {
    $noMarkerContent = "# My junie guidelines\n\nSome user content without any markers.\n";
    mkdir($this->tempRoot . '/junie', 0755, true);
    file_put_contents($this->tempRoot . '/junie/guidelines.md', $noMarkerContent);

    (new JunieAgent($this->tempRoot))->install(devaiContext('# New Guidelines'), $this->tempRoot);

    expect((string) file_get_contents($this->tempRoot . '/junie/guidelines.md'))->toBe($noMarkerContent);
});

it('does not modify Junie skill distribution behavior', function (): void {
    $bundles = [
        new SkillBundle('marko-skills', [
            'plan-create.md' => '# Plan Create skill',
            'plan-orchestrate.md' => '# Plan Orchestrate skill',
        ]),
    ];
    (new JunieAgent($this->tempRoot))->install(devaiContext(skills: $bundles), $this->tempRoot);

    expect(file_get_contents($this->tempRoot . '/junie/skills/plan-create.md'))->toBe('# Plan Create skill')
        ->and(file_get_contents($this->tempRoot . '/junie/skills/plan-orchestrate.md'))->toBe(
            '# Plan Orchestrate skill',
        );
});
