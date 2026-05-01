<?php

declare(strict_types=1);

describe('README.md', function (): void {
    beforeEach(function (): void {
        $this->readmePath = dirname(__DIR__, 2) . '/README.md';
        $this->contents = file_exists($this->readmePath)
            ? file_get_contents($this->readmePath)
            : null;
    });

    it('README.md starts with the package title (# marko/claude-plugins or similar)', function (): void {
        expect($this->contents)->not->toBeNull()
            ->and($this->contents)->toStartWith('# marko/claude-plugins');
    });

    it('README.md includes a one-paragraph description of what the package provides', function (): void {
        expect($this->contents)->not->toBeNull()
            ->and($this->contents)->toContain('Claude Code')
            ->and($this->contents)->toContain('marketplace');
    });

    it('README.md lists all three plugins (marko-skills, marko-lsp, marko-mcp) with one-line descriptions', function (): void {
        expect($this->contents)->not->toBeNull()
            ->and($this->contents)->toContain('marko-skills')
            ->and($this->contents)->toContain('marko-lsp')
            ->and($this->contents)->toContain('marko-mcp');
    });

    it('README.md includes the install command via marko devai:install', function (): void {
        expect($this->contents)->not->toBeNull()
            ->and($this->contents)->toContain('devai:install');
    });

    it('README.md links to the full docs page in the docs site', function (): void {
        expect($this->contents)->not->toBeNull()
            ->and($this->contents)->toContain('https://marko.build/docs/ai-assisted-development/');
    });

    it('README.md follows the slim-pointer convention from docs/DOCS-STANDARDS.md (no exhaustive feature lists, no inline code samples beyond a quick install example)', function (): void {
        expect($this->contents)->not->toBeNull();

        // Slim-pointer: file should be short (under 80 lines)
        $lines = substr_count($this->contents, "\n") + 1;
        expect($lines)->toBeLessThan(80)
            // Must have a Documentation section
            ->and($this->contents)->toContain('## Documentation');
    });
});
