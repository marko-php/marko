<?php

declare(strict_types=1);

$root = dirname(__DIR__);

it('removes the entire demo/ directory', function () use ($root): void {
    expect(is_dir($root . '/demo'))->toBeFalse('demo/ directory should not exist');
});

it('updates CLAUDE.md to remove demo/ references and the demo application section', function () use ($root): void {
    $content = file_get_contents($root . '/CLAUDE.md');

    expect($content)
        ->not->toContain('## Demo Application')
        ->not->toContain('demo/')
        ->not->toContain('demo/app/')
        ->not->toContain('CRITICAL: Plans must NEVER include demo/app/');
});

it('removes any other references to demo/ in .claude/ configuration files', function () use ($root): void {
    $files = [
        $root . '/.claude/architecture.md',
        $root . '/.claude/project-overview.md',
        $root . '/.claude/testing.md',
    ];

    foreach ($files as $file) {
        if (file_exists($file)) {
            $content = file_get_contents($file);
            expect($content)->not->toContain('demo/', "File {$file} still references demo/");
        }
    }
});
