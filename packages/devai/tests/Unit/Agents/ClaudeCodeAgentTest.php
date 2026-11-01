<?php

declare(strict_types=1);

use Marko\DevAi\Agents\ClaudeCodeAgent;
use Marko\DevAi\Contract\AgentInterface;
use Marko\DevAi\Exceptions\DevAiInstallException;
use Marko\DevAi\Installation\EnsureResult;
use Marko\DevAi\Installation\IntelephenseEnsurerInterface;
use Marko\DevAi\Process\CommandRunnerInterface;

// ---------------------------------------------------------------------------
// Basic identity
// ---------------------------------------------------------------------------

it('reports name as claude-code', function (): void {
    expect((new ClaudeCodeAgent(devaiRunner()))->name())->toBe('claude-code');
});

it('detects installation when claude binary is on PATH', function (): void {
    expect((new ClaudeCodeAgent(devaiRunner(onPath: true)))->isInstalled())->toBeTrue()
        ->and((new ClaudeCodeAgent(devaiRunner(onPath: false)))->isInstalled())->toBeFalse();
});

it('implements AgentInterface', function (): void {
    expect(new ClaudeCodeAgent(devaiRunner()))->toBeInstanceOf(AgentInterface::class);
});

// ---------------------------------------------------------------------------
// install() — guidelines
// ---------------------------------------------------------------------------

