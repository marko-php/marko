<?php

declare(strict_types=1);

describe('composer.json', function (): void {
    beforeEach(function (): void {
        $this->composerPath = dirname(__DIR__, 2) . '/composer.json';
        $this->composer = json_decode(file_get_contents($this->composerPath), true);
    });

    it('declares package name marko/claude-plugins', function (): void {
        expect($this->composer['name'])->toBe('marko/claude-plugins');
    });

    it('type field is "library" or omitted (defaulting to library)', function (): void {
        $type = $this->composer['type'] ?? 'library';
        expect($type)->toBe('library');
    });

    it('does not set extra.marko.module', function (): void {
        $module = $this->composer['extra']['marko']['module'] ?? null;
        expect($module)->toBeNull();
    });

    it('does not include a version field', function (): void {
        expect(array_key_exists('version', $this->composer))->toBeFalse();
    });

    it('does not declare a Marko\\ClaudePlugins\\ autoload namespace (no PHP code shipped)', function (): void {
        $autoload = $this->composer['autoload'] ?? [];
        $psr4 = $autoload['psr-4'] ?? [];
        expect(array_key_exists('Marko\\ClaudePlugins\\', $psr4))->toBeFalse();
    });

    it('module discovery in marko/core does not register marko/claude-plugins as a Marko module', function (): void {
        // Module discovery in marko/core reads extra.marko.module from composer.json.
        // Without that flag set to true the package is invisible to the loader.
        $isModule = ($this->composer['extra']['marko']['module'] ?? false) === true;
        expect($isModule)->toBeFalse();
    });

    it('no module.php file exists at the package root', function (): void {
        $modulePath = dirname(__DIR__, 2) . '/module.php';
        expect(file_exists($modulePath))->toBeFalse();
    });
});

describe('marketplace.json', function (): void {
    beforeEach(function (): void {
        // Marketplace lives at the monorepo root, not inside packages/claude-plugins/
        $this->repoRoot = dirname(__DIR__, 4);
        $this->marketplacePath = $this->repoRoot . '/.claude-plugin/marketplace.json';
        $this->marketplace = file_exists($this->marketplacePath)
            ? json_decode(file_get_contents($this->marketplacePath), true)
            : null;
    });

    it('marketplace.json lives at the monorepo repo root at .claude-plugin/marketplace.json (NOT inside packages/claude-plugins/)', function (): void {
        $insidePackage = dirname(__DIR__, 2) . '/.claude-plugin/marketplace.json';

        expect(file_exists($this->marketplacePath))->toBeTrue()
            ->and(file_exists($insidePackage))->toBeFalse();
    });

    it('marketplace.json references the official schema URL via $schema', function (): void {
        expect($this->marketplace)->toHaveKey('$schema')
            ->and($this->marketplace['$schema'])->toBe('https://json.schemastore.org/claude-code-plugin-marketplace.json');
    });

    it('architecture.md AI Development Tooling table includes a row for marko/claude-plugins', function (): void {
        $archPath = dirname(__DIR__, 4) . '/.claude/architecture.md';
        $contents = file_get_contents($archPath);

        expect(str_contains($contents, 'marko/claude-plugins'))->toBeTrue()
            ->and(str_contains($contents, 'AI Development Tooling'))->toBeTrue();
    });

    it('marketplace.json each plugin entry includes name, description, author, category fields per Task 001\'s required-fields finding', function (): void {
        foreach ($this->marketplace['plugins'] as $plugin) {
            expect($plugin)->toHaveKey('name')
                ->and($plugin)->toHaveKey('source')
                ->and($plugin)->toHaveKey('description')
                ->and($plugin)->toHaveKey('author')
                ->and($plugin['author'])->toHaveKey('name')
                ->and($plugin)->toHaveKey('category');
        }
    });

    it('marketplace.json plugins array lists three entries (marko-skills, marko-lsp, marko-mcp) with bare-name source values resolved relative to pluginRoot', function (): void {
        $plugins = $this->marketplace['plugins'];
        $names = array_column($plugins, 'name');
        $sources = array_column($plugins, 'source');

        expect(count($plugins))->toBe(3)
            ->and($names)->toContain('marko-skills')
            ->and($names)->toContain('marko-lsp')
            ->and($names)->toContain('marko-mcp');

        // Sources must be bare names (no path separators), resolved under pluginRoot
        foreach ($sources as $source) {
            expect(str_contains($source, '/'))->toBeFalse()
                ->and(str_contains($source, '\\'))->toBeFalse();
        }
    });

    it('marketplace.json sets metadata.pluginRoot to "./packages/claude-plugins/plugins"', function (): void {
        expect($this->marketplace)->toHaveKey('metadata')
            ->and($this->marketplace['metadata'])->toHaveKey('pluginRoot')
            ->and($this->marketplace['metadata']['pluginRoot'])->toBe('./packages/claude-plugins/plugins');
    });

    it('marketplace.json declares marketplace name "marko" and owner field per Task 001\'s required-fields finding', function (): void {
        expect($this->marketplace['name'])->toBe('marko')
            ->and($this->marketplace['owner'])->toHaveKey('name')
            ->and($this->marketplace['owner']['name'])->toBeString()
            ->and(strlen($this->marketplace['owner']['name']))->toBeGreaterThan(0);
    });

    it('marketplace.json conforms to the schema captured in Task 001\'s schemas/marketplace.json artifact', function (): void {
        // Required fields per Task 001 finding F7: name (string), owner (object with name), plugins (array)
        expect($this->marketplace)->toBeArray()
            ->and($this->marketplace)->toHaveKey('name')
            ->and($this->marketplace['name'])->toBeString()
            ->and($this->marketplace)->toHaveKey('owner')
            ->and($this->marketplace['owner'])->toBeArray()
            ->and($this->marketplace['owner'])->toHaveKey('name')
            ->and($this->marketplace)->toHaveKey('plugins')
            ->and($this->marketplace['plugins'])->toBeArray();
    });
});
