<?php

declare(strict_types=1);

use Marko\Core\Attributes\Command;
use Marko\Core\Command\CommandInterface;
use Marko\Core\Command\ConfirmationPrompterInterface;
use Marko\Core\Command\Input;
use Marko\Core\Command\Output;
use Marko\DevAi\Commands\InstallCommand;
use Marko\DevAi\Guidelines\GuidelinesAggregator;
use Marko\DevAi\Installation\AgentRegistry;
use Marko\DevAi\Installation\DocsDriverResolver;
use Marko\DevAi\Installation\InstallationOrchestrator;
use Marko\DevAi\Installation\IntelephenseEnsurer;
use Marko\DevAi\Process\CommandRunnerInterface;
use Marko\DevAi\Rendering\AgentsMdRenderer;
use Marko\DevAi\Skills\SkillsDistributor;
use Marko\Testing\Fake\FakeConfirmationPrompter;

// ---------------------------------------------------------------------------
// Local test helpers
// ---------------------------------------------------------------------------

/**
 * A CommandRunner double that records every call and returns a configurable
 * exit code for `composer require` commands.
 */
function makeInstallCmdRunner(
    bool $composerOnPath = true,
    int $requireExitCode = 0,
    bool $npmOnPath = false,
): CommandRunnerInterface {
    return new class ($composerOnPath, $requireExitCode, $npmOnPath) implements CommandRunnerInterface
    {
        /** @var list<array{string, list<string>}> */
        public array $calls = [];

        public function __construct(
            private readonly bool $composerOnPath,
            private readonly int $requireExitCode,
            private readonly bool $npmOnPath,
        ) {}

        public function run(
            string $command,
            array $args = [],
        ): array {
            $this->calls[] = [$command, $args];

            if (
                $command === 'composer'
                && ($args[0] ?? '') === 'require'
            ) {
                return ['exitCode' => $this->requireExitCode, 'stdout' => '', 'stderr' => 'install failed'];
            }

            return ['exitCode' => 0, 'stdout' => '', 'stderr' => ''];
        }

        public function isOnPath(
            string $binary,
        ): bool {
            return match ($binary) {
                'composer' => $this->composerOnPath,
                'npm' => $this->npmOnPath,
                default => false,
            };
        }
    };
}

/**
 * Create a minimal InstallationOrchestrator stub for command-level tests.
 * Returns status=installed without running agent installs or real file system writes,
 * but it does create .marko/devai.json (orchestrator requires this).
 */
function makeInstallCmdOrchestrator(
    string $tempRoot,
    ?CommandRunnerInterface $runner = null,
): InstallationOrchestrator {
    $runner ??= devaiRunner();

    return new InstallationOrchestrator(
        registry: new AgentRegistry($runner),
        agentsRenderer: new AgentsMdRenderer(),
        guidelinesAggregator: new GuidelinesAggregator(devaiWalker(), '/dev/null'),
        skillsDistributor: new SkillsDistributor(devaiWalker(), '/dev/null'),
        runner: $runner,
        docsDriverResolver: new DocsDriverResolver(),
    );
}

/**
 * Build an InstallCommand with all dependencies wired for testing.
 */
function makeInstallCmd(
    InstallationOrchestrator $orchestrator,
    DocsDriverResolver $resolver,
    ConfirmationPrompterInterface $prompter,
    CommandRunnerInterface $runner,
): InstallCommand {
    return new InstallCommand(
        orchestrator: $orchestrator,
        registry: new AgentRegistry(devaiRunner()),
        docsDriverResolver: $resolver,
        confirmationPrompter: $prompter,
        commandRunner: $runner,
    );
}

/**
 * Write a stub known-drivers.php under {tempRoot}/vendor/marko/docs/.
 *
 * @param array<string, string> $drivers
 */
function writeInstallCmdKnownDrivers(
    string $tempRoot,
    array $drivers,
): void {
    $docsDir = $tempRoot . '/vendor/marko/docs';
    mkdir($docsDir, 0755, true);
    $export = var_export($drivers, true);
    file_put_contents($docsDir . '/known-drivers.php', "<?php\nreturn $export;\n");
}

/** Create a fake vendor directory for the given package. */
function makeInstallCmdVendorDir(
    string $tempRoot,
    string $package,
): void {
    mkdir($tempRoot . '/vendor/' . $package, 0755, true);
}

/** Create an in-memory output stream and return the Output object. */
function makeInstallCmdOutput(): array
{
    $stream = fopen('php://memory', 'r+');

    return ['stream' => $stream, 'output' => new Output($stream)];
}