describe('guidelines', function (): void {
    beforeEach(function (): void {
        $this->root = devaiTempDir();
        $this->agent = new ClaudeCodeAgent(devaiRunner());
    });

    afterEach(function (): void {
        devaiRemoveDir($this->root);
    });

    it('writes AGENTS.md with the aggregated package guidelines content', function (): void {
        $this->agent->install(devaiContext('# Project Guidelines'), $this->root);

        expect(file_exists($this->root . '/AGENTS.md'))->toBeTrue()
            ->and(file_get_contents($this->root . '/AGENTS.md'))->toContain('# Project Guidelines');
    });

    it('creates AGENTS.md via the writer when it does not exist', function (): void {
        $this->agent->install(devaiContext('# Project Guidelines'), $this->root);

        $agentsMd = (string) file_get_contents($this->root . '/AGENTS.md');
        expect($agentsMd)->toContain('# Project Guidelines')
            ->and($agentsMd)->toContain('<!-- BEGIN marko:devai -->');
    });

    it('preserves user content outside the markers in an existing AGENTS.md', function (): void {
        $beginMarker = '<!-- BEGIN marko:devai -->';
        $endMarker = '<!-- END marko:devai -->';
        $existing = "# My Custom Header\n\n$beginMarker\nOld generated content\n$endMarker\n\n## My Footer\n";
        file_put_contents($this->root . '/AGENTS.md', $existing);

        $this->agent->install(devaiContext('# New Guidelines'), $this->root);

        $agentsMd = (string) file_get_contents($this->root . '/AGENTS.md');
        expect($agentsMd)->toContain('# My Custom Header')
            ->and($agentsMd)->toContain('## My Footer')
            ->and($agentsMd)->toContain('# New Guidelines');
    });

    it('creates CLAUDE.md with the AGENTS.md import inside the marker region', function (): void {
        $this->agent->install(devaiContext('body'), $this->root);

        $claudeMd = (string) file_get_contents($this->root . '/CLAUDE.md');
        $beginPos = strpos($claudeMd, '<!-- BEGIN marko:devai -->');
        $endPos = strpos($claudeMd, '<!-- END marko:devai -->');
        $importPos = strpos($claudeMd, '@AGENTS.md');

        expect($beginPos)->not->toBeFalse()
            ->and($endPos)->not->toBeFalse()
            ->and($importPos)->not->toBeFalse()
            ->and($importPos > $beginPos)->toBeTrue()
            ->and($importPos < $endPos)->toBeTrue();
    });

    it('wraps the Claude tooling block inside the marker region', function (): void {
        $this->agent->install(devaiContext('body'), $this->root);

        $claudeMd = (string) file_get_contents($this->root . '/CLAUDE.md');
        $beginPos = strpos($claudeMd, '<!-- BEGIN marko:devai -->');
        $endPos = strpos($claudeMd, '<!-- END marko:devai -->');
        $toolingPos = strpos($claudeMd, 'Marko AI tooling');

        expect($beginPos)->not->toBeFalse()
            ->and($endPos)->not->toBeFalse()
            ->and($toolingPos)->not->toBeFalse()
            ->and($toolingPos > $beginPos)->toBeTrue()
            ->and($toolingPos < $endPos)->toBeTrue();
    });

    it('leaves a marker-stripped CLAUDE.md untouched', function (): void {
        $noMarkerContent = "# My CLAUDE.md\n\nSome user content without any markers.\n";
        file_put_contents($this->root . '/CLAUDE.md', $noMarkerContent);

        $this->agent->install(devaiContext('body'), $this->root);

        $claudeMd = (string) file_get_contents($this->root . '/CLAUDE.md');
        expect($claudeMd)->toBe($noMarkerContent);
    });

    it('does not modify MCP or settings behavior', function (): void {
        $this->agent->install(devaiContext('body'), $this->root);

        $settingsPath = $this->root . '/.claude/settings.json';
        expect(file_exists($settingsPath))->toBeTrue();

        $data = json_decode((string) file_get_contents($settingsPath), true);
        expect($data)->toHaveKey('extraKnownMarketplaces')
            ->and($data['extraKnownMarketplaces'])->toHaveKey('marko')
            ->and($data['enabledPlugins']['marko-skills@marko'])->toBeTrue()
            ->and($data['enabledPlugins']['marko-lsp@marko'])->toBeTrue()
            ->and($data['enabledPlugins']['marko-mcp@marko'])->toBeTrue();
    });

    it('writes CLAUDE.md including the @AGENTS.md import directive', function (): void {
        $this->agent->install(devaiContext('body'), $this->root);

        expect((string) file_get_contents($this->root . '/CLAUDE.md'))->toContain('@AGENTS.md');
    });

    it(
        'writes CLAUDE.md including the verbatim authority directive about skills as canonical spec',
        function (): void {
            $this->agent->install(devaiContext('body'), $this->root);

            $claudeMd = (string) file_get_contents($this->root . '/CLAUDE.md');
            expect($claudeMd)->toContain('skill is the canonical specification')
                ->and($claudeMd)->toContain('marko-skills:create-module');
        },
    );

    it('writes CLAUDE.md including the LSP verification gate directive', function (): void {
        $this->agent->install(devaiContext('body'), $this->root);

        $claudeMd = (string) file_get_contents($this->root . '/CLAUDE.md');
        expect($claudeMd)->toContain('LSP diagnostics')
            ->and($claudeMd)->toContain('verification gate');
    });

    it('writes CLAUDE.md stating no reindex/build step is needed', function (): void {
        $this->agent->install(devaiContext('body'), $this->root);

        $claudeMd = (string) file_get_contents($this->root . '/CLAUDE.md');
        expect($claudeMd)->toContain('There is no build, compile, or reindex step')
            ->and($claudeMd)->toContain('discovered live from your source files')
            ->and($claudeMd)->toContain('Magento reflex Marko does not have');
    });

    it('writes CLAUDE.md noting the plugin-namespaced skill invocation', function (): void {
        $this->agent->install(devaiContext('body'), $this->root);

        $claudeMd = (string) file_get_contents($this->root . '/CLAUDE.md');
        expect($claudeMd)->toContain('marko-skills@marko')
            ->and($claudeMd)->toContain('marko-lsp@marko')
            ->and($claudeMd)->toContain('marko-mcp@marko');
    });

    it(
        'writes CLAUDE.md instructing the agent to call search_docs first for documentation lookups',
        function (): void {
            $this->agent->install(devaiContext('body'), $this->root);

            $claudeMd = (string) file_get_contents($this->root . '/CLAUDE.md');
            expect($claudeMd)->toContain('search_docs')
                ->and($claudeMd)->toContain('Do NOT infer answers from `vendor/marko/*`')
                ->and($claudeMd)->toContain('list_modules')
                ->and($claudeMd)->toContain('validate_module')
                ->and($claudeMd)->toContain('find_event_observers')
                ->and($claudeMd)->toContain('find_plugins_targeting')
                ->and($claudeMd)->toContain('resolve_preference')
                ->and($claudeMd)->toContain('check_config_key');
        },
    );

    it(
        'writes CLAUDE.md instructing the agent to trust its own writes rather than using introspection tools to confirm scaffolding',
        function (): void {
            $this->agent->install(devaiContext('body'), $this->root);

            $claudeMd = (string) file_get_contents($this->root . '/CLAUDE.md');
            expect($claudeMd)->toContain('introspection tools are for discovering pre-existing code');
        },
    );
});

