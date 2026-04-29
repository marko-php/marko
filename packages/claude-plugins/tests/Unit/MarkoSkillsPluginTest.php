<?php

declare(strict_types=1);

describe('marko-skills plugin', function (): void {
    beforeEach(function (): void {
        $this->pluginRoot = dirname(__DIR__, 2) . '/plugins/marko-skills';
        $this->pluginJsonPath = $this->pluginRoot . '/.claude-plugin/plugin.json';
    });

    it('plugin.json declares name "marko-skills"', function (): void {
        $manifest = json_decode(file_get_contents($this->pluginJsonPath), true);

        expect(file_exists($this->pluginJsonPath))->toBeTrue()
            ->and($manifest['name'])->toBe('marko-skills');
    });

    it('plugin.json description states the plugin provides scaffolding skills for Marko modules and plugins', function (): void {
        $manifest = json_decode(file_get_contents($this->pluginJsonPath), true);

        expect($manifest)->toHaveKey('description')
            ->and($manifest['description'])->toBe('Marko-specific skills (create-module, create-plugin) for Claude Code.');
    });

    it('plugin.json includes author with name "Marko Framework"', function (): void {
        $manifest = json_decode(file_get_contents($this->pluginJsonPath), true);

        expect($manifest)->toHaveKey('author')
            ->and($manifest['author'])->toHaveKey('name')
            ->and($manifest['author']['name'])->toBe('Marko Framework');
    });

    it('plugin.json does not include a version field', function (): void {
        $manifest = json_decode(file_get_contents($this->pluginJsonPath), true);

        expect($manifest)->not->toHaveKey('version');
    });

    it('skills/ directory exists at the plugin root (not inside .claude-plugin/)', function (): void {
        expect(is_dir($this->pluginRoot . '/skills'))->toBeTrue()
            ->and(is_dir($this->pluginRoot . '/.claude-plugin/skills'))->toBeFalse();
    });

    it('README.md lists the included skills (create-module, create-plugin), how they\'re invoked with the plugin namespace, and how to install via the marko marketplace', function (): void {
        $readmePath = $this->pluginRoot . '/README.md';
        $content = file_exists($readmePath) ? file_get_contents($readmePath) : '';

        expect(file_exists($readmePath))->toBeTrue()
            ->and($content)->toContain('create-module')
            ->and($content)->toContain('create-plugin')
            ->and($content)->toContain('/marko-skills:create-module')
            ->and($content)->toContain('/marko-skills:create-plugin')
            ->and($content)->toContain('/plugin install marko-skills@marko');
    });
});
