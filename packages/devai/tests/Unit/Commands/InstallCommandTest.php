<?php

declare(strict_types=1);

use Marko\Core\Attributes\Command;
use Marko\Core\Command\CommandInterface;
use Marko\Core\Command\Input;
use Marko\Core\Command\Output;
use Marko\DevAi\Commands\InstallCommand;
use Marko\DevAi\Guidelines\GuidelinesAggregator;
use Marko\DevAi\Installation\AgentRegistry;
use Marko\DevAi\Installation\DocsDriverResolver;
use Marko\DevAi\Installation\InstallationOrchestrator;
use Marko\DevAi\Process\CommandRunnerInterface;
use Marko\DevAi\Process\ConfirmationPrompterInterface;
use Marko\DevAi\Rendering\AgentsMdRenderer;
use Marko\DevAi\Skills\SkillsDistributor;

// ---------------------------------------------------------------------------
// Local test helpers
// ---------------------------------------------------------------------------

/**
 * A scripted ConfirmationPrompterInterface double.
 */
function makeInstallCmdFakePrompter(bool $answer, bool $interactive = true): ConfirmationPrompterInterface
{
    return new readonly class ($answer, $interactive) implements ConfirmationPrompterInterface
    {
        public function __construct(
            private bool $answer,
            private bool $interactive,
        ) {}

        public function isInteractive(): bool
        {
            return $this->interactive;
        }

        public function confirm(string $question, bool $default): bool
        {
            return $this->answer;
        }
    };
}

/**
 * A CommandRunner double that records every call and returns a configurable
 * exit code for `composer require` commands.
 */
function makeInstallCmdRunner(bool $composerOnPath = true, int $requireExitCode = 0): CommandRunnerInterface
{
    return new class ($composerOnPath, $requireExitCode) implements CommandRunnerInterface
    {
        /** @var list<array{string, list<string>}> */
        public array $calls = [];

        public function __construct(
            private readonly bool $composerOnPath,
            private readonly int $requireExitCode,
        ) {}

        public function run(string $command, array $args = []): array
        {
            $this->calls[] = [$command, $args];

            if ($command === 'composer' && ($args[0] ?? '') === 'require') {
                return ['exitCode' => $this->requireExitCode, 'stdout' => '', 'stderr' => 'install failed'];
            }

            return ['exitCode' => 0, 'stdout' => '', 'stderr' => ''];
        }

        public function isOnPath(string $binary): bool
        {
            return $binary === 'composer' && $this->composerOnPath;
        }
    };
}

/**
 * Create a minimal InstallationOrchestrator stub for command-level tests.
 * Returns status=installed without running agent installs or real file system writes,
 * but it does create .marko/devai.json (orchestrator requires this).
 */
function makeInstallCmdOrchestrator(string $tempRoot): InstallationOrchestrator
{
    $runner = devaiRunner();

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
function writeInstallCmdKnownDrivers(string $tempRoot, array $drivers): void
{
    $docsDir = $tempRoot . '/vendor/marko/docs';
    mkdir($docsDir, 0755, true);
    $export = var_export($drivers, true);
    file_put_contents($docsDir . '/known-drivers.php', "<?php\nreturn $export;\n");
}

/** Create a fake vendor directory for the given package. */
function makeInstallCmdVendorDir(string $tempRoot, string $package): void
{
    mkdir($tempRoot . '/vendor/' . $package, 0755, true);
}

/** Create an in-memory output stream and return the Output object. */
function makeInstallCmdOutput(): array
{
    $stream = fopen('php://memory', 'r+');

    return ['stream' => $stream, 'output' => new Output($stream)];
}

/** Read all output written to the memory stream. */
function readInstallCmdOutput(mixed $stream): string
{
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

// ---------------------------------------------------------------------------
// New tests: docs driver prompt flow
// ---------------------------------------------------------------------------

it('offers to install the recommended driver and runs composer require on yes', function (): void {
    writeInstallCmdKnownDrivers($this->tempRoot, [
        'marko/docs-fts' => 'Full-text search driver (recommended)',
    ]);
    chdir($this->tempRoot);

    $runner = makeInstallCmdRunner(composerOnPath: true);
    $cmd = makeInstallCmd(
        orchestrator: makeInstallCmdOrchestrator($this->tempRoot),
        resolver: new DocsDriverResolver(),
        prompter: makeInstallCmdFakePrompter(answer: true),
        runner: $runner,
    );

    ['stream' => $stream, 'output' => $output] = makeInstallCmdOutput();
    $cmd->execute(new Input(['marko', 'devai:install']), $output);

    expect($runner->calls)->toContain(['composer', ['require', '--dev', 'marko/docs-fts']]);
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
        prompter: makeInstallCmdFakePrompter(answer: false),
        runner: $runner,
    );

    $cmd->execute(new Input(['marko', 'devai:install']), new Output(fopen('php://memory', 'r+')));

    $composerCalls = array_filter($runner->calls, fn ($c) => $c[0] === 'composer');
    expect($composerCalls)->toBeEmpty();
});

it('does not prompt or install when run with --no-interaction', function (): void {
    writeInstallCmdKnownDrivers($this->tempRoot, [
        'marko/docs-fts' => 'Full-text search driver (recommended)',
    ]);
    chdir($this->tempRoot);

    $runner = makeInstallCmdRunner(composerOnPath: true);
    $cmd = makeInstallCmd(
        orchestrator: makeInstallCmdOrchestrator($this->tempRoot),
        resolver: new DocsDriverResolver(),
        prompter: makeInstallCmdFakePrompter(answer: true),
        runner: $runner,
    );

    $cmd->execute(new Input(['marko', 'devai:install', '--no-interaction']), new Output(fopen('php://memory', 'r+')));

    $composerCalls = array_filter($runner->calls, fn ($c) => $c[0] === 'composer');
    expect($composerCalls)->toBeEmpty();
});

it('does not prompt or install when the session is not interactive (no TTY)', function (): void {
    writeInstallCmdKnownDrivers($this->tempRoot, [
        'marko/docs-fts' => 'Full-text search driver (recommended)',
    ]);
    chdir($this->tempRoot);

    $runner = makeInstallCmdRunner(composerOnPath: true);
    $cmd = makeInstallCmd(
        orchestrator: makeInstallCmdOrchestrator($this->tempRoot),
        resolver: new DocsDriverResolver(),
        prompter: makeInstallCmdFakePrompter(answer: true, interactive: false),
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
        prompter: makeInstallCmdFakePrompter(answer: true),
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
        prompter: makeInstallCmdFakePrompter(answer: true),
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
        prompter: makeInstallCmdFakePrompter(answer: true),
        runner: $runner,
    );

    ['stream' => $stream, 'output' => $output] = makeInstallCmdOutput();
    $exitCode = $cmd->execute(new Input(['marko', 'devai:install']), $output);

    $text = readInstallCmdOutput($stream);
    expect($exitCode)->toBe(0)
        ->and($text)->toContain('marko/docs-fts');
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