// ---------------------------------------------------------------------------
// install() — settings
// ---------------------------------------------------------------------------

describe('settings', function (): void {
    beforeEach(function (): void {
        $this->root = devaiTempDir();
        $this->agent = new ClaudeCodeAgent(devaiRunner());
    });

    afterEach(function (): void {
        devaiRemoveDir($this->root);
    });

    it('writes .claude/settings.json with extraKnownMarketplaces.marko entry', function (): void {
        $this->agent->install(devaiContext(), $this->root);

        $path = $this->root . '/.claude/settings.json';
        expect(file_exists($path))->toBeTrue();

        $data = json_decode((string) file_get_contents($path), true);
        expect($data)->toHaveKey('extraKnownMarketplaces')
            ->and($data['extraKnownMarketplaces'])->toHaveKey('marko');
    });

    it('writes enabledPlugins listing marko-skills, marko-lsp, marko-mcp all true', function (): void {
        $this->agent->install(devaiContext(), $this->root);

        $data = json_decode((string) file_get_contents($this->root . '/.claude/settings.json'), true);
        expect($data['enabledPlugins']['marko-skills@marko'])->toBeTrue()
            ->and($data['enabledPlugins']['marko-lsp@marko'])->toBeTrue()
            ->and($data['enabledPlugins']['marko-mcp@marko'])->toBeTrue();
    });

    it('merges into existing settings without clobbering unrelated user keys', function (): void {
        mkdir($this->root . '/.claude', 0755, true);
        file_put_contents(
            $this->root . '/.claude/settings.json',
            json_encode(['theme' => 'dark', 'someUserKey' => 42]),
        );

        $this->agent->install(devaiContext(), $this->root);

        $data = json_decode((string) file_get_contents($this->root . '/.claude/settings.json'), true);
        expect($data['theme'])->toBe('dark')
            ->and($data['someUserKey'])->toBe(42)
            ->and($data['extraKnownMarketplaces'])->toHaveKey('marko');
    });

    it('throws a loud exception when marko is already registered and force is false', function (): void {
        mkdir($this->root . '/.claude', 0755, true);
        file_put_contents(
            $this->root . '/.claude/settings.json',
            json_encode(
                ['extraKnownMarketplaces' => ['marko' => ['source' => ['source' => 'github', 'repo' => 'marko-php/marko']]]],
            ),
        );

        expect(fn () => $this->agent->install(devaiContext(force: false), $this->root))
            ->toThrow(DevAiInstallException::class);
    });

    it('overwrites marko keys without throwing when force is true', function (): void {
        mkdir($this->root . '/.claude', 0755, true);
        file_put_contents(
            $this->root . '/.claude/settings.json',
            json_encode(['extraKnownMarketplaces' => ['marko' => ['source' => ['source' => 'old']]]]),
        );

        $this->agent->install(devaiContext(force: true), $this->root);

        $data = json_decode((string) file_get_contents($this->root . '/.claude/settings.json'), true);
        expect($data['extraKnownMarketplaces']['marko']['source']['source'])->toBe('github');
    });

    it('preserves unrelated user keys under force (only marko-prefixed keys touched)', function (): void {
        mkdir($this->root . '/.claude', 0755, true);
        file_put_contents(
            $this->root . '/.claude/settings.json',
            json_encode([
                'theme' => 'light',
                'extraKnownMarketplaces' => ['marko' => ['old' => true]],
                'enabledPlugins' => ['marko-skills@marko' => false, 'other-plugin@other' => true],
            ]),
        );

        $this->agent->install(devaiContext(force: true), $this->root);

        $data = json_decode((string) file_get_contents($this->root . '/.claude/settings.json'), true);
        expect($data['theme'])->toBe('light')
            ->and($data['enabledPlugins']['other-plugin@other'])->toBeTrue()
            ->and($data['enabledPlugins']['marko-skills@marko'])->toBeTrue();
    });
});

