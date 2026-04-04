<?php

declare(strict_types=1);

use Marko\Core\Attributes\Command;
use Marko\Core\Command\Input;
use Marko\Core\Command\Output;
use Marko\Core\Path\ProjectPaths;
use Marko\DevServer\Command\DevUpCommand;
use Marko\DevServer\Detection\DockerDetector;
use Marko\DevServer\Detection\FrontendDetector;
use Marko\DevServer\Detection\PubSubDetector;
use Marko\DevServer\Exceptions\DevServerException;
use Marko\DevServer\Process\PidFile;
use Marko\DevServer\Process\ProcessEntry;
use Marko\DevServer\Process\ProcessManager;
use Marko\Testing\Fake\FakeConfigRepository;

// Test double for ProcessManager that records calls without starting real processes
class FakeProcessManager extends ProcessManager
{
    /** @var array<string, string> */
    public array $started = [];

    public bool $foregroundCalled = false;

    /** @var array<string, bool> Override isRunning results per process name */
    public array $runningOverrides = [];

    /** @noinspection PhpMissingParentConstructorInspection - Test stub intentionally skips parent */
    public function __construct() {}

    public function start(
        string $name,
        string $command,
    ): int {
        $this->started[$name] = $command;

        return 12345;
    }

    public function isRunning(string $name): bool
    {
        return $this->runningOverrides[$name] ?? true;
    }

    public function startDetached(
        string $name,
        string $command,
    ): int {
        $this->started[$name] = $command;

        return 12345;
    }

    public function runForeground(): void
    {
        $this->foregroundCalled = true;
    }
}

const CONFIG_DEFAULTS = [
    'dev.port' => 8000,
    'dev.host' => 'localhost',
    'dev.docker' => false,
    'dev.frontend' => false,
    'dev.pubsub' => false,
    'dev.detach' => true,
    'dev.processes' => [],
];

/**
 * Helper to create a DevUpCommand with standard test dependencies.
 *
 * @param array<string, mixed> $config
 * @param string|null $tempDir Directory to use for detectors (null = no files present)
 * @param PubSubDetector|null $pubsubDetector Optional PubSubDetector override
 * @return array{command: DevUpCommand, processManager: FakeProcessManager, pidFile: PidFile, tempDir: string}
 */
function createDevUpCommand(
    array $config = [],
    ?string $tempDir = null,
    ?PubSubDetector $pubsubDetector = null,
): array {
    $dir = $tempDir ?? sys_get_temp_dir() . '/marko-test-' . uniqid();
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    // Create public/index.php so existing tests don't trigger the guard
    if (!is_dir($dir . '/public')) {
        mkdir($dir . '/public', 0755, true);
    }
    if (!file_exists($dir . '/public/index.php')) {
        file_put_contents($dir . '/public/index.php', '<?php');
    }

    $fakeConfig = new FakeConfigRepository(array_merge(CONFIG_DEFAULTS, $config));

    $dockerDetector = new DockerDetector($dir);
    $frontendDetector = new FrontendDetector($dir);
    $resolvedPubsubDetector = $pubsubDetector ?? new PubSubDetector();
    $pidFile = new PidFile($dir);
    $processManager = new FakeProcessManager();
    $paths = new ProjectPaths($dir);

    $command = new DevUpCommand(
        config: $fakeConfig,
        dockerDetector: $dockerDetector,
        frontendDetector: $frontendDetector,
        pubsubDetector: $resolvedPubsubDetector,
        pidFile: $pidFile,
        processManager: $processManager,
        paths: $paths,
    );

    return ['command' => $command, 'processManager' => $processManager, 'pidFile' => $pidFile, 'tempDir' => $dir];
}

/**
 * Helper to create an Output writing to memory stream.
 *
 * @return array{stream: resource, output: Output}
 */
function createMemoryOutput(): array
{
    $stream = fopen('php://memory', 'r+');
    $output = new Output($stream);

    return ['stream' => $stream, 'output' => $output];
}

/**
 * Helper to read content from a memory stream.
 *
 * @param resource $stream
 */
function readStream(mixed $stream): string
{
    rewind($stream);

    return stream_get_contents($stream);
}

it('reads port from config', function (): void {
    ['command' => $command, 'processManager' => $pm] = createDevUpCommand(['dev.port' => 7500]);
    ['output' => $output] = createMemoryOutput();

    $input = new Input(['marko', 'dev:up']);
    $command->execute($input, $output);

    expect($pm->started['php'])->toContain('localhost:7500');
});