/** Read all output written to the memory stream. */
function readInstallCmdOutput(
    mixed $stream,
): string {
    rewind($stream);

    return (string) stream_get_contents($stream);
}

// ---------------------------------------------------------------------------
// State shared across new tests
// ---------------------------------------------------------------------------

$originalCwd = null;

beforeEach(function () use (&$originalCwd): void {
    $this->tempRoot = devaiTempDir();
    $originalCwd = getcwd();
});

afterEach(function () use (&$originalCwd): void {
    devaiRemoveDir($this->tempRoot);
    if ($originalCwd !== false) {
        chdir($originalCwd);
    }
});

// ---------------------------------------------------------------------------
// Original tests (kept green)
// ---------------------------------------------------------------------------

it('supports non-interactive mode via the --agents flag', function (): void {
    $input = new Input(['marko', 'devai:install', '--agents=claude-code']);
    expect($input->getOption('agents'))->toBe('claude-code');
});

it('is registered via Command attribute with name devai:install', function (): void {
    $reflection = new ReflectionClass(InstallCommand::class);

    expect($reflection->implementsInterface(CommandInterface::class))->toBeTrue();

    $attributes = $reflection->getAttributes(Command::class);

    expect($attributes)->toHaveCount(1)
        ->and($attributes[0]->newInstance()->name)->toBe('devai:install');
});

it('declares its boolean flags on the Command attribute', function (): void {
    $attribute = new ReflectionClass(InstallCommand::class)->getAttributes(Command::class)[0]->newInstance();

    expect($attribute->flags)->toBe(['force', 'update-gitignore', 'skip-lsp-deps', 'yes']);
});

// ---------------------------------------------------------------------------
// New tests: docs driver prompt flow
// ---------------------------------------------------------------------------

it('offers to install the recommended driver and runs composer require on yes', function (): void {
    writeInstallCmdKnownDrivers($this->tempRoot, [
        'marko/docs-fts' => 'Full-text search driver (recommended)',
    ]);
    chdir($this->tempRoot);

    $runner = makeInstallCmdRunner(composerOnPath: true);
    $prompter = new FakeConfirmationPrompter(answers: [true]);
    $cmd = makeInstallCmd(
        orchestrator: makeInstallCmdOrchestrator($this->tempRoot),
        resolver: new DocsDriverResolver(),
        prompter: $prompter,
        runner: $runner,
    );

    ['output' => $output] = makeInstallCmdOutput();
    $cmd->execute(new Input(['marko', 'devai:install']), $output);

    expect($runner->calls)->toContain(
        ['composer', ['require', '--dev', '--no-interaction', '--no-progress', 'marko/docs-fts']],
    )->and($prompter->asked)->toBe([
        'No docs search driver installed. Install marko/docs-fts to enable search_docs?',
    ]);
});

it('does not install anything when the user answers no', function (): void {
    writeInstallCmdKnownDrivers($this->tempRoot, [
        'marko/docs-fts' => 'Full-text search driver (recommended)',
    ]);
    chdir($this->tempRoot);

    $runner = makeInstallCmdRunner(composerOnPath: true);
    $cmd = makeInstallCmd(
        orchestrator: makeInstallCmdOrchestrator($this->tempRoot),
        resolver: new DocsDriverResolver(),
        prompter: new FakeConfirmationPrompter(answers: [false]),
        runner: $runner,
    );

    $cmd->execute(new Input(['marko', 'devai:install']), new Output(fopen('php://memory', 'r+')));

    $composerCalls = array_filter($runner->calls, fn ($c) => $c[0] === 'composer');
    expect($composerCalls)->toBeEmpty();
});

it('does not prompt or install when the session is not interactive', function (): void {
    writeInstallCmdKnownDrivers($this->tempRoot, [
        'marko/docs-fts' => 'Full-text search driver (recommended)',
    ]);
    chdir($this->tempRoot);

    $runner = makeInstallCmdRunner(composerOnPath: true);
    $cmd = makeInstallCmd(
        orchestrator: makeInstallCmdOrchestrator($this->tempRoot),
        resolver: new DocsDriverResolver(),
        prompter: new FakeConfirmationPrompter(interactive: false),
        runner: $runner,
    );

    $cmd->execute(new Input(['marko', 'devai:install']), new Output(fopen('php://memory', 'r+')));

    $composerCalls = array_filter($runner->calls, fn ($c) => $c[0] === 'composer');
    expect($composerCalls)->toBeEmpty();
});