// ---------------------------------------------------------------------------
// Monorepo vs external-project detection
// ---------------------------------------------------------------------------

describe('monorepo detection', function (): void {
    it('chooses the local marketplace source when inside the monorepo', function (): void {
        $root = devaiTempDir();
        mkdir($root . '/packages/claude-plugins', 0755, true);

        try {
            (new ClaudeCodeAgent(devaiRunner()))->install(devaiContext(), $root);

            $data = json_decode((string) file_get_contents($root . '/.claude/settings.json'), true);
            expect($data['extraKnownMarketplaces']['marko']['source']['source'])->toBe('local');
        } finally {
            devaiRemoveDir($root);
        }
    });

    it('chooses the github source shape for an external project', function (): void {
        $root = devaiTempDir();

        try {
            (new ClaudeCodeAgent(devaiRunner()))->install(devaiContext(), $root);

            $source = json_decode(
                (string) file_get_contents($root . '/.claude/settings.json'),
                true,
            )['extraKnownMarketplaces']['marko']['source'];
            expect($source['source'])->toBe('github')
                ->and($source['repo'])->toBe('marko-php/marko');
        } finally {
            devaiRemoveDir($root);
        }
    });

    it('pins the github marketplace source to a ref instead of tracking the default branch', function (): void {
        $root = devaiTempDir();

        try {
            (new ClaudeCodeAgent(devaiRunner()))->install(devaiContext(), $root);
            $defaultRef = json_decode(
                (string) file_get_contents($root . '/.claude/settings.json'),
                true,
            )['extraKnownMarketplaces']['marko']['source']['ref'];

            (new ClaudeCodeAgent(devaiRunner(), null, 'deadbeef'))->install(devaiContext(force: true), $root);
            $customRef = json_decode(
                (string) file_get_contents($root . '/.claude/settings.json'),
                true,
            )['extraKnownMarketplaces']['marko']['source']['ref'];

            expect($defaultRef)->toBe(ClaudeCodeAgent::DEFAULT_MARKETPLACE_REF)
                ->and($customRef)->toBe('deadbeef');
        } finally {
            devaiRemoveDir($root);
        }
    });
});

// ---------------------------------------------------------------------------
// Legacy artifact cleanup
// ---------------------------------------------------------------------------