it('reads host from config', function (): void {
    ['command' => $command, 'processManager' => $pm] = createDevUpCommand(['dev.host' => '127.0.0.1']);
    ['output' => $output] = createMemoryOutput();

    $input = new Input(['marko', 'dev:up']);
    $command->execute($input, $output);

    expect($pm->started['php'])->toContain('127.0.0.1:8000');
});

it('reads docker setting from config', function (): void {
    ['command' => $command, 'processManager' => $pm] = createDevUpCommand([
        'dev.docker' => 'custom-docker-up',
        'dev.detach' => false,
    ]);
    ['output' => $output] = createMemoryOutput();

    $input = new Input(['marko', 'dev:up']);
    $command->execute($input, $output);

    expect($pm->started)->toHaveKey('docker')
        ->and($pm->started['docker'])->toBe('custom-docker-up');
});

it('reads frontend setting from config', function (): void {
    ['command' => $command, 'processManager' => $pm] = createDevUpCommand([
        'dev.frontend' => 'yarn dev',
    ]);
    ['output' => $output] = createMemoryOutput();

    $input = new Input(['marko', 'dev:up']);
    $command->execute($input, $output);

    expect($pm->started)->toHaveKey('frontend')
        ->and($pm->started['frontend'])->toBe('yarn dev');
});

it('reads detach setting from config', function (): void {
    ['command' => $command, 'pidFile' => $pidFile] = createDevUpCommand([
        'dev.port' => 8000,
        'dev.detach' => true,
    ]);
    ['output' => $output] = createMemoryOutput();

    $input = new Input(['marko', 'dev:up']);
    $command->execute($input, $output);

    $entries = $pidFile->read();

    expect($entries)->not->toBeEmpty();
});

it('overrides config port with --port flag', function (): void {
    ['command' => $command, 'processManager' => $pm] = createDevUpCommand(['dev.port' => 8000]);
    ['output' => $output] = createMemoryOutput();

    $input = new Input(['marko', 'dev:up', '--port=9999']);
    $command->execute($input, $output);

    expect($pm->started['php'])->toContain('localhost:9999')
        ->and($pm->started['php'])->not->toContain('localhost:8000');
});

it('overrides config detach with --detach flag', function (): void {
    ['command' => $command, 'pidFile' => $pidFile] = createDevUpCommand([
        'dev.port' => 8000,
        'dev.detach' => false,
    ]);
    ['output' => $output] = createMemoryOutput();

    $input = new Input(['marko', 'dev:up', '--detach']);
    $command->execute($input, $output);

    $entries = $pidFile->read();

    expect($entries)->not->toBeEmpty();
});

it('has Command attribute with name dev:up and alias up', function (): void {
    $reflection = new ReflectionClass(DevUpCommand::class);
    $attributes = $reflection->getAttributes(Command::class);

    expect($attributes)->not->toBeEmpty();

    $attr = $attributes[0]->newInstance();

    expect($attr->name)->toBe('dev:up')
        ->and($attr->aliases)->toContain('up');
});

it('starts PHP server on configured port', function (): void {
    ['command' => $command, 'processManager' => $pm] = createDevUpCommand(['dev.port' => 8000]);
    ['output' => $output] = createMemoryOutput();

    $input = new Input(['marko', 'dev:up']);
    $command->execute($input, $output);

    expect($pm->started)->toHaveKey('php')
        ->and($pm->started['php'])->toContain('localhost:8000');
});

it('overrides port with --port flag', function (): void {
    ['command' => $command, 'processManager' => $pm] = createDevUpCommand(['dev.port' => 8000]);
    ['output' => $output] = createMemoryOutput();

    $input = new Input(['marko', 'dev:up', '--port=9000']);
    $command->execute($input, $output);

    expect($pm->started)->toHaveKey('php')
        ->and($pm->started['php'])->toContain('localhost:9000');
});

it('starts Docker when docker config is true and compose file exists', function (): void {
    $tempDir = sys_get_temp_dir() . '/marko-docker-test-' . uniqid();
    mkdir($tempDir, 0755, true);
    file_put_contents($tempDir . '/compose.yaml', "version: '3'\nservices:\n  app:\n    image: nginx\n");

    ['command' => $command, 'processManager' => $pm] = createDevUpCommand(
        config: ['dev.docker' => true],
        tempDir: $tempDir,
    );
    ['output' => $output] = createMemoryOutput();

    $input = new Input(['marko', 'dev:up']);
    $command->execute($input, $output);

    expect($pm->started)->toHaveKey('docker')
        ->and($pm->started['docker'])->toContain('compose.yaml');
});