it('does not prompt when a docs driver is already installed', function (): void {
    writeInstallCmdKnownDrivers($this->tempRoot, [
        'marko/docs-fts' => 'Full-text search driver (recommended)',
    ]);
    makeInstallCmdVendorDir($this->tempRoot, 'marko/docs-fts');
    chdir($this->tempRoot);

    $runner = makeInstallCmdRunner(composerOnPath: true);
    $cmd = makeInstallCmd(
        orchestrator: makeInstallCmdOrchestrator($this->tempRoot),
        resolver: new DocsDriverResolver(),
        prompter: new FakeConfirmationPrompter(answers: [true]),
        runner: $runner,
    );

    $cmd->execute(new Input(['marko', 'devai:install']), new Output(fopen('php://memory', 'r+')));

    $composerCalls = array_filter($runner->calls, fn ($c) => $c[0] === 'composer');
    expect($composerCalls)->toBeEmpty();
});

it('writes a helpful message when composer is not on PATH', function (): void {
    writeInstallCmdKnownDrivers($this->tempRoot, [
        'marko/docs-fts' => 'Full-text search driver (recommended)',
    ]);
    chdir($this->tempRoot);

    $runner = makeInstallCmdRunner(composerOnPath: false);
    $cmd = makeInstallCmd(
        orchestrator: makeInstallCmdOrchestrator($this->tempRoot),
        resolver: new DocsDriverResolver(),
        prompter: new FakeConfirmationPrompter(answers: [true]),
        runner: $runner,
    );

    ['stream' => $stream, 'output' => $output] = makeInstallCmdOutput();
    $cmd->execute(new Input(['marko', 'devai:install']), $output);

    $text = readInstallCmdOutput($stream);
    expect($text)->toContain('composer not found')
        ->and($text)->toContain('composer require --dev marko/docs-fts');
});

it('writes a helpful message and does not throw when composer require exits non-zero', function (): void {
    writeInstallCmdKnownDrivers($this->tempRoot, [
        'marko/docs-fts' => 'Full-text search driver (recommended)',
    ]);
    chdir($this->tempRoot);

    $runner = makeInstallCmdRunner(composerOnPath: true, requireExitCode: 1);
    $cmd = makeInstallCmd(
        orchestrator: makeInstallCmdOrchestrator($this->tempRoot),
        resolver: new DocsDriverResolver(),
        prompter: new FakeConfirmationPrompter(answers: [true]),
        runner: $runner,
    );

    ['stream' => $stream, 'output' => $output] = makeInstallCmdOutput();
    $exitCode = $cmd->execute(new Input(['marko', 'devai:install']), $output);

    $text = readInstallCmdOutput($stream);
    expect($exitCode)->toBe(0)
        ->and($text)->toContain('marko/docs-fts');
});

// ---------------------------------------------------------------------------
// Global intelephense install confirmation
// ---------------------------------------------------------------------------

/**
 * Run `devai:install --agents=claude-code` with npm on PATH and intelephense missing,
 * so the global LSP install is on the table.
 *
 * @param list<string> $extraArgs
 * @return array{runner: CommandRunnerInterface, text: string}
 */
function runClaudeInstallWithNpm(
    string $tempRoot,
    FakeConfirmationPrompter $prompter,
    array $extraArgs = [],
): array {
    chdir($tempRoot);
    $runner = makeInstallCmdRunner(composerOnPath: false, npmOnPath: true);
    $cmd = makeInstallCmd(
        orchestrator: makeInstallCmdOrchestrator($tempRoot, $runner),
        resolver: new DocsDriverResolver(),
        prompter: $prompter,
        runner: $runner,
    );

    ['stream' => $stream, 'output' => $output] = makeInstallCmdOutput();
    $cmd->execute(new Input(['marko', 'devai:install', '--agents=claude-code', ...$extraArgs]), $output);

    return ['runner' => $runner, 'text' => readInstallCmdOutput($stream)];
}

/** @return list<list<string>> args of every `npm` call */
function installCmdNpmCalls(
    CommandRunnerInterface $runner,
): array {
    return array_values(array_map(
        fn (array $call): array => $call[1],
        array_filter($runner->calls, fn (array $call): bool => $call[0] === 'npm'),
    ));
}

it('asks before installing the pinned intelephense globally and installs it on yes', function (): void {
    $prompter = new FakeConfirmationPrompter(answers: [true]);

    ['runner' => $runner] = runClaudeInstallWithNpm($this->tempRoot, $prompter);

    expect($prompter->asked)->toHaveCount(1)
        ->and($prompter->asked[0])->toContain('intelephense@' . IntelephenseEnsurer::VERSION)
        ->and($prompter->asked[0])->toContain('npm install -g')
        ->and(installCmdNpmCalls($runner))->toBe([['install', '-g', IntelephenseEnsurer::PACKAGE]]);
});