describe('legacy artifact cleanup', function (): void {
    it('removes any pre-existing .claude/plugins/marko/.lsp.json (idempotent)', function (): void {
        $root = devaiTempDir();

        try {
            $legacyDir = $root . '/.claude/plugins/marko';
            mkdir($legacyDir, 0755, true);
            file_put_contents($legacyDir . '/.lsp.json', '{"old":"stuff"}');

            (new ClaudeCodeAgent(devaiRunner()))->install(devaiContext(), $root);

            expect(file_exists($legacyDir . '/.lsp.json'))->toBeFalse();
        } finally {
            devaiRemoveDir($root);
        }
    });

    it('removes a previously-registered marko-mcp server via claude mcp remove', function (): void {
        $root = devaiTempDir();

        try {
            $runner = devaiRunner(listOutput: "marko-mcp: stdio - php marko mcp:serve\n");
            (new ClaudeCodeAgent($runner))->install(devaiContext(), $root);

            $listCall = null;
            $removeCall = null;
            foreach ($runner->calls as $call) {
                if ($call['command'] === 'claude' && ($call['args'][0] ?? '') === 'mcp' && ($call['args'][1] ?? '') === 'list') {
                    $listCall = $call;
                }
                if ($call['command'] === 'claude' && ($call['args'][0] ?? '') === 'mcp' && ($call['args'][1] ?? '') === 'remove') {
                    $removeCall = $call;
                }
            }
            expect($listCall)->not->toBeNull()
                ->and($removeCall)->not->toBeNull()
                ->and($removeCall['args'])->toContain('marko-mcp');
        } finally {
            devaiRemoveDir($root);
        }
    });

    it('does not call claude mcp remove when marko-mcp is absent (idempotent)', function (): void {
        $root = devaiTempDir();

        try {
            $runner = devaiRunner(listOutput: '');
            (new ClaudeCodeAgent($runner))->install(devaiContext(), $root);

            $removeCall = null;
            foreach ($runner->calls as $call) {
                if ($call['command'] === 'claude' && ($call['args'][0] ?? '') === 'mcp' && ($call['args'][1] ?? '') === 'remove') {
                    $removeCall = $call;
                }
            }
            expect($removeCall)->toBeNull();
        } finally {
            devaiRemoveDir($root);
        }
    });
});

// ---------------------------------------------------------------------------
// IntelephenseEnsurer wiring — --skip-lsp-deps
// ---------------------------------------------------------------------------

describe('lsp deps', function (): void {
    beforeEach(function (): void {
        $this->root = devaiTempDir();
    });

    afterEach(function (): void {
        devaiRemoveDir($this->root);
    });

    it('invokes IntelephenseEnsurer with skip=false during install', function (): void {
        $log = [];
        $ensurer = new class ($log) implements IntelephenseEnsurerInterface
        {
            public function __construct(
                /** @noinspection PhpPropertyOnlyWrittenInspection - Reference property modifies external variable */
                private array &$log,
            ) {}

            public function ensure(bool $skip = false): EnsureResult
            {
                $this->log[] = $skip;

                return EnsureResult::alreadyInstalled();
            }
        };

        (new ClaudeCodeAgent(devaiRunner(), $ensurer))->install(devaiContext(skipLspDeps: false), $this->root);

        expect($log)->toBe([false]);
    });

    it('passes --skip-lsp-deps through to IntelephenseEnsurer when set', function (): void {
        $log = [];
        $ensurer = new class ($log) implements IntelephenseEnsurerInterface
        {
            public function __construct(
                /** @noinspection PhpPropertyOnlyWrittenInspection - Reference property modifies external variable */
                private array &$log,
            ) {}

            public function ensure(bool $skip = false): EnsureResult
            {
                $this->log[] = $skip;

                return EnsureResult::skipped();
            }
        };

        (new ClaudeCodeAgent(devaiRunner(), $ensurer))->install(devaiContext(skipLspDeps: true), $this->root);

        expect($log)->toBe([true]);
    });

    it('completes install when no IntelephenseEnsurer is wired', function (): void {
        (new ClaudeCodeAgent(devaiRunner()))->install(devaiContext(), $this->root);

        expect(file_exists($this->root . '/.claude/settings.json'))->toBeTrue();
    });
});

// ---------------------------------------------------------------------------
// install() — explicit plugin install + verification
// ---------------------------------------------------------------------------

/**
 * A CommandRunner double for the `claude plugin` flow. `claude plugin list --json`
 * reports $listed (plugin id => enabled). `claude plugin install` overwrites
 * .claude/settings.json the way the real CLI does (2-space indent, its own key
 * order) so tests can prove devai rewrites it afterwards.
 *
 * @param array<string, bool> $listed
 */