it('skips Docker when docker config is false', function (): void {
    ['command' => $command, 'processManager' => $pm] = createDevUpCommand(['dev.docker' => false]);
    ['output' => $output] = createMemoryOutput();

    $input = new Input(['marko', 'dev:up']);
    $command->execute($input, $output);

    expect($pm->started)->not->toHaveKey('docker');
});

it('uses custom Docker command when docker config is a string', function (): void {
    ['command' => $command, 'processManager' => $pm] = createDevUpCommand([
        'dev.docker' => 'custom-docker up --detach',
        'dev.detach' => false,
    ]);
    ['output' => $output] = createMemoryOutput();

    $input = new Input(['marko', 'dev:up']);
    $command->execute($input, $output);

    expect($pm->started)->toHaveKey('docker')
        ->and($pm->started['docker'])->toBe('custom-docker up --detach');
});

it('starts frontend when frontend config is true and package.json has dev script', function (): void {
    $tempDir = sys_get_temp_dir() . '/marko-frontend-test-' . uniqid();
    mkdir($tempDir, 0755, true);
    file_put_contents($tempDir . '/package.json', json_encode(['scripts' => ['dev' => 'vite']]));

    ['command' => $command, 'processManager' => $pm] = createDevUpCommand(
        config: ['dev.frontend' => true],
        tempDir: $tempDir,
    );
    ['output' => $output] = createMemoryOutput();

    $input = new Input(['marko', 'dev:up']);
    $command->execute($input, $output);

    expect($pm->started)->toHaveKey('frontend');
});

it('skips frontend when frontend config is false', function (): void {
    ['command' => $command, 'processManager' => $pm] = createDevUpCommand(['dev.frontend' => false]);
    ['output' => $output] = createMemoryOutput();

    $input = new Input(['marko', 'dev:up']);
    $command->execute($input, $output);

    expect($pm->started)->not->toHaveKey('frontend');
});

it('uses custom frontend command when frontend config is a string', function (): void {
    ['command' => $command, 'processManager' => $pm] = createDevUpCommand([
        'dev.frontend' => 'yarn start',
    ]);
    ['output' => $output] = createMemoryOutput();

    $input = new Input(['marko', 'dev:up']);
    $command->execute($input, $output);

    expect($pm->started)->toHaveKey('frontend')
        ->and($pm->started['frontend'])->toBe('yarn start');
});

it('writes PID file when --detach flag is used', function (): void {
    ['command' => $command, 'pidFile' => $pidFile, 'tempDir' => $tempDir] = createDevUpCommand([
        'dev.port' => 8000,
        'dev.detach' => false,
    ]);
    ['output' => $output] = createMemoryOutput();

    $input = new Input(['marko', 'dev:up', '--detach']);
    $command->execute($input, $output);

    $entries = $pidFile->read();

    expect($entries)->not->toBeEmpty()
        ->and($entries[0]->name)->toBe('php')
        ->and($entries[0]->pid)->toBe(12345);
});

it('outputs service summary on startup', function (): void {
    ['command' => $command] = createDevUpCommand(['dev.port' => 8000]);
    ['stream' => $stream, 'output' => $output] = createMemoryOutput();

    $input = new Input(['marko', 'dev:up']);
    $command->execute($input, $output);

    $content = readStream($stream);

    expect($content)->toContain('Starting development environment...')
        ->and($content)->toContain('Starting PHP server');
});

it('calls runForeground when not detached', function (): void {
    ['command' => $command, 'processManager' => $pm] = createDevUpCommand(['dev.detach' => false]);
    ['output' => $output] = createMemoryOutput();

    $input = new Input(['marko', 'dev:up']);
    $command->execute($input, $output);

    expect($pm->foregroundCalled)->toBeTrue();
});

it('does not call runForeground when detached', function (): void {
    ['command' => $command, 'processManager' => $pm] = createDevUpCommand(['dev.detach' => true]);
    ['output' => $output] = createMemoryOutput();

    $input = new Input(['marko', 'dev:up']);
    $command->execute($input, $output);

    expect($pm->foregroundCalled)->toBeFalse();
});

