<?php

declare(strict_types=1);

describe('marko-mcp plugin', function (): void {
    beforeEach(function (): void {
        $this->pluginRoot = dirname(__DIR__, 2) . '/plugins/marko-mcp';
        $this->pluginJsonPath = $this->pluginRoot . '/.claude-plugin/plugin.json';
    });

    it('plugin.json declares name "marko-mcp" and a description matching the marketplace.json entry', function (): void {
        $manifest = json_decode(file_get_contents($this->pluginJsonPath), true);

        expect(file_exists($this->pluginJsonPath))->toBeTrue()
            ->and($manifest['name'])->toBe('marko-mcp')
            ->and($manifest['description'])->toBe('Marko MCP server (codeindexer, docs-fts) for Claude Code.');
    });

    it('plugin.json includes author with name "Marko Framework"', function (): void {
        $manifest = json_decode(file_get_contents($this->pluginJsonPath), true);

        expect($manifest)->toHaveKey('author')
            ->and($manifest['author'])->toHaveKey('name')
            ->and($manifest['author']['name'])->toBe('Marko Framework');
    });

    it('plugin.json does not include a version field, allowing git-commit-based versioning', function (): void {
        $manifest = json_decode(file_get_contents($this->pluginJsonPath), true);

        expect(array_key_exists('version', $manifest))->toBeFalse();
    });

    it('.mcp.json top-level structure is { "mcpServers": { "marko-mcp": {...} } } per Task 001 finding F2', function (): void {
        $mcpPath = $this->pluginRoot . '/.mcp.json';
        $mcp = json_decode(file_get_contents($mcpPath), true);

        expect(file_exists($mcpPath))->toBeTrue()
            ->and($mcp)->toHaveKey('mcpServers')
            ->and($mcp['mcpServers'])->toBeArray()
            ->and($mcp['mcpServers'])->toHaveKey('marko-mcp')
            ->and($mcp['mcpServers']['marko-mcp'])->toBeArray();
    });

    it('.mcp.json marko-mcp.command is "${CLAUDE_PLUGIN_ROOT}/bin/marko-mcp"', function (): void {
        $mcp = json_decode(file_get_contents($this->pluginRoot . '/.mcp.json'), true);

        expect($mcp['mcpServers']['marko-mcp']['command'])->toBe('${CLAUDE_PLUGIN_ROOT}/bin/marko-mcp');
    });

    it('.mcp.json marko-mcp.args is an empty array (subcommand is hardcoded inside the shim)', function (): void {
        $mcp = json_decode(file_get_contents($this->pluginRoot . '/.mcp.json'), true);

        expect($mcp['mcpServers']['marko-mcp'])->toHaveKey('args')
            ->and($mcp['mcpServers']['marko-mcp']['args'])->toBeArray()
            ->and($mcp['mcpServers']['marko-mcp']['args'])->toHaveCount(0);
    });

    it('bin/marko-mcp shim script exists, has POSIX shebang (#!/bin/sh), is committed with executable bit set', function (): void {
        $shimPath = $this->pluginRoot . '/bin/marko-mcp';
        $contents = file_exists($shimPath) ? file_get_contents($shimPath) : '';

        expect(file_exists($shimPath))->toBeTrue()
            ->and(str_starts_with($contents, '#!/bin/sh'))->toBeTrue()
            ->and(is_executable($shimPath))->toBeTrue();
    });

    it('bin/marko-mcp searches for marko binary in order: ./vendor/bin/marko then marko on PATH', function (): void {
        $contents = file_get_contents($this->pluginRoot . '/bin/marko-mcp');

        // vendor/bin/marko must appear before global marko PATH check
        $vendorPos = strpos($contents, './vendor/bin/marko');
        $pathPos = strpos($contents, 'command -v marko');

        expect($vendorPos)->not->toBeFalse()
            ->and($pathPos)->not->toBeFalse()
            ->and($vendorPos)->toBeLessThan($pathPos);
    });

    it('bin/marko-mcp execs the discovered binary with "mcp:serve" plus any forwarded args', function (): void {
        $contents = file_get_contents($this->pluginRoot . '/bin/marko-mcp');

        expect(str_contains($contents, 'exec ./vendor/bin/marko mcp:serve "$@"'))->toBeTrue()
            ->and(str_contains($contents, 'exec marko mcp:serve "$@"'))->toBeTrue();
    });

    it('bin/marko-mcp prints a loud error to stderr and exits 1 when no marko binary is found, suggesting "composer require marko/devai"', function (): void {
        $contents = file_get_contents($this->pluginRoot . '/bin/marko-mcp');

        expect(str_contains($contents, '>&2'))->toBeTrue()
            ->and(str_contains($contents, 'composer require marko/devai'))->toBeTrue()
            ->and(str_contains($contents, 'exit 1'))->toBeTrue();
    });

    it('README.md explains what the plugin registers, how to install via the marko marketplace, and how to verify with claude mcp list', function (): void {
        $readmePath = $this->pluginRoot . '/README.md';
        $contents = file_exists($readmePath) ? file_get_contents($readmePath) : '';

        expect(file_exists($readmePath))->toBeTrue()
            // What the plugin registers
            ->and(str_contains($contents, 'mcp:serve') || str_contains($contents, 'MCP server'))->toBeTrue()
            // How to install via marketplace
            ->and(str_contains($contents, '/plugin install marko-mcp@marko'))->toBeTrue()
            // How to verify
            ->and(str_contains($contents, 'claude mcp list'))->toBeTrue();
    });
});
