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

    /** Whether isPortAvailable() reports the port as free */
    public bool $portAvailable = true;

    /** Whether waitUntilAccepting() reports the server as accepting connections (false: it exited first) */
    public bool $acceptsConnections = true;

    /** Whether isPortAvailable() reports the port as free once the PHP server exited (null: same as $portAvailable) */
    public ?bool $portAvailableAfterExit = null;

    /** @var list<array{0: string, 1: int}> Calls to isPortAvailable() as [host, port] */
    public array $portChecks = [];

    /** @var list<array{0: string, 1: string, 2: int}> Calls to waitUntilAccepting() as [name, host, port] */
    public array $waitedFor = [];

    /** The process whose start fails with processFailedToStart, or null */
    public ?string $failOn = null;

    /** What getExitCode() and collectOutput() report for the PHP server */
    public ?int $exitCode = null;

    public string $output = '';

    /** @var array<string, int> Processes stopAll() fails to stop, as name => PID */
    public array $survivors = [];

    public int $stopAllCalls = 0;

    /** @noinspection PhpMissingParentConstructorInspection - Test stub intentionally skips parent */
    public function __construct() {}

    public function start(
        string $name,
        string $command,
    ): int {
        return $this->record($name, $command);
    }

    private function record(
        string $name,
        string $command,
    ): int {
        if ($name === $this->failOn) {
            throw DevServerException::processFailedToStart($name, $command);
        }

        $this->started[$name] = $command;

        return 12345;
    }

    public function isPortAvailable(
        string $host,
        int $port,
    ): bool {
        $this->portChecks[] = [$host, $port];

        return (count($this->portChecks) > 1 && $this->portAvailableAfterExit !== null)
            ? $this->portAvailableAfterExit
            : $this->portAvailable;
    }

    public function stopAll(): void
    {
        $this->stopAllCalls++;

        if ($this->survivors !== []) {
            throw DevServerException::processFailedToStop(
                (string) array_key_first($this->survivors),
                reset($this->survivors),
            );
        }
    }

    public function getPids(): array
    {
        return $this->survivors;
    }

    public function getExitCode(
        string $name,
    ): ?int {
        return $this->exitCode;
    }

    public function collectOutput(
        string $name,
    ): string {
        return $this->output;
    }

    public function waitUntilAccepting(
        string $name,
        string $host,
        int $port,
    ): bool {
        $this->waitedFor[] = [$name, $host, $port];

        return $this->acceptsConnections;
    }

    public function startDetached(
        string $name,
        string $command,
    ): int {
        return $this->record($name, $command);
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
function readStream(
    mixed $stream,
): string {
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

it('declares its boolean flags on the Command attribute', function (): void {
    $attribute = new ReflectionClass(DevUpCommand::class)->getAttributes(Command::class)[0]->newInstance();

    expect($attribute->flags)->toBe(['foreground', 'f', 'detach', 'd']);
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

it('overrides config host with --host flag', function (): void {
    ['command' => $command, 'processManager' => $pm] = createDevUpCommand(['dev.host' => 'localhost']);
    ['output' => $output] = createMemoryOutput();

    $input = new Input(['marko', 'dev:up', '--host=0.0.0.0']);
    $command->execute($input, $output);

    expect($pm->started['php'])->toContain('0.0.0.0:8000')
        ->and($pm->started['php'])->not->toContain('localhost');
});

it('rejects invalid host values', function (string $host): void {
    ['command' => $command, 'processManager' => $pm] = createDevUpCommand(['dev.host' => 'localhost']);
    ['output' => $output] = createMemoryOutput();

    expect(fn () => $command->execute(new Input(['marko', 'dev:up', "--host=$host"]), $output))
        ->toThrow(DevServerException::class, "Invalid host value: '$host'")
        ->and($pm->started)->toBe([]);
})->with(['0.0.0.0; evil', '[localhost]', '::1::2', 'localhost:8000']);

it('brackets a bare or bracketed IPv6 host in the PHP server command', function (string $host): void {
    ['command' => $command, 'processManager' => $pm] = createDevUpCommand();
    ['stream' => $stream, 'output' => $output] = createMemoryOutput();

    $command->execute(new Input(['marko', 'dev:up', "--host=$host"]), $output);

    expect($pm->started['php'])->toBe('env PHP_CLI_SERVER_WORKERS=4 php -S [::1]:8000 -t public/')
        ->and(readStream($stream))->toContain('Starting PHP server: php -S [::1]:8000');
})->with(['::1', '[::1]']);

it('passes the unbracketed IPv6 host to the port check and the readiness wait', function (): void {
    ['command' => $command, 'processManager' => $pm] = createDevUpCommand(['dev.detach' => false]);
    ['output' => $output] = createMemoryOutput();

    $command->execute(new Input(['marko', 'dev:up', '--host=[::1]']), $output);

    expect($pm->portChecks)->toBe([['::1', 8000]])
        ->and($pm->waitedFor)->toBe([['php', '::1', 8000]]);
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
    $names = array_map(fn ($e) => $e->name, $entries);

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
    $pubsubDetector = new class () extends PubSubDetector
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
    $pubsubDetector = new class () extends PubSubDetector
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

it(
    'reports portInUse when the PHP server exits because the port was taken',
    function (): void {
        ['command' => $command, 'processManager' => $pm] = createDevUpCommand([
            'dev.port' => 8000,
            'dev.detach' => false,
        ]);
        // The PHP server exits before it accepts a connection: it lost the bind to another process
        $pm->acceptsConnections = false;
        $pm->portAvailableAfterExit = false;
        ['output' => $output] = createMemoryOutput();

        $input = new Input(['marko', 'dev:up']);
        $command->execute($input, $output);
    },
)->throws(DevServerException::class, 'Port 8000 is already in use');

it('waits for the PHP server to accept connections in foreground mode', function (): void {
    ['command' => $command, 'processManager' => $pm] = createDevUpCommand([
        'dev.port' => 8123,
        'dev.host' => '127.0.0.1',
        'dev.detach' => false,
    ]);
    ['output' => $output] = createMemoryOutput();

    $result = $command->execute(new Input(['marko', 'dev:up']), $output);

    expect($result)->toBe(0)
        ->and($pm->waitedFor)->toBe([['php', '127.0.0.1', 8123]])
        ->and($pm->foregroundCalled)->toBeTrue();
});

it('does not wait for connections in detached mode', function (): void {
    ['command' => $command, 'processManager' => $pm] = createDevUpCommand(['dev.detach' => true]);
    ['output' => $output] = createMemoryOutput();

    $command->execute(new Input(['marko', 'dev:up']), $output);

    expect($pm->waitedFor)->toBe([])
        ->and($pm->started)->toHaveKey('php');
});

it('throws portInUse before starting any process when the port is occupied', function (bool $detach): void {
    ['command' => $command, 'processManager' => $pm] = createDevUpCommand([
        'dev.port' => 8000,
        'dev.detach' => $detach,
        'dev.processes' => ['worker' => 'php worker.php'],
    ]);
    $pm->portAvailable = false;
    ['output' => $output] = createMemoryOutput();

    expect(fn () => $command->execute(new Input(['marko', 'dev:up']), $output))
        ->toThrow(DevServerException::class, 'Port 8000 is already in use')
        ->and($pm->started)->toBe([]);
})->with(['foreground' => [false], 'detached' => [true]]);

it('throws portInUse for an occupied port with the real process manager', function (): void {
    $dir = sys_get_temp_dir() . '/marko-test-' . uniqid();
    mkdir($dir . '/public', 0755, true);
    file_put_contents($dir . '/public/index.php', '<?php');

    $server = stream_socket_server('tcp://127.0.0.1:0');
    $address = (string) stream_socket_get_name($server, false);
    $port = (int) substr($address, (int) strrpos($address, ':') + 1);
    ['output' => $output] = createMemoryOutput();

    $command = new DevUpCommand(
        config: new FakeConfigRepository(array_merge(CONFIG_DEFAULTS, [
            'dev.host' => '127.0.0.1',
            'dev.port' => $port,
            'dev.detach' => false,
        ])),
        dockerDetector: new DockerDetector($dir),
        frontendDetector: new FrontendDetector($dir),
        pubsubDetector: new PubSubDetector(),
        pidFile: new PidFile($dir),
        processManager: new ProcessManager($output),
        paths: new ProjectPaths($dir),
    );

    try {
        expect(fn () => $command->execute(new Input(['marko', 'dev:up']), $output))
            ->toThrow(DevServerException::class, "Port $port is already in use");
    } finally {
        fclose($server);
        unlink($dir . '/public/index.php');
        rmdir($dir . '/public');
        rmdir($dir);
    }
});

it(
    'stops the services it started and rethrows the original error when a service fails',
    function (bool $detach): void {
        ['command' => $command, 'processManager' => $pm] = createDevUpCommand([
            'dev.detach' => $detach,
            'dev.frontend' => 'yarn dev',
            'dev.processes' => ['queue' => 'php marko queue:work'],
        ]);
        $pm->failOn = 'queue';
        ['stream' => $stream, 'output' => $output] = createMemoryOutput();

        expect(fn () => $command->execute(new Input(['marko', 'dev:up']), $output))
            ->toThrow(DevServerException::class, "Failed to start process 'queue'")
            ->and($pm->stopAllCalls)->toBe(1)
            ->and($pm->started)->not->toHaveKey('php')
            ->and(readStream($stream))->toContain('Stopping the services that already started');
    },
)->with(['foreground' => [false], 'detached' => [true]]);

it('does not stop anything when it fails before starting a service', function (): void {
    ['command' => $command, 'processManager' => $pm] = createDevUpCommand();
    $pm->portAvailable = false;
    ['output' => $output] = createMemoryOutput();

    expect(fn () => $command->execute(new Input(['marko', 'dev:up']), $output))
        ->toThrow(DevServerException::class, 'Port 8000 is already in use')
        ->and($pm->stopAllCalls)->toBe(0);
});

it('throws a DevServerException naming the survivors when the rollback fails', function (): void {
    ['command' => $command, 'processManager' => $pm] = createDevUpCommand(['dev.frontend' => 'yarn dev']);
    $pm->failOn = 'php';
    $pm->survivors = ['frontend' => 4321];
    ['output' => $output] = createMemoryOutput();

    try {
        $command->execute(new Input(['marko', 'dev:up']), $output);
        expect(false)->toBeTrue('Expected DevServerException was not thrown');
    } catch (DevServerException $e) {
        expect($e->getMessage())->toContain("'frontend' (PID 4321)")
            ->and($e->getPrevious())->toBeInstanceOf(DevServerException::class)
            ->and($e->getPrevious()?->getMessage())->toContain("Failed to start process 'php'");
    }
});

it('removes a stale PID file when the start fails', function (bool $detach): void {
    $dir = sys_get_temp_dir() . '/marko-stale-' . uniqid();
    mkdir($dir, 0755, true);
    $pidFile = new PidFile($dir);
    $pidFile->write([new ProcessEntry('php', 99999, 'php -S localhost:8000', 8000, '2026-02-25T10:00:00+00:00')]);

    ['command' => $command, 'processManager' => $pm] = createDevUpCommand(['dev.detach' => $detach], $dir);
    $pm->failOn = 'php';
    ['output' => $output] = createMemoryOutput();

    expect(fn () => $command->execute(new Input(['marko', 'dev:up']), $output))
        ->toThrow(DevServerException::class)
        ->and($pidFile->read())->toBe([]);
})->with(['foreground' => [false], 'detached' => [true]]);

it('reports a PHP server that exits before accepting connections with its exit code and output', function (): void {
    ['command' => $command, 'processManager' => $pm] = createDevUpCommand(['dev.detach' => false]);
    $pm->acceptsConnections = false;
    $pm->exitCode = 255;
    $pm->output = 'PHP Parse error: syntax error in public/router.php';
    ['output' => $output] = createMemoryOutput();

    try {
        $command->execute(new Input(['marko', 'dev:up']), $output);
        expect(false)->toBeTrue('Expected DevServerException was not thrown');
    } catch (DevServerException $e) {
        expect($e->getMessage())
            ->toBe('PHP server exited with code 255 before accepting connections on localhost:8000')
            ->and($e->getContext())->toContain('PHP Parse error')
            ->and($pm->stopAllCalls)->toBe(1);
    }
});

/**
 * A ProcessManager that remembers the PID of every process it started, so tests can check they are gone.
 */
class RecordingProcessManager extends ProcessManager
{
    /** @var array<string, int> */
    public array $startedPids = [];

    public function start(
        string $name,
        string $command,
    ): int {
        return $this->startedPids[$name] = parent::start($name, $command);
    }

    public function startDetached(
        string $name,
        string $command,
    ): int {
        return $this->startedPids[$name] = parent::startDetached($name, $command);
    }

    /**
     * These tests expect startup to fail; reaching the foreground loop would block until the services exit.
     */
    public function runForeground(): void
    {
        $this->stopAll();

        throw new RuntimeException('dev:up reached runForeground(): startup was expected to fail');
    }
}

/**
 * Build a DevUpCommand with the real process manager in a fresh project directory.
 *
 * @param array<string, mixed> $config
 * @param float $startProbeSeconds How long each start is watched for an early exit
 * @return array{command: DevUpCommand, processManager: RecordingProcessManager, pidFile: PidFile, dir: string, output: Output}
 */
function createRealDevUpCommand(
    array $config,
    float $startProbeSeconds = 0.2,
): array {
    $dir = sys_get_temp_dir() . '/marko-real-' . bin2hex(random_bytes(6));
    mkdir($dir . '/public', 0755, true);
    file_put_contents($dir . '/public/index.php', '<?php echo "marko-ok";');

    ['output' => $output] = createMemoryOutput();
    $processManager = new RecordingProcessManager($output, startProbeSeconds: $startProbeSeconds);
    $pidFile = new PidFile($dir);

    $command = new DevUpCommand(
        config: new FakeConfigRepository(array_merge(CONFIG_DEFAULTS, $config)),
        dockerDetector: new DockerDetector($dir),
        frontendDetector: new FrontendDetector($dir),
        pubsubDetector: new PubSubDetector(),
        pidFile: $pidFile,
        processManager: $processManager,
        paths: new ProjectPaths($dir),
    );

    return [
        'command' => $command,
        'processManager' => $processManager,
        'pidFile' => $pidFile,
        'dir' => $dir,
        'output' => $output,
    ];
}

/**
 * Run a command from the project directory, as `marko up` does, so `php -S -t public/` finds the docroot.
 */
function runInProject(
    string $dir,
    callable $callback,
): void {
    $cwd = (string) getcwd();
    chdir($dir);

    try {
        $callback();
    } finally {
        chdir($cwd);
    }
}

it(
    'stops services already started when a later service fails to start with the real process manager',
    function (bool $detach): void {
        [
            'command' => $command,
            'processManager' => $pm,
            'pidFile' => $pidFile,
            'dir' => $dir,
            'output' => $output,
        ] = createRealDevUpCommand([
            'dev.host' => '127.0.0.1',
            'dev.port' => devserverFreePort(),
            'dev.detach' => $detach,
            'dev.processes' => [
                'first' => 'sleep 30',
                'second' => 'sleep 30',
                // A missing executable fails before anything is spawned, however slow the machine is
                'broken' => 'nonexistent-binary-abc123 --watch',
            ],
        ]);

        try {
            runInProject($dir, function () use ($command, $output): void {
                expect(fn () => $command->execute(new Input(['marko', 'dev:up']), $output))
                    ->toThrow(DevServerException::class, "Failed to start process 'broken'");
            });

            expect($pm->startedPids)->toHaveKeys(['first', 'second'])
                ->and(devserverProcessGroupAlive($pm->startedPids['first']))->toBeFalse()
                ->and(devserverProcessGroupAlive($pm->startedPids['second']))->toBeFalse()
                ->and($pm->getPids())->toBe([])
                ->and($pidFile->read())->toBe([]);
        } finally {
            array_map(devserverStopDetached(...), $pm->startedPids);
        }
    },
)->with(['foreground' => [false], 'detached' => [true]]);

it(
    'stops services already started when the PHP server fails to start with the real process manager',
    function (bool $detach): void {
        // Detached mode only notices an exit inside the probe window, so give a slow machine time;
        // the failing server ends the window early
        [
            'command' => $command,
            'processManager' => $pm,
            'pidFile' => $pidFile,
            'dir' => $dir,
            'output' => $output,
        ] = createRealDevUpCommand([
            // TEST-NET-1 (RFC 5737) is never a local address, so the PHP server cannot bind it and exits
            'dev.host' => '192.0.2.1',
            'dev.port' => devserverFreePort(),
            'dev.detach' => $detach,
            'dev.processes' => ['worker' => 'sleep 30'],
        ], startProbeSeconds: $detach ? 5.0 : 0.2);

        try {
            runInProject($dir, function () use ($command, $output, $detach): void {
                expect(fn () => $command->execute(new Input(['marko', 'dev:up']), $output))
                    ->toThrow(
                        DevServerException::class,
                        $detach ? "Failed to start process 'php'" : 'PHP server exited with code 1 before accepting connections on 192.0.2.1',
                    );
            });

            expect(devserverProcessGroupAlive($pm->startedPids['worker']))->toBeFalse()
                ->and($pm->getPids())->toBe([])
                ->and($pidFile->read())->toBe([]);
        } finally {
            array_map(devserverStopDetached(...), $pm->startedPids);
        }
    },
)->with(['foreground' => [false], 'detached' => [true]]);

it('includes the PHP server output when it exits before accepting connections', function (): void {
    [
        'command' => $command,
        'processManager' => $pm,
        'dir' => $dir,
        'output' => $output,
    ] = createRealDevUpCommand([
        'dev.host' => '192.0.2.1',
        'dev.port' => devserverFreePort(),
        'dev.detach' => false,
    ]);

    try {
        runInProject($dir, function () use ($command, $output): void {
            try {
                $command->execute(new Input(['marko', 'dev:up']), $output);
                expect(false)->toBeTrue('Expected DevServerException was not thrown');
            } catch (DevServerException $e) {
                expect($e->getContext())->toContain('Failed to listen on 192.0.2.1');
            }
        });
    } finally {
        array_map(devserverStopDetached(...), $pm->startedPids);
    }
});

it('serves requests on the IPv6 loopback for a bare or bracketed host', function (string $host): void {
    if (!devserverHasIpv6Loopback()) {
        $this->markTestSkipped('This machine has no IPv6 loopback address');
    }

    $port = devserverFreePort('[::1]');
    [
        'command' => $command,
        'processManager' => $pm,
        'pidFile' => $pidFile,
        'dir' => $dir,
        'output' => $output,
    ] = createRealDevUpCommand(['dev.port' => $port, 'dev.detach' => true]);

    try {
        runInProject($dir, function () use ($command, $output, $host): void {
            expect($command->execute(new Input(['marko', 'dev:up', "--host=$host"]), $output))->toBe(0);
        });

        $body = null;
        $served = devserverWaitUntil(function () use ($port, &$body): bool {
            $body = devserverHttpGet("http://[::1]:$port/");

            return $body !== false;
        }, timeoutSeconds: 15.0);

        expect($pidFile->read()[0]->command)->toContain("php -S [::1]:$port")
            ->and($pidFile->read()[0]->host)->toBe('::1')
            ->and($served)->toBeTrue()
            ->and($body)->toBe('marko-ok');
    } finally {
        array_map(devserverStopDetached(...), $pm->startedPids);
    }
})->with(['::1', '[::1]']);

it('binds all IPv6 interfaces for the :: host', function (): void {
    if (!devserverHasIpv6Loopback()) {
        $this->markTestSkipped('This machine has no IPv6 loopback address');
    }

    $port = devserverFreePort('[::1]');
    [
        'command' => $command,
        'processManager' => $pm,
        'pidFile' => $pidFile,
        'dir' => $dir,
        'output' => $output,
    ] = createRealDevUpCommand(['dev.port' => $port, 'dev.detach' => true]);

    try {
        runInProject($dir, function () use ($command, $output): void {
            expect($command->execute(new Input(['marko', 'dev:up', '--host=::']), $output))->toBe(0);
        });

        $body = null;
        $served = devserverWaitUntil(function () use ($port, &$body): bool {
            $body = devserverHttpGet("http://[::1]:$port/");

            return $body !== false;
        }, timeoutSeconds: 15.0);

        expect($pidFile->read()[0]->command)->toContain("php -S [::]:$port")
            ->and($served)->toBeTrue()
            ->and($body)->toBe('marko-ok');
    } finally {
        array_map(devserverStopDetached(...), $pm->startedPids);
    }
});
