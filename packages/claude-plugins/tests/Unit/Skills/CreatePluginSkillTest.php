<?php

declare(strict_types=1);

describe('create-plugin skill', function (): void {
    beforeEach(function (): void {
        $this->skillRoot = dirname(__DIR__, 3) . '/plugins/marko-skills/skills/create-plugin';
        $this->skillPath = $this->skillRoot . '/SKILL.md';
        $this->skillContent = file_exists($this->skillPath) ? file_get_contents($this->skillPath) : '';
    });

    it(
        'SKILL.md frontmatter has name "create-plugin" and a description with concrete trigger examples (e.g., "add a plugin for X", "intercept Y method", "extend Z class behavior")',
        function (): void {
            expect(file_exists($this->skillPath))->toBeTrue()
                ->and($this->skillContent)->toContain('name: create-plugin')
                // Description must be "pushy" with concrete trigger examples
                ->and($this->skillContent)->toContain('add a plugin')
                ->and($this->skillContent)->toContain('intercept')
                ->and($this->skillContent)->toContain('extend');
        },
    );

    it('SKILL.md is under 500 lines', function (): void {
        $lineCount = file_exists($this->skillPath) ? count(file($this->skillPath)) : 0;

        expect(file_exists($this->skillPath))->toBeTrue()
            ->and($lineCount)->toBeLessThan(500);
    });

    it('SKILL.md contains the anti-pattern directive forbidding inference from sibling plugins', function (): void {
        expect($this->skillContent)->toContain('Do not inspect existing plugins in this project');
    });

    it('SKILL.md contains the LSP verification gate directive', function (): void {
        expect($this->skillContent)->toContain('marko-lsp')
            ->and($this->skillContent)->toContain('find_plugins_targeting');
    });

    it(
        'SKILL.md correctly distinguishes Before vs After plugin types per architecture.md (no around plugins; use Preference for total replacement)',
        function (): void {
            expect($this->skillContent)->toContain('#[Before]')
                ->and($this->skillContent)->toContain('#[After]')
                ->and($this->skillContent)->not->toContain('Around')
                ->and($this->skillContent)->toContain('Preference');
        },
    );

    it(
        'SKILL.md instructs the agent to copy templates from assets/ rather than inlining file content',
        function (): void {
            expect($this->skillContent)->toContain('assets/')
                ->and($this->skillContent)->toContain('PluginClass.php.tmpl')
                ->and($this->skillContent)->toContain('PluginTest.php.tmpl');
        },
    );

    it(
        'PluginClass.php.tmpl uses #[Plugin] attribute on the class and #[Before] or #[After] on methods, with sortOrder argument shown',
        function (): void {
            $tmplPath = $this->skillRoot . '/assets/PluginClass.php.tmpl';
            $content = file_exists($tmplPath) ? file_get_contents($tmplPath) : '';

            expect(file_exists($tmplPath))->toBeTrue()
                ->and($content)->toContain('#[Plugin')
                ->and($content)->toContain('#[Before')
                ->and($content)->toContain('#[After')
                ->and($content)->toContain('sortOrder');
        },
    );

    it('PluginClass.php.tmpl includes strict_types=1 declaration', function (): void {
        $tmplPath = $this->skillRoot . '/assets/PluginClass.php.tmpl';
        $content = file_exists($tmplPath) ? file_get_contents($tmplPath) : '';

        expect(file_exists($tmplPath))->toBeTrue()
            ->and($content)->toContain('declare(strict_types=1)');
    });

    it(
        'the original SKILL.md at packages/devai/resources/ai/skills/marko-create-plugin/ is deleted',
        function (): void {
            $originalPath = dirname(__DIR__, 4) . '/devai/resources/ai/skills/marko-create-plugin/SKILL.md';

            expect(file_exists($originalPath))->toBeFalse();
        },
    );
});