it('does not call runForeground when --detach flag is used', function (): void {
    ['command' => $command, 'processManager' => $pm] = createDevUpCommand(['dev.detach' => false]);
    ['output' => $output] = createMemoryOutput();

    $input = new Input(['marko', 'dev:up', '--detach']);
    $command->execute($input, $output);

    expect($pm->foregroundCalled)->toBeFalse();
});

it('overrides config detach with -d short flag', function (): void {
    ['command' => $command, 'processManager' => $pm, 'pidFile' => $pidFile] = createDevUpCommand([
        'dev.detach' => false,
    ]);
    ['output' => $output] = createMemoryOutput();

    $input = new Input(['marko', 'dev:up', '-d']);
    $command->execute($input, $output);

    expect($pm->foregroundCalled)->toBeFalse()
        ->and($pidFile->read())->not->toBeEmpty();
});

it('overrides config port with -p short flag', function (): void {
    ['command' => $command, 'processManager' => $pm] = createDevUpCommand(['dev.port' => 8000]);
    ['output' => $output] = createMemoryOutput();

    $input = new Input(['marko', 'dev:up', '-p=9000']);
    $command->execute($input, $output);

    expect($pm->started['php'])->toContain('localhost:9000');
});

it('overrides config port with -h short flag', function (): void {
    ['command' => $command, 'processManager' => $pm] = createDevUpCommand(['dev.host' => 'localhost']);
    ['output' => $output] = createMemoryOutput();

    $input = new Input(['marko', 'dev:up', '-h=0.0.0.0']);
    $command->execute($input, $output);

    expect($pm->started['php'])->toContain('0.0.0.0:8000');
});


it('overrides config port with -p space syntax', function (): void {
    ['command' => $command, 'processManager' => $pm] = createDevUpCommand(['dev.port' => 8000]);
    ['output' => $output] = createMemoryOutput();

    $input = new Input(['marko', 'dev:up', '-p', '9000']);
    $command->execute($input, $output);

    expect($pm->started['php'])->toContain('localhost:9000');
});

it('runs docker without -d flag as a managed process', function (): void {
    $tempDir = sys_get_temp_dir() . '/marko-docker-fg-test-' . uniqid();
    mkdir($tempDir, 0755, true);
    file_put_contents($tempDir . '/compose.yaml', "version: '3'\nservices:\n  app:\n    image: nginx\n");

    ['command' => $command, 'processManager' => $pm] = createDevUpCommand(
        config: ['dev.docker' => true, 'dev.detach' => false],
        tempDir: $tempDir,
    );
    ['output' => $output] = createMemoryOutput();

    $input = new Input(['marko', 'dev:up']);
    $command->execute($input, $output);

    expect($pm->started['docker'])->not->toContain('-d');
});

it('starts custom processes from config', function (): void {
    ['command' => $command, 'processManager' => $pm] = createDevUpCommand([
        'dev.processes' => [
            'tailwind' => './tailwindcss -i src/css/app.css -o public/css/app.css --watch',
        ],
    ]);
    ['output' => $output] = createMemoryOutput();

    $input = new Input(['marko', 'dev:up']);
    $command->execute($input, $output);

    expect($pm->started)->toHaveKey('tailwind')
        ->and($pm->started['tailwind'])->toBe('./tailwindcss -i src/css/app.css -o public/css/app.css --watch');
});

it('starts multiple custom processes from config', function (): void {
    ['command' => $command, 'processManager' => $pm] = createDevUpCommand([
        'dev.processes' => [
            'tailwind' => './tailwindcss --watch',
            'queue' => 'php marko queue:work',
        ],
    ]);
    ['output' => $output] = createMemoryOutput();

    $input = new Input(['marko', 'dev:up']);
    $command->execute($input, $output);

    expect($pm->started)->toHaveKey('tailwind')
        ->and($pm->started['tailwind'])->toBe('./tailwindcss --watch')
        ->and($pm->started)->toHaveKey('queue')
        ->and($pm->started['queue'])->toBe('php marko queue:work');
});

it('skips custom processes when config is empty', function (): void {
    ['command' => $command, 'processManager' => $pm] = createDevUpCommand([
        'dev.processes' => [],
    ]);
    ['output' => $output] = createMemoryOutput();

    $input = new Input(['marko', 'dev:up']);
    $command->execute($input, $output);

    // Only php should be started (docker and frontend are false by default)
    expect($pm->started)->toHaveCount(1)
        ->and($pm->started)->toHaveKey('php');
});

