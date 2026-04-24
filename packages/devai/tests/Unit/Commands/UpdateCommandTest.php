<?php

declare(strict_types=1);

use Marko\CodeIndexer\Contract\ModuleWalkerInterface;
use Marko\Core\Attributes\Command;
use Marko\Core\Command\CommandInterface;
use Marko\Core\Command\Input;
use Marko\Core\Command\Output;
use Marko\DevAi\Commands\UpdateCommand;
use Marko\DevAi\Guidelines\GuidelinesAggregator;
use Marko\DevAi\Installation\AgentRegistry;
use Marko\DevAi\Installation\InstallationContext;
use Marko\DevAi\Installation\InstallationOrchestrator;
use Marko\DevAi\Process\CommandRunnerInterface;
use Marko\DevAi\Rendering\AgentsMdRenderer;
use Marko\DevAi\Rendering\ClaudeMdRenderer;
use Marko\DevAi\Skills\SkillsDistributor;

class UpdateCommandTestStubOrchestrator extends InstallationOrchestrator
{
    public ?InstallationContext $capturedContext = null;
    public bool $installCalled = false;

    /** @var array{status: string, log?: list<string>} */
    public array $installReturn = ['status' => 'installed', 'log' => []];

    public function install(InstallationContext $ctx, string $projectRoot, bool $force): array
    {
        $this->capturedContext = $ctx;
        $this->installCalled = true;

        return $this->installReturn;
    }
}

class UpdateCommandTestNullWalker implements ModuleWalkerInterface
{
    public function walk(): array
    {
        return [];
    }
}

class UpdateCommandTestNullRunner implements CommandRunnerInterface
{
    public function run(string $command, array $args = []): array
    {
        return ['exitCode' => 0, 'stdout' => '', 'stderr' => ''];
    }

    public function isOnPath(string $binary): bool
    {
        return false;
    }
}

function makeUpdateTestOrchestrator(): UpdateCommandTestStubOrchestrator
{
    $walker = new UpdateCommandTestNullWalker();
    $runner = new UpdateCommandTestNullRunner();

    return new UpdateCommandTestStubOrchestrator(
        registry: new AgentRegistry($runner),
        agentsRenderer: new AgentsMdRenderer(),
        claudeRenderer: new ClaudeMdRenderer(),
        guidelinesAggregator: new GuidelinesAggregator($walker, '/dev/null'),
        skillsDistributor: new SkillsDistributor($walker, '/dev/null'),
    );
}

function makeUpdateTestAggregator(): GuidelinesAggregator
{
    return new GuidelinesAggregator(new UpdateCommandTestNullWalker(), '/dev/null');
}

/** @return array{input: Input, output: Output, stream: resource} */
function makeUpdateTestInputOutput(): array
{
    $stream = fopen('php://memory', 'rw');
    assert(is_resource($stream));

    return [
        'input' => new Input(['marko', 'devai:update']),
        'output' => new Output($stream),
        'stream' => $stream,
    ];
}

function readUpdateTestStream(mixed $stream): string
{
    rewind($stream);

    return (string) stream_get_contents($stream);
}

beforeEach(function (): void {
    $this->tempRoot = sys_get_temp_dir() . '/devai-update-test-' . uniqid();
    mkdir($this->tempRoot, 0755, true);
    $this->originalCwd = getcwd();
    chdir($this->tempRoot);
});

afterEach(function (): void {
    chdir($this->originalCwd);
    $iter = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($this->tempRoot, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST,
    );
    foreach ($iter as $f) {
        $f->isDir() ? rmdir($f->getPathname()) : unlink($f->getPathname());
    }
    rmdir($this->tempRoot);
});

it('is registered via Command attribute with name devai:update', function (): void {
    $reflection = new ReflectionClass(UpdateCommand::class);

    expect($reflection->implementsInterface(CommandInterface::class))->toBeTrue();

    $attributes = $reflection->getAttributes(Command::class);

    expect($attributes)->toHaveCount(1)
        ->and($attributes[0]->newInstance()->name)->toBe('devai:update');
});