it('skips the global intelephense install when the user declines and says how to install it later', function (): void {
    $prompter = new FakeConfirmationPrompter(answers: [false]);

    ['runner' => $runner, 'text' => $text] = runClaudeInstallWithNpm($this->tempRoot, $prompter);

    expect(installCmdNpmCalls($runner))->toBe([])
        ->and($text)->toContain('npm install -g ' . IntelephenseEnsurer::PACKAGE);
});

it('installs intelephense without asking when --yes is passed', function (): void {
    $prompter = new FakeConfirmationPrompter();

    ['runner' => $runner] = runClaudeInstallWithNpm($this->tempRoot, $prompter, ['--yes']);

    expect($prompter->asked)->toBe([])
        ->and(installCmdNpmCalls($runner))->toBe([['install', '-g', IntelephenseEnsurer::PACKAGE]]);
});

it('does not ask about intelephense when the session is not interactive', function (): void {
    $prompter = new FakeConfirmationPrompter(interactive: false);

    ['runner' => $runner] = runClaudeInstallWithNpm($this->tempRoot, $prompter);

    expect($prompter->asked)->toBe([])
        ->and(installCmdNpmCalls($runner))->toBe([['install', '-g', IntelephenseEnsurer::PACKAGE]]);
});

it('honours --skip-lsp-deps when agents are auto-detected', function (): void {
    chdir($this->tempRoot);
    $prompter = new FakeConfirmationPrompter();
    $runner = makeInstallCmdRunner(composerOnPath: false, npmOnPath: true);
    $cmd = makeInstallCmd(
        orchestrator: makeInstallCmdOrchestrator($this->tempRoot, $runner),
        resolver: new DocsDriverResolver(),
        prompter: $prompter,
        runner: $runner,
    );

    $cmd->execute(
        new Input(['marko', 'devai:install', '--skip-lsp-deps']),
        new Output(fopen('php://memory', 'r+')),
    );

    expect($prompter->asked)->toBe([])
        ->and(installCmdNpmCalls($runner))->toBe([]);
});

// ---------------------------------------------------------------------------
// New tests: multi-instance config-isolation tip for Claude Code
// ---------------------------------------------------------------------------

it('prints a multi-instance config-isolation tip when Claude Code is installed', function (): void {
    chdir($this->tempRoot);

    $cmd = makeInstallCmd(
        orchestrator: makeInstallCmdOrchestrator($this->tempRoot),
        resolver: new DocsDriverResolver(),
        prompter: new FakeConfirmationPrompter(interactive: false),
        runner: makeInstallCmdRunner(composerOnPath: true),
    );

    ['stream' => $stream, 'output' => $output] = makeInstallCmdOutput();
    $cmd->execute(
        new Input(['marko', 'devai:install', '--agents=claude-code', '--no-interaction', '--skip-lsp-deps']),
        $output,
    );

    $text = readInstallCmdOutput($stream);
    expect($text)->toContain('multiple Claude Code instances')
        ->and($text)->toContain('marko.build/docs/ai-assisted-development/troubleshooting');
});

it('does not print the Claude Code tip when only non-Claude agents are installed', function (): void {
    chdir($this->tempRoot);

    $cmd = makeInstallCmd(
        orchestrator: makeInstallCmdOrchestrator($this->tempRoot),
        resolver: new DocsDriverResolver(),
        prompter: new FakeConfirmationPrompter(interactive: false),
        runner: makeInstallCmdRunner(composerOnPath: true),
    );

    ['stream' => $stream, 'output' => $output] = makeInstallCmdOutput();
    $cmd->execute(new Input(['marko', 'devai:install', '--agents=codex', '--no-interaction']), $output);

    $text = readInstallCmdOutput($stream);
    expect($text)->not->toContain('multiple Claude Code instances');
});

it('keeps devai dependent on the marko/docs contract only (no driver in require)', function (): void {
    $composerJson = json_decode(
        (string) file_get_contents(dirname(__DIR__, 3) . '/composer.json'),
        true,
    );

    $require = $composerJson['require'] ?? [];

    $driverKeys = array_filter(array_keys($require), fn ($k) => str_starts_with($k, 'marko/docs-'));

    expect($require)->toHaveKey('marko/docs')
        ->and($driverKeys)->toBeEmpty();
});