it('outputs custom process names on startup', function (): void {
    ['command' => $command] = createDevUpCommand([
        'dev.processes' => [
            'tailwind' => './tailwindcss --watch',
        ],
    ]);
    ['stream' => $stream, 'output' => $output] = createMemoryOutput();

    $input = new Input(['marko', 'dev:up']);
    $command->execute($input, $output);

    $content = readStream($stream);

    expect($content)->toContain('Starting tailwind: ./tailwindcss --watch');
});

it('includes custom processes in PID file when detached', function (): void {
    ['command' => $command, 'pidFile' => $pidFile] = createDevUpCommand([
        'dev.detach' => true,
        'dev.processes' => [
            'tailwind' => './tailwindcss --watch',
        ],
    ]);
    ['output' => $output] = createMemoryOutput();

    $input = new Input(['marko', 'dev:up']);
    $command->execute($input, $output);

    $entries = $pidFile->read();
    $names = array_map(fn($e) => $e->name, $entries);

    expect($names)->toContain('tailwind')
        ->and($names)->toContain('php');
});

it('never appends -d to docker command even in detach mode', function (): void {
    $tempDir = sys_get_temp_dir() . '/marko-docker-det-test-' . uniqid();
    mkdir($tempDir, 0755, true);
    file_put_contents($tempDir . '/compose.yaml', "version: '3'\nservices:\n  app:\n    image: nginx\n");

    ['command' => $command, 'processManager' => $pm] = createDevUpCommand(
        config: ['dev.docker' => true, 'dev.detach' => true],
        tempDir: $tempDir,
    );
    ['output' => $output] = createMemoryOutput();

    $input = new Input(['marko', 'dev:up']);
    $command->execute($input, $output);

    expect($pm->started['docker'])->not->toContain('-d');
});

it('starts pubsub:listen as managed process in DevUpCommand when detected', function (): void {
    $pubsubDetector = new class() extends PubSubDetector
    {
        protected function isPubSubInstalled(): bool
        {
            return true;
        }
    };

    ['command' => $command, 'processManager' => $pm] = createDevUpCommand(
        config: ['dev.pubsub' => true],
        pubsubDetector: $pubsubDetector,
    );
    ['output' => $output] = createMemoryOutput();

    $input = new Input(['marko', 'dev:up']);
    $command->execute($input, $output);

    expect($pm->started)->toHaveKey('pubsub')
        ->and($pm->started['pubsub'])->toBe('marko pubsub:listen');
});

it('throws DevServerException when public/index.php does not exist', function (): void {
    $dir = sys_get_temp_dir() . '/marko-no-index-' . uniqid();
    mkdir($dir, 0755, true);
    // Deliberately no public/index.php created

    $fakeConfig = new FakeConfigRepository(CONFIG_DEFAULTS);
    $command = new DevUpCommand(
        config: $fakeConfig,
        dockerDetector: new DockerDetector($dir),
        frontendDetector: new FrontendDetector($dir),
        pubsubDetector: new PubSubDetector(),
        pidFile: new PidFile($dir),
        processManager: new FakeProcessManager(),
        paths: new ProjectPaths($dir),
    );
    ['output' => $output] = createMemoryOutput();

    $input = new Input(['marko', 'dev:up']);
    $command->execute($input, $output);
})->throws(DevServerException::class);

it('includes helpful message with bootstrap code in the exception', function (): void {
    $dir = sys_get_temp_dir() . '/marko-no-index-msg-' . uniqid();
    mkdir($dir, 0755, true);

    $fakeConfig = new FakeConfigRepository(CONFIG_DEFAULTS);
    $command = new DevUpCommand(
        config: $fakeConfig,
        dockerDetector: new DockerDetector($dir),
        frontendDetector: new FrontendDetector($dir),
        pubsubDetector: new PubSubDetector(),
        pidFile: new PidFile($dir),
        processManager: new FakeProcessManager(),
        paths: new ProjectPaths($dir),
    );
    ['output' => $output] = createMemoryOutput();

    $input = new Input(['marko', 'dev:up']);

    try {
        $command->execute($input, $output);
        expect(false)->toBeTrue('Expected DevServerException was not thrown');
    } catch (DevServerException $e) {
        expect($e->getMessage())->toContain('public/index.php not found')
            ->and($e->getSuggestion())->toContain('Application::boot(')
            ->and($e->getSuggestion())->toContain('handleRequest()');
    }
});

