<?php

declare(strict_types=1);

use Marko\DevAi\Agents\JunieAgent;
use Marko\DevAi\Contract\SupportsGuidelines;
use Marko\DevAi\Contract\SupportsSkills;
use Marko\DevAi\ValueObject\GuidelinesContent;
use Marko\DevAi\ValueObject\SkillBundle;

beforeEach(function () {
    $this->tempRoot = sys_get_temp_dir() . '/devai-junie-' . uniqid();
    mkdir($this->tempRoot, 0755, true);
});

afterEach(function () {
    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($this->tempRoot, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST,
    );
    foreach ($files as $file) {
        $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
    }
    rmdir($this->tempRoot);
});

it('reports name as junie', function () {
    $agent = new JunieAgent($this->tempRoot);
    expect($agent->name())->toBe('junie');
});

it('detects JetBrains IDE or existing junie/ directory', function () {
    $agent = new JunieAgent($this->tempRoot);
    expect($agent->isInstalled())->toBeFalse();

    mkdir($this->tempRoot . '/.idea', 0755, true);
    expect($agent->isInstalled())->toBeTrue();

    rmdir($this->tempRoot . '/.idea');
    expect($agent->isInstalled())->toBeFalse();

    mkdir($this->tempRoot . '/junie', 0755, true);
    expect($agent->isInstalled())->toBeTrue();
});

it('writes junie/ layout with Marko guidelines', function () {
    $agent = new JunieAgent($this->tempRoot);
    $content = new GuidelinesContent('# Marko Guidelines');
    $agent->writeGuidelines($content, $this->tempRoot);

    expect(is_dir($this->tempRoot . '/junie'))->toBeTrue()
        ->and(file_exists($this->tempRoot . '/junie/guidelines.md'))->toBeTrue()
        ->and(file_get_contents($this->tempRoot . '/junie/guidelines.md'))->toBe('# Marko Guidelines');
});

it('ensures AGENTS.md is present', function () {
    $agent = new JunieAgent($this->tempRoot);
    $content = new GuidelinesContent('# Marko Guidelines');
    $agent->writeGuidelines($content, $this->tempRoot);

    expect(file_exists($this->tempRoot . '/AGENTS.md'))->toBeTrue()
        ->and(file_get_contents($this->tempRoot . '/AGENTS.md'))->toBe('# Marko Guidelines');

    // Should not overwrite existing AGENTS.md
    file_put_contents($this->tempRoot . '/AGENTS.md', '# Custom');
    $agent->writeGuidelines($content, $this->tempRoot);
    expect(file_get_contents($this->tempRoot . '/AGENTS.md'))->toBe('# Custom');
});

it('supports Guidelines Skills capabilities', function () {
    $agent = new JunieAgent($this->tempRoot);
    expect($agent)->toBeInstanceOf(SupportsGuidelines::class)
        ->and($agent)->toBeInstanceOf(SupportsSkills::class);

    $bundles = [
        new SkillBundle('marko-skills', [
            'plan-create.md' => '# Plan Create skill',
            'plan-orchestrate.md' => '# Plan Orchestrate skill',
        ]),
    ];
    $agent->distributeSkills($bundles, $this->tempRoot);

    expect(file_exists($this->tempRoot . '/junie/skills/plan-create.md'))->toBeTrue()
        ->and(file_get_contents($this->tempRoot . '/junie/skills/plan-create.md'))->toBe('# Plan Create skill')
        ->and(file_exists($this->tempRoot . '/junie/skills/plan-orchestrate.md'))->toBeTrue()
        ->and(file_get_contents($this->tempRoot . '/junie/skills/plan-orchestrate.md'))->toBe('# Plan Orchestrate skill');
});
