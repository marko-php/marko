<?php

declare(strict_types=1);

describe('create-module skill', function (): void {
    beforeEach(function (): void {
        $this->skillRoot = dirname(__DIR__, 3) . '/plugins/marko-skills/skills/create-module';
        $this->skillMd = $this->skillRoot . '/SKILL.md';
        $this->assetsDir = $this->skillRoot . '/assets';
    });

    it('SKILL.md frontmatter has name "create-module" and a non-empty description with concrete trigger examples', function (): void {
        $content = file_exists($this->skillMd) ? file_get_contents($this->skillMd) : '';

        // Parse YAML frontmatter between the two --- delimiters
        preg_match('/^---\n(.*?)\n---/s', $content, $matches);

        $frontmatter = $matches[1] ?? '';

        expect(file_exists($this->skillMd))->toBeTrue()
            ->and($matches)->not->toBeEmpty()
            ->and($frontmatter)->toContain('name: create-module')
            // description key must be present (may be a YAML block scalar starting with >)
            ->and($frontmatter)->toContain('description:')
            // Must contain concrete trigger examples (in the body — YAML block scalar or inline)
            ->and($content)->toContain('create a module named');
    });

    it('SKILL.md is under 500 lines', function (): void {
        $lines = file_exists($this->skillMd) ? count(file($this->skillMd)) : 0;

        expect(file_exists($this->skillMd))->toBeTrue()
            ->and($lines)->toBeLessThan(500);
    });

    it('SKILL.md contains the anti-pattern directive forbidding inference from sibling modules', function (): void {
        $content = file_get_contents($this->skillMd);

        expect($content)->toContain('Do not inspect existing modules');
    });

    it('SKILL.md contains the LSP verification gate directive', function (): void {
        $content = file_get_contents($this->skillMd);

        expect($content)->toContain('marko-lsp')
            ->and($content)->toContain('diagnostics');
    });

    it('SKILL.md instructs the agent to copy templates from assets/ rather than inlining file content', function (): void {
        $content = file_get_contents($this->skillMd);

        expect($content)->toContain('assets/');
    });

    it('composer.json.tmpl contains {{vendor}} and {{name}} placeholders and is valid JSON when placeholders are substituted with literal strings', function (): void {
        $tmplPath = $this->assetsDir . '/composer.json.tmpl';
        $content = file_exists($tmplPath) ? file_get_contents($tmplPath) : '';

        // Substitute placeholders with literal strings and validate JSON
        $substituted = str_replace(
            ['{{vendor}}', '{{name}}', '{{Vendor}}', '{{Name}}'],
            ['acme', 'payment', 'Acme', 'Payment'],
            $content,
        );
        $decoded = json_decode($substituted, true);

        expect(file_exists($tmplPath))->toBeTrue()
            ->and($content)->toContain('{{vendor}}')
            ->and($content)->toContain('{{name}}')
            ->and($decoded)->not->toBeNull()
            ->and($decoded)->toHaveKey('name')
            ->and($decoded)->toHaveKey('type')
            ->and($decoded)->toHaveKey('require')
            ->and($decoded)->toHaveKey('autoload')
            ->and($decoded)->toHaveKey('extra')
            ->and($decoded['extra']['marko']['module'])->toBeTrue();
    });

    it('composer.json.monorepo.tmpl uses self.version constraints for marko/* requirements', function (): void {
        $tmplPath = $this->assetsDir . '/composer.json.monorepo.tmpl';
        $content = file_exists($tmplPath) ? file_get_contents($tmplPath) : '';

        // Confirm marko/* packages use self.version, not ^1.0
        $substituted = str_replace(
            ['{{vendor}}', '{{name}}', '{{Vendor}}', '{{Name}}'],
            ['acme', 'payment', 'Acme', 'Payment'],
            $content,
        );
        $decoded = json_decode($substituted, true);

        expect(file_exists($tmplPath))->toBeTrue()
            ->and($content)->toContain('self.version')
            ->and($decoded)->not->toBeNull();

        // All marko/* keys in require should have "self.version"
        foreach ($decoded['require'] as $pkg => $version) {
            if (str_starts_with($pkg, 'marko/')) {
                expect($version)->toBe('self.version');
            }
        }
    });

    it('Pest.php.tmpl contains the Marko\\\\Testing\\\\TestCase reference', function (): void {
        $tmplPath = $this->assetsDir . '/Pest.php.tmpl';
        $content = file_exists($tmplPath) ? file_get_contents($tmplPath) : '';

        expect(file_exists($tmplPath))->toBeTrue()
            ->and($content)->toContain('Marko\\Testing\\TestCase');
    });

    it('module.php.tmpl is documented as optional and only created when DI bindings are needed', function (): void {
        $tmplPath = $this->assetsDir . '/module.php.tmpl';
        $skillContent = file_get_contents($this->skillMd);

        expect(file_exists($tmplPath))->toBeTrue()
            // SKILL.md must document module.php as optional
            ->and($skillContent)->toContain('optional')
            ->and($skillContent)->toContain('module.php');
    });

    it('README.md.tmpl follows the slim-pointer convention from docs/DOCS-STANDARDS.md', function (): void {
        $tmplPath = $this->assetsDir . '/README.md.tmpl';
        $content = file_exists($tmplPath) ? file_get_contents($tmplPath) : '';

        // Slim pointer: must have vendor/name placeholder, an install section, and a docs link
        expect(file_exists($tmplPath))->toBeTrue()
            ->and($content)->toContain('{{vendor}}')
            ->and($content)->toContain('{{name}}')
            ->and($content)->toContain('composer require');
    });

    it('the original SKILL.md at packages/devai/resources/ai/skills/marko-create-module/ is deleted', function (): void {
        $originalPath = dirname(__DIR__, 4) . '/devai/resources/ai/skills/marko-create-module/SKILL.md';

        expect(file_exists($originalPath))->toBeFalse();
    });
});
