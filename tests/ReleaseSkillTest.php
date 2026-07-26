<?php

declare(strict_types=1);

$skillPath = dirname(__DIR__) . '/.claude/skills/release/SKILL.md';

it('creates .claude/skills/release/SKILL.md with discoverable frontmatter', function () use ($skillPath): void {
    expect(file_exists($skillPath))->toBeTrue('.claude/skills/release/SKILL.md must exist');

    $content = file_get_contents($skillPath);

    expect($content)
        ->toStartWith("---\n")
        ->toContain('name: release')
        ->toContain('description:');
});

it('takes no arguments and decides the version in conversation', function () use ($skillPath): void {
    $content = file_get_contents($skillPath);

    expect($content)
        ->toContain('## Arguments')
        ->toContain('None.')
        ->toContain('/release');
});

it('stops at a single approval gate before tagging', function () use ($skillPath): void {
    $content = file_get_contents($skillPath);

    expect($content)
        ->toContain('one approval gate')
        ->toContain('Present the recommendation, then stop')
        ->toContain('Never tag without explicit approval');
});

it('documents 0.x version rules including Composer caret reachability', function () use ($skillPath): void {
    $content = file_get_contents($skillPath);

    expect($content)
        ->toContain('## Step 4 — Decide the version')
        ->toContain('**Patch**')
        ->toContain('**Minor**')
        ->toContain('^0.8.4')
        ->toContain('composer update');
});

it('audits labels against every category in .github/release.yml', function () use ($skillPath): void {
    $content = file_get_contents($skillPath);
    $releaseConfig = file_get_contents(dirname(__DIR__) . '/.github/release.yml');
    $categories = substr($releaseConfig, (int) strpos($releaseConfig, 'categories:'));

    preg_match_all('/- title: (.+)/', $categories, $titles);
    preg_match_all('/^\s+- (?!title: )(\S+)$/m', $categories, $labels);

    expect($titles[1])->not->toBeEmpty()
        ->and($labels[1])->not->toBeEmpty();

    foreach ($titles[1] as $title) {
        expect($content)->toContain(trim($title));
    }

    foreach ($labels[1] as $label) {
        expect($content)->toContain("`$label`");
    }
});

it('delegates mechanical work to bin/release.sh, never the changelog', function () use ($skillPath): void {
    $content = file_get_contents($skillPath);

    expect($content)
        ->toContain('./bin/release.sh <version>')
        ->toContain('Never hand-edit `CHANGELOG.md`')
        ->toContain('integration-destructive');
});

it('verifies preconditions before proposing a release', function () use ($skillPath): void {
    $content = file_get_contents($skillPath);

    expect($content)
        ->toContain('## Step 1 — Verify preconditions')
        ->toContain('PHP_BIN')
        ->toContain('develop');
});

it('points at the skill from .claude/release-process.md', function (): void {
    $content = file_get_contents(dirname(__DIR__) . '/.claude/release-process.md');

    expect($content)->toContain('/release');
});