it('throws before any processes start when public/index.php is missing', function (): void {
    $dir = sys_get_temp_dir() . '/marko-no-index-proc-' . uniqid();
    mkdir($dir, 0755, true);

    $fakeConfig = new FakeConfigRepository(array_merge(CONFIG_DEFAULTS, [
        'dev.docker' => 'docker compose up',
        'dev.frontend' => 'yarn dev',
        'dev.detach' => false,
    ]));
    $pm = new FakeProcessManager();
    $command = new DevUpCommand(
        config: $fakeConfig,
        dockerDetector: new DockerDetector($dir),
        frontendDetector: new FrontendDetector($dir),
        pubsubDetector: new PubSubDetector(),
        pidFile: new PidFile($dir),
        processManager: $pm,
        paths: new ProjectPaths($dir),
    );
    ['output' => $output] = createMemoryOutput();

    $input = new Input(['marko', 'dev:up']);

    try {
        $command->execute($input, $output);
    } catch (DevServerException) {
        // expected
    }

    expect($pm->started)->toBeEmpty();
});

it('starts PHP server normally when public/index.php exists', function (): void {
    $dir = sys_get_temp_dir() . '/marko-with-index-' . uniqid();
    mkdir($dir . '/public', 0755, true);
    file_put_contents($dir . '/public/index.php', '<?php');

    $fakeConfig = new FakeConfigRepository(CONFIG_DEFAULTS);
    $pm = new FakeProcessManager();
    $command = new DevUpCommand(
        config: $fakeConfig,
        dockerDetector: new DockerDetector($dir),
        frontendDetector: new FrontendDetector($dir),
        pubsubDetector: new PubSubDetector(),
        pidFile: new PidFile($dir),
        processManager: $pm,
        paths: new ProjectPaths($dir),
    );
    ['output' => $output] = createMemoryOutput();

    $input = new Input(['marko', 'dev:up']);
    $command->execute($input, $output);

    expect($pm->started)->toHaveKey('php')
        ->and($pm->started['php'])->toContain('localhost:8000');
});

it('calls runForeground when --foreground flag is used', function (): void {
    ['command' => $command, 'processManager' => $pm] = createDevUpCommand();
    ['output' => $output] = createMemoryOutput();

    $input = new Input(['marko', 'dev:up', '--foreground']);
    $command->execute($input, $output);

    expect($pm->foregroundCalled)->toBeTrue();
});

it('does not call runForeground in default detached mode', function (): void {
    ['command' => $command, 'processManager' => $pm] = createDevUpCommand();
    ['output' => $output] = createMemoryOutput();

    $input = new Input(['marko', 'dev:up']);
    $command->execute($input, $output);

    expect($pm->foregroundCalled)->toBeFalse();
});

it('writes PID file in default detached mode', function (): void {
    ['command' => $command, 'pidFile' => $pidFile] = createDevUpCommand();
    ['output' => $output] = createMemoryOutput();

    $input = new Input(['marko', 'dev:up']);
    $command->execute($input, $output);

    $entries = $pidFile->read();

    expect($entries)->not->toBeEmpty()
        ->and($entries[0]->name)->toBe('php')
        ->and($entries[0]->pid)->toBe(12345);
});

it('reads foreground setting from config when dev.detach is false', function (): void {
    ['command' => $command, 'processManager' => $pm] = createDevUpCommand([
        'dev.detach' => false,
    ]);
    ['output' => $output] = createMemoryOutput();

    $input = new Input(['marko', 'dev:up']);
    $command->execute($input, $output);

    expect($pm->foregroundCalled)->toBeTrue();
});

it('runs in foreground mode when -f flag is used', function (): void {
    ['command' => $command, 'processManager' => $pm] = createDevUpCommand();
    ['output' => $output] = createMemoryOutput();

    $input = new Input(['marko', 'dev:up', '-f']);
    $command->execute($input, $output);

    expect($pm->foregroundCalled)->toBeTrue();
});

it('runs in foreground mode when --foreground flag is used', function (): void {
    ['command' => $command, 'processManager' => $pm] = createDevUpCommand();
    ['output' => $output] = createMemoryOutput();

    $input = new Input(['marko', 'dev:up', '--foreground']);
    $command->execute($input, $output);

    expect($pm->foregroundCalled)->toBeTrue();
});

