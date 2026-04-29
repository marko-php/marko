<?php

declare(strict_types=1);

use Marko\DevAi\Agents\ClaudeCodeAgent;
use Marko\DevAi\Contract\SupportsGuidelines;
use Marko\DevAi\Contract\SupportsLsp;
use Marko\DevAi\Contract\SupportsMcp;
use Marko\DevAi\Contract\SupportsSkills;
use Marko\DevAi\Process\CommandRunnerInterface;
use Marko\DevAi\ValueObject\GuidelinesContent;
use Marko\DevAi\ValueObject\LspRegistration;
use Marko\DevAi\ValueObject\McpRegistration;
use Marko\DevAi\ValueObject\SkillBundle;

beforeEach(function () {
    $this->tempRoot = sys_get_temp_dir() . '/devai-test-' . uniqid();
    mkdir($this->tempRoot, 0755, true);
    $this->runner = new class () implements CommandRunnerInterface
    {
        public array $calls = [];

        public bool $claudeOnPath = true;

        public string $listOutput = '';

        public function run(
            string $cmd,
            array $args = [],
        ): array
        {
            $this->calls[] = [$cmd, $args];
            if ($cmd === 'command') {
                return ['exitCode' => $this->claudeOnPath ? 0 : 1, 'stdout' => $this->claudeOnPath ? '/usr/bin/claude' : '', 'stderr' => ''];
            }
            if ($cmd === 'claude' && ($args[0] ?? '') === 'mcp' && ($args[1] ?? '') === 'list') {
                return ['exitCode' => 0, 'stdout' => $this->listOutput, 'stderr' => ''];
            }

            return ['exitCode' => 0, 'stdout' => '', 'stderr' => ''];
        }

        public function isOnPath(string $binary): bool
        {
            return $this->claudeOnPath;
        }
    };
    $this->agent = new ClaudeCodeAgent($this->runner);
});

afterEach(function () {
    $iter = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($this->tempRoot, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST,
    );
    foreach ($iter as $f) {
        $f->isDir() ? rmdir($f->getPathname()) : unlink($f->getPathname());
    }
    rmdir($this->tempRoot);
});

it('reports name as claude-code', function () {
    expect($this->agent->name())->toBe('claude-code');
});

it('detects installation when claude binary is on PATH', function () {
    expect($this->agent->isInstalled())->toBeTrue();
    $this->runner->claudeOnPath = false;
    expect($this->agent->isInstalled())->toBeFalse();
});

it('writes CLAUDE.md with @AGENTS.md import and Claude-specific additions', function () {
    $content = new GuidelinesContent('# Project Guidelines');
    $this->agent->writeGuidelines($content, $this->tempRoot);

    expect(file_exists($this->tempRoot . '/AGENTS.md'))->toBeTrue()
        ->and(file_get_contents($this->tempRoot . '/AGENTS.md'))->toBe('# Project Guidelines');

    $claudeMd = file_get_contents($this->tempRoot . '/CLAUDE.md');
    expect($claudeMd)->toContain('@AGENTS.md')
        ->and($claudeMd)->toContain('Claude-Specific');
});

it('registers marko-mcp via claude mcp add command', function () {
    $reg = new McpRegistration(
        serverName: 'marko-mcp',
        command: 'marko-mcp',
        args: ['--port', '3000'],
        transport: 'stdio',
    );
    $this->agent->registerMcpServer($reg, $this->tempRoot);

    $addCall = null;
    foreach ($this->runner->calls as $call) {
        if ($call[0] === 'claude' && in_array('add', $call[1], true)) {
            $addCall = $call;
            break;
        }
    }

    expect($addCall)->not->toBeNull()
        ->and($addCall[1])->toContain('mcp')
        ->and($addCall[1])->toContain('add')
        ->and($addCall[1])->toContain('marko-mcp');
});

it('skips registration when the exact server name is already in claude mcp list', function () {
    $this->runner->listOutput = "marko-mcp: stdio - php marko mcp:serve\n";
    $reg = new McpRegistration(serverName: 'marko-mcp', command: 'php', args: ['marko', 'mcp:serve']);
    $this->agent->registerMcpServer($reg, $this->tempRoot);

    $addCall = null;
    foreach ($this->runner->calls as $call) {
        if ($call[0] === 'claude' && in_array('add', $call[1], true)) {
            $addCall = $call;
            break;
        }
    }

    expect($addCall)->toBeNull();
});

it('still registers marko-mcp when a different server with a similar name exists', function () {
    // Regression: substring-only check would false-positive on `marko-mcp-staging`
    // and silently skip the real `marko-mcp` registration.
    $this->runner->listOutput = "marko-mcp-staging: stdio - php marko mcp:serve --staging\n";
    $reg = new McpRegistration(serverName: 'marko-mcp', command: 'php', args: ['marko', 'mcp:serve']);
    $this->agent->registerMcpServer($reg, $this->tempRoot);

    $addCall = null;
    foreach ($this->runner->calls as $call) {
        if ($call[0] === 'claude' && in_array('add', $call[1], true)) {
            $addCall = $call;
            break;
        }
    }

    expect($addCall)->not->toBeNull()
        ->and($addCall[1])->toContain('marko-mcp');
});

it('writes .lsp.json plugin config for marko-lsp', function () {
    $reg = new LspRegistration(
        serverName: 'marko-lsp',
        command: 'marko-lsp',
        args: ['--stdio'],
        fileExtensions: ['php', 'latte'],
    );
    $this->agent->registerLspServer($reg, $this->tempRoot);

    $lspFile = $this->tempRoot . '/.claude/plugins/marko/.lsp.json';
    expect(file_exists($lspFile))->toBeTrue();

    $config = json_decode(file_get_contents($lspFile), true);
    expect($config['name'])->toBe('marko-lsp')
        ->and($config['command'])->toBe('marko-lsp')
        ->and($config['args'])->toBe(['--stdio'])
        ->and($config['fileExtensions'])->toBe(['php', 'latte']);
});

it('distributes skills to .claude/skills directory', function () {
    $bundles = [
        new SkillBundle('marko-skills', [
            'plan-create.md' => '# Plan Create skill',
            'plan-orchestrate.md' => '# Plan Orchestrate skill',
        ]),
    ];
    $this->agent->distributeSkills($bundles, $this->tempRoot);

    expect(file_exists($this->tempRoot . '/.claude/skills/plan-create.md'))->toBeTrue()
        ->and(file_get_contents($this->tempRoot . '/.claude/skills/plan-create.md'))->toBe('# Plan Create skill')
        ->and(file_exists($this->tempRoot . '/.claude/skills/plan-orchestrate.md'))->toBeTrue();
});

it('supports all four capability interfaces Guidelines Mcp Lsp Skills', function () {
    expect($this->agent)->toBeInstanceOf(SupportsGuidelines::class)
        ->and($this->agent)->toBeInstanceOf(SupportsMcp::class)
        ->and($this->agent)->toBeInstanceOf(SupportsLsp::class)
        ->and($this->agent)->toBeInstanceOf(SupportsSkills::class);
});