function claudePluginRunner(
    string $projectRoot,
    array $listed = ['marko-skills@marko' => true, 'marko-lsp@marko' => true, 'marko-mcp@marko' => true],
    bool $claudeOnPath = true,
    int $listExitCode = 0,
): CommandRunnerInterface {
    return new class ($projectRoot, $listed, $claudeOnPath, $listExitCode) implements CommandRunnerInterface
    {
        /** @var list<array{command: string, args: list<string>}> */
        public array $calls = [];

        /** @var list<string> settings.json content seen at each `plugin install` call */
        public array $settingsAtInstall = [];

        /**
         * @param array<string, bool> $listed
         */
        public function __construct(
            private string $projectRoot,
            private array $listed,
            private bool $claudeOnPath,
            private int $listExitCode,
        ) {}

        public function run(
            string $command,
            array $args = [],
        ): array {
            $this->calls[] = ['command' => $command, 'args' => $args];
            if ($command === 'claude' && ($args[0] ?? '') === 'plugin' && ($args[1] ?? '') === 'install') {
                $path = $this->projectRoot . '/.claude/settings.json';
                $this->settingsAtInstall[] = is_file($path) ? (string) file_get_contents($path) : '';
                file_put_contents(
                    $path,
                    "{\n  \"enabledPlugins\": {\n    \"" . ($args[2] ?? '') . "\": true\n  }\n}\n",
                );

                return ['exitCode' => 0, 'stdout' => '', 'stderr' => ''];
            }
            if ($command === 'claude' && ($args[0] ?? '') === 'plugin' && ($args[1] ?? '') === 'list') {
                $plugins = [];
                foreach ($this->listed as $id => $enabled) {
                    $plugins[] = ['id' => $id, 'scope' => 'project', 'enabled' => $enabled];
                }

                return [
                    'exitCode' => $this->listExitCode,
                    'stdout' => $this->listExitCode === 0 ? (string) json_encode($plugins) : '',
                    'stderr' => $this->listExitCode === 0 ? '' : 'plugin list exploded',
                ];
            }

            return ['exitCode' => 0, 'stdout' => '', 'stderr' => ''];
        }

        public function isOnPath(string $binary): bool
        {
            return $binary === 'claude' && $this->claudeOnPath;
        }

        /**
         * @return list<list<string>>
         */
        public function pluginCalls(string $subcommand): array
        {
            $matches = [];
            foreach ($this->calls as $call) {
                if ($call['command'] === 'claude' && ($call['args'][0] ?? '') === 'plugin' && ($call['args'][1] ?? '') === $subcommand) {
                    $matches[] = $call['args'];
                }
            }

            return $matches;
        }
    };
}

