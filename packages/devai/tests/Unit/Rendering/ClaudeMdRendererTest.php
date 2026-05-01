<?php

declare(strict_types=1);

use Marko\DevAi\Rendering\ClaudeMdRenderer;

beforeEach(function () {
    $this->renderer = new ClaudeMdRenderer();
});

it('renders CLAUDE.md starting with @AGENTS.md import line', function () {
    $result = $this->renderer->render();
    expect($result->body)->toContain('@AGENTS.md')
        ->and($result->filename)->toBe('CLAUDE.md');
    $lines = explode("\n", $result->body);
    $firstNonComment = '';
    foreach ($lines as $line) {
        if (str_starts_with(trim($line), '<!--') || trim($line) === '') {
            continue;
        }
        $firstNonComment = trim($line);
        break;
    }
    expect($firstNonComment)->toBe('@AGENTS.md');
});

it('appends Claude Code specific sections below the import', function () {
    $result = $this->renderer->render();
    $importPos = strpos($result->body, '@AGENTS.md');
    $claudeSectionPos = strpos($result->body, 'Claude Code Specific');
    expect($claudeSectionPos)->toBeGreaterThan($importPos);
});

it('references marko-mcp and marko-lsp tool names where useful', function () {
    $result = $this->renderer->render();
    expect($result->body)->toContain('marko-mcp')
        ->and($result->body)->toContain('marko-lsp');
});

it('produces deterministic output', function () {
    $r1 = $this->renderer->render(['projectName' => 'Test']);
    $r2 = $this->renderer->render(['projectName' => 'Test']);
    expect($r1->body)->toBe($r2->body);
});

it('includes regeneration marker', function () {
    $result = $this->renderer->render();
    expect($result->body)->toContain('marko devai:update');
});