it('defaults to detached mode when no flags are passed', function (): void {
    ['command' => $command, 'processManager' => $pm, 'pidFile' => $pidFile] = createDevUpCommand();
    ['output' => $output] = createMemoryOutput();

    $input = new Input(['marko', 'dev:up']);
    $command->execute($input, $output);

    expect($pm->foregroundCalled)->toBeFalse()
        ->and($pidFile->read())->not->toBeEmpty();
});

it('skips pubsub process when pubsub config is false', function (): void {
    $pubsubDetector = new class() extends PubSubDetector
    {
        protected function isPubSubInstalled(): bool
        {
            return true;
        }
    };

    ['command' => $command, 'processManager' => $pm] = createDevUpCommand(
        config: ['dev.pubsub' => false],
        pubsubDetector: $pubsubDetector,
    );
    ['output' => $output] = createMemoryOutput();

    $input = new Input(['marko', 'dev:up']);
    $command->execute($input, $output);

    expect($pm->started)->not->toHaveKey('pubsub');
});

it('throws DevServerException when services are already running', function (): void {
    $tmpDir = sys_get_temp_dir() . '/marko-already-running-' . uniqid();
    mkdir($tmpDir, 0755, true);

    $pidFile = new PidFile($tmpDir);
    // Write a PID entry using current process PID (guaranteed to be "running")
    $pidFile->write([
        new ProcessEntry('php', getmypid(), 'php -S localhost:8000', 8000, '2026-02-25T10:00:00+00:00'),
    ]);

    ['command' => $command] = createDevUpCommand(tempDir: $tmpDir);
    ['output' => $output] = createMemoryOutput();

    $input = new Input(['marko', 'dev:up']);
    $command->execute($input, $output);
})->throws(DevServerException::class, 'Development environment is already running');

it('allows dev:up when PID file has only stopped processes', function (): void {
    $tmpDir = sys_get_temp_dir() . '/marko-stopped-' . uniqid();
    mkdir($tmpDir, 0755, true);

    $pidFile = new PidFile($tmpDir);
    // PID 99999 is almost certainly not running
    $pidFile->write([
        new ProcessEntry('php', 99999, 'php -S localhost:8000', 8000, '2026-02-25T10:00:00+00:00'),
    ]);

    ['command' => $command, 'processManager' => $pm] = createDevUpCommand(tempDir: $tmpDir);
    ['output' => $output] = createMemoryOutput();

    $input = new Input(['marko', 'dev:up']);
    $command->execute($input, $output);

    expect($pm->started)->toHaveKey('php');
});

it('suggests marko down in exception when services are already running', function (): void {
    $tmpDir = sys_get_temp_dir() . '/marko-suggest-down-' . uniqid();
    mkdir($tmpDir, 0755, true);

    $pidFile = new PidFile($tmpDir);
    $pidFile->write([
        new ProcessEntry('php', getmypid(), 'php -S localhost:8000', 8000, '2026-02-25T10:00:00+00:00'),
    ]);

    ['command' => $command] = createDevUpCommand(tempDir: $tmpDir);
    ['output' => $output] = createMemoryOutput();

    $input = new Input(['marko', 'dev:up']);

    try {
        $command->execute($input, $output);
        expect(false)->toBeTrue('Expected DevServerException was not thrown');
    } catch (DevServerException $e) {
        expect($e->getSuggestion())->toContain('marko down');
    }
});

it('throws DevServerException when PHP server dies immediately after start in foreground mode', function (): void {
    ['command' => $command, 'processManager' => $pm] = createDevUpCommand([
        'dev.port' => 8000,
        'dev.detach' => false,
    ]);
    // Simulate PHP server dying immediately (port in use)
    $pm->runningOverrides['php'] = false;
    ['output' => $output] = createMemoryOutput();

    $input = new Input(['marko', 'dev:up']);
    $command->execute($input, $output);
})->throws(DevServerException::class, 'Port 8000 is already in use');

it('does not throw when PHP server stays running after start in foreground mode', function (): void {
    ['command' => $command, 'processManager' => $pm] = createDevUpCommand([
        'dev.port' => 8000,
        'dev.detach' => false,
    ]);
    // PHP server stays alive (default behavior)
    $pm->runningOverrides['php'] = true;
    ['output' => $output] = createMemoryOutput();

    $input = new Input(['marko', 'dev:up']);
    $result = $command->execute($input, $output);

    expect($result)->toBe(0);
});