describe('plugin installation', function (): void {
    beforeEach(function (): void {
        $this->root = devaiTempDir();
    });

    afterEach(function (): void {
        devaiRemoveDir($this->root);
    });

    it('installs all three marko plugins at project scope for an external project', function (): void {
        $runner = claudePluginRunner($this->root);

        (new ClaudeCodeAgent($runner))->install(devaiContext(), $this->root);

        expect($runner->pluginCalls('install'))->toBe([
            ['plugin', 'install', 'marko-skills@marko', '--scope', 'project'],
            ['plugin', 'install', 'marko-lsp@marko', '--scope', 'project'],
            ['plugin', 'install', 'marko-mcp@marko', '--scope', 'project'],
        ]);
    });

    it('writes the marketplace into settings before installing so the CLI can resolve it', function (): void {
        $runner = claudePluginRunner($this->root);

        (new ClaudeCodeAgent($runner))->install(devaiContext(), $this->root);

        $first = json_decode($runner->settingsAtInstall[0], true);
        expect($first['extraKnownMarketplaces']['marko']['source']['source'])->toBe('github');
    });

    it('rewrites settings.json after the CLI install so the output stays stable', function (): void {
        $runner = claudePluginRunner($this->root);

        (new ClaudeCodeAgent($runner))->install(devaiContext(), $this->root);

        $content = (string) file_get_contents($this->root . '/.claude/settings.json');
        $data = json_decode($content, true);
        expect($content)->toBe(json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES))
            ->and($data['extraKnownMarketplaces']['marko']['source']['repo'])->toBe('marko-php/marko')
            ->and($data['enabledPlugins'])->toBe([
                'marko-skills@marko' => true,
                'marko-lsp@marko' => true,
                'marko-mcp@marko' => true,
            ]);
    });

    it('verifies the install with claude plugin list --json after installing', function (): void {
        $runner = claudePluginRunner($this->root);

        (new ClaudeCodeAgent($runner))->install(devaiContext(), $this->root);

        $pluginCalls = array_values(array_filter(
            $runner->calls,
            fn (array $call): bool => $call['command'] === 'claude' && ($call['args'][0] ?? '') === 'plugin',
        ));
        expect(end($pluginCalls)['args'])->toBe(['plugin', 'list', '--json']);
    });

    it('throws a loud exception naming the missing plugin when verification fails', function (): void {
        $runner = claudePluginRunner($this->root, ['marko-skills@marko' => true, 'marko-lsp@marko' => true]);

        try {
            (new ClaudeCodeAgent($runner))->install(devaiContext(), $this->root);
            $this->fail('Expected DevAiInstallException');
        } catch (DevAiInstallException $e) {
            expect($e->getMessage())->toContain('marko-mcp@marko')
                ->and($e->getMessage())->not->toContain('marko-lsp@marko')
                ->and($e->getSuggestion())->toContain('claude plugin install marko-skills@marko --scope project')
                ->and($e->getSuggestion())->toContain('claude plugin install marko-lsp@marko --scope project')
                ->and($e->getSuggestion())->toContain('claude plugin install marko-mcp@marko --scope project')
                ->and($e->getSuggestion())->toContain('restart Claude Code');
        }
    });

    it('treats a plugin that is listed but not enabled for this project as missing', function (): void {
        $runner = claudePluginRunner($this->root, [
            'marko-skills@marko' => false,
            'marko-lsp@marko' => true,
            'marko-mcp@marko' => true,
        ]);

        expect(fn () => (new ClaudeCodeAgent($runner))->install(devaiContext(), $this->root))
            ->toThrow(DevAiInstallException::class, 'marko-skills@marko');
    });

    it('throws when claude plugin list itself fails', function (): void {
        $runner = claudePluginRunner($this->root, listExitCode: 1);

        try {
            (new ClaudeCodeAgent($runner))->install(devaiContext(), $this->root);
            $this->fail('Expected DevAiInstallException');
        } catch (DevAiInstallException $e) {
            expect($e->getContext())->toContain('plugin list exploded');
        }
    });

    it('warns instead of throwing when the claude CLI is not on PATH', function (): void {
        $runner = claudePluginRunner($this->root, [], claudeOnPath: false);
        $agent = new ClaudeCodeAgent($runner);

        $agent->install(devaiContext(), $this->root);

        $notices = $agent->takeNotices();
        expect($runner->pluginCalls('install'))->toBe([])
            ->and($runner->pluginCalls('list'))->toBe([])
            ->and($notices)->toHaveCount(1)
            ->and($notices[0])->toContain('claude CLI not found on PATH')
            ->and($notices[0])->toContain('claude plugin install marko-mcp@marko --scope project')
            ->and($agent->takeNotices())->toBe([]);
    });

    it('leaves the monorepo local marketplace to the folder-trust prompt', function (): void {
        mkdir($this->root . '/packages/claude-plugins', 0755, true);
        $runner = claudePluginRunner($this->root, []);
        $agent = new ClaudeCodeAgent($runner);

        $agent->install(devaiContext(), $this->root);

        expect($runner->pluginCalls('install'))->toBe([])
            ->and($runner->pluginCalls('list'))->toBe([])
            ->and($agent->takeNotices())->toBe([]);
    });
});