it('reads prior agent selection from .marko/devai.json', function (): void {
    mkdir($this->tempRoot . '/.marko', 0755, true);
    file_put_contents(
        $this->tempRoot . '/.marko/devai.json',
        json_encode(['agents' => ['claude-code'], 'docsDriver' => 'vec']),
    );

    $orchestrator = makeUpdateTestOrchestrator();
    $command = new UpdateCommand($orchestrator, makeUpdateTestAggregator());
    ['input' => $input, 'output' => $output] = makeUpdateTestInputOutput();
    $command->execute($input, $output);

    expect($orchestrator->capturedContext)->not->toBeNull()
        ->and($orchestrator->capturedContext->selectedAgents)->toBe(['claude-code'])
        ->and($orchestrator->capturedContext->docsDriver)->toBe('vec');
});

it('re-runs each previously selected adapter', function (): void {
    mkdir($this->tempRoot . '/.marko', 0755, true);
    file_put_contents(
        $this->tempRoot . '/.marko/devai.json',
        json_encode(['agents' => ['claude-code', 'codex'], 'docsDriver' => 'fts']),
    );

    $orchestrator = makeUpdateTestOrchestrator();
    $orchestrator->installReturn = ['status' => 'installed', 'log' => ['[claude-code] wrote guidelines', '[codex] wrote guidelines']];

    $command = new UpdateCommand($orchestrator, makeUpdateTestAggregator());
    ['input' => $input, 'output' => $output] = makeUpdateTestInputOutput();
    $result = $command->execute($input, $output);

    expect($orchestrator->installCalled)->toBeTrue()
        ->and($result)->toBe(0);
});

it('detects and reports newly contributed guidelines from new packages', function (): void {
    mkdir($this->tempRoot . '/.marko', 0755, true);
    file_put_contents(
        $this->tempRoot . '/.marko/devai.json',
        json_encode([
            'agents' => [],
            'docsDriver' => 'vec',
            'guidelines' => ['marko/core'],
        ]),
    );

    $orchestrator = makeUpdateTestOrchestrator();

    // Build aggregator that returns marko/core + marko/authentication (the "new" one)
    $tempBase = sys_get_temp_dir() . '/devai-aggregator-' . uniqid();
    mkdir($tempBase . '/resources/ai/guidelines', 0755, true);
    file_put_contents($tempBase . '/resources/ai/guidelines/core.md', 'core guidelines');
    $authPkgDir = $tempBase . '/packages/marko-authentication';
    mkdir($authPkgDir . '/resources/ai', 0755, true);
    file_put_contents($authPkgDir . '/resources/ai/guidelines.md', 'auth guidelines');

    $walker = new class ($authPkgDir) implements ModuleWalkerInterface
    {
        public function __construct(private string $authPath) {}

        public function walk(): array
        {
            return [new \Marko\CodeIndexer\ValueObject\ModuleInfo(name: 'marko/authentication', path: $this->authPath, namespace: '')];
        }
    };
    $aggregator = new GuidelinesAggregator($walker, $tempBase);

    $command = new UpdateCommand($orchestrator, $aggregator);
    ['input' => $input, 'output' => $output, 'stream' => $stream] = makeUpdateTestInputOutput();
    $command->execute($input, $output);

    $captured = readUpdateTestStream($stream);
    expect($captured)->toContain('marko/authentication');

    // Cleanup tempBase
    $iter = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($tempBase, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST,
    );
    foreach ($iter as $f) {
        $f->isDir() ? rmdir($f->getPathname()) : unlink($f->getPathname());
    }
    rmdir($tempBase);
});

it('prints a summary of files changed', function (): void {
    mkdir($this->tempRoot . '/.marko', 0755, true);
    file_put_contents(
        $this->tempRoot . '/.marko/devai.json',
        json_encode(['agents' => ['claude-code'], 'docsDriver' => 'vec']),
    );

    $orchestrator = makeUpdateTestOrchestrator();
    $orchestrator->installReturn = ['status' => 'installed', 'log' => ['[claude-code] wrote guidelines']];

    $command = new UpdateCommand($orchestrator, makeUpdateTestAggregator());
    ['input' => $input, 'output' => $output, 'stream' => $stream] = makeUpdateTestInputOutput();
    $command->execute($input, $output);

    $captured = readUpdateTestStream($stream);
    expect($captured)->toContain('Update summary:')
        ->and($captured)->toContain('[claude-code] wrote guidelines');
});

it('errors with helpful suggestion when no prior install config exists', function (): void {
    $orchestrator = makeUpdateTestOrchestrator();
    $command = new UpdateCommand($orchestrator, makeUpdateTestAggregator());
    ['input' => $input, 'output' => $output] = makeUpdateTestInputOutput();

    $result = $command->execute($input, $output);

    expect($result)->toBe(1);
});
