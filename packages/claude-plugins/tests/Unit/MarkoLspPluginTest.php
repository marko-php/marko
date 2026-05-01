<?php

declare(strict_types=1);

describe('marko-lsp plugin', function (): void {
    beforeEach(function (): void {
        $this->pluginRoot = dirname(__DIR__, 2) . '/plugins/marko-lsp';
        $this->pluginJsonPath = $this->pluginRoot . '/.claude-plugin/plugin.json';
        $this->lspJsonPath = $this->pluginRoot . '/.lsp.json';
        $this->shimPath = $this->pluginRoot . '/bin/marko-lsp';
        $this->readmePath = $this->pluginRoot . '/README.md';
    });

    it('plugin.json declares name "marko-lsp" and a description matching the marketplace.json entry', function (): void {
        $manifest = json_decode(file_get_contents($this->pluginJsonPath), true);

        expect(file_exists($this->pluginJsonPath))->toBeTrue()
            ->and($manifest['name'])->toBe('marko-lsp')
            ->and($manifest['description'])->toBe('Marko LSP bundle (PHP intelephense) for Claude Code.');
    });

    it('plugin.json includes author with name "Marko Framework"', function (): void {
        $manifest = json_decode(file_get_contents($this->pluginJsonPath), true);

        expect($manifest['author'])->toBeArray()
            ->and($manifest['author']['name'])->toBe('Marko Framework');
    });

    it('plugin.json does not include a version field', function (): void {
        $manifest = json_decode(file_get_contents($this->pluginJsonPath), true);

        expect(array_key_exists('version', $manifest))->toBeFalse();
    });

    it('.lsp.json top-level structure is an object keyed by server name (not wrapped in lspServers per Task 001 F1 verbatim)', function (): void {
        $lsp = json_decode(file_get_contents($this->lspJsonPath), true);

        expect(file_exists($this->lspJsonPath))->toBeTrue()
            ->and(array_key_exists('marko-lsp', $lsp))->toBeTrue()
            ->and(array_key_exists('lspServers', $lsp))->toBeFalse();
    });

    it('.lsp.json marko-lsp.command is "${CLAUDE_PLUGIN_ROOT}/bin/marko-lsp"', function (): void {
        $lsp = json_decode(file_get_contents($this->lspJsonPath), true);

        expect($lsp['marko-lsp']['command'])->toBe('${CLAUDE_PLUGIN_ROOT}/bin/marko-lsp');
    });

    it('.lsp.json marko-lsp.args is an empty array (subcommand hardcoded inside the shim)', function (): void {
        $lsp = json_decode(file_get_contents($this->lspJsonPath), true);

        expect($lsp['marko-lsp']['args'])->toBeArray()
            ->and($lsp['marko-lsp']['args'])->toHaveCount(0);
    });

    it('.lsp.json marko-lsp.extensionToLanguage maps only .php to php (no .latte for v1, per Task 001 F9)', function (): void {
        $lsp = json_decode(file_get_contents($this->lspJsonPath), true);
        $ext = $lsp['marko-lsp']['extensionToLanguage'];

        expect($ext)->toBeArray()
            ->and($ext)->toHaveCount(1)
            ->and(array_key_exists('.php', $ext))->toBeTrue()
            ->and($ext['.php'])->toBe('php')
            ->and(array_key_exists('.latte', $ext))->toBeFalse();
    });

    it('.lsp.json each extensionToLanguage key starts with a leading dot', function (): void {
        $lsp = json_decode(file_get_contents($this->lspJsonPath), true);
        $ext = $lsp['marko-lsp']['extensionToLanguage'];

        foreach (array_keys($ext) as $key) {
            expect(str_starts_with($key, '.'))->toBeTrue();
        }
    });

    it('bin/marko-lsp shim script exists, has POSIX shebang, is committed with executable bit set', function (): void {
        $contents = file_exists($this->shimPath) ? file_get_contents($this->shimPath) : '';

        expect(file_exists($this->shimPath))->toBeTrue()
            ->and(str_starts_with($contents, '#!/bin/sh'))->toBeTrue()
            ->and(is_executable($this->shimPath))->toBeTrue();
    });

    it('bin/marko-lsp searches for marko binary in order: ./vendor/bin/marko then marko on PATH', function (): void {
        $contents = file_get_contents($this->shimPath);

        expect(str_contains($contents, './vendor/bin/marko'))->toBeTrue()
            ->and(str_contains($contents, 'command -v marko'))->toBeTrue();
    });

    it('bin/marko-lsp execs the discovered binary with "lsp:serve" plus any forwarded args', function (): void {
        $contents = file_get_contents($this->shimPath);

        expect(str_contains($contents, 'lsp:serve'))->toBeTrue()
            ->and(str_contains($contents, '"$@"'))->toBeTrue();
    });

    it('bin/marko-lsp prints a loud error to stderr and exits 1 when no marko binary is found', function (): void {
        $contents = file_get_contents($this->shimPath);

        expect(str_contains($contents, '>&2'))->toBeTrue()
            ->and(str_contains($contents, 'exit 1'))->toBeTrue();
    });

    it('README.md explains marko-lsp coexists with php-lsp (intelephense), what marko-lsp adds beyond it, the recommendation to uninstall php-lsp@claude-plugins-official to avoid duplication, and how to verify with claude plugin list', function (): void {
        $contents = file_exists($this->readmePath) ? file_get_contents($this->readmePath) : '';

        expect(file_exists($this->readmePath))->toBeTrue()
            ->and(str_contains($contents, 'intelephense'))->toBeTrue()
            ->and(str_contains($contents, 'php-lsp@claude-plugins-official'))->toBeTrue()
            ->and(str_contains($contents, 'uninstall'))->toBeTrue()
            ->and(str_contains($contents, 'claude plugin list'))->toBeTrue();
    });
});
