<?php

declare(strict_types=1);

use Marko\Cli\CliKernel;
use Marko\Cli\ProjectFinder;
use Marko\Core\Command\Output;

function createTempProjectDir(): string
{
    $tempDir = sys_get_temp_dir() . '/marko-cli-test-' . uniqid();
    mkdir($tempDir . '/vendor/marko/core', 0755, true);

    return $tempDir;
}

function cleanupTempDir(
    string $dir,
): void {
    if (!is_dir($dir)) {
        return;
    }

    $items = scandir($dir);
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }

        $path = $dir . '/' . $item;
        if (is_link($path)) {
            unlink($path);
        } elseif (is_dir($path)) {
            cleanupTempDir($path);
        } else {
            unlink($path);
        }
    }
    rmdir($dir);
}

// Test double for ProjectFinder
class TestProjectFinder extends ProjectFinder
{
    public ?string $findResult = null;

    public ?string $lastStartPath = null;

    public int $findCallCount = 0;

    public function find(
        ?string $startPath = null,
    ): ?string {
        $this->lastStartPath = $startPath;
        $this->findCallCount++;

        return $this->findResult;
    }
}

// Helper to create a mock application factory with commandRunner
function createMockApplicationFactory(
    ?Closure $onBoot = null,
    ?object $commandRunner = null,
): Closure {
    return function (string $root) use ($onBoot, $commandRunner) {
        $runner = $commandRunner ?? new class ()
        {
            public function run(
                string $name,
                object $input,
                object $output,
            ): int {
                return 0;
            }
        };

        return new class ($onBoot, $runner)
        {
            public function __construct(
                private ?Closure $onBoot,
                public object $commandRunner,
            ) {}

            public function initialize(): void
            {
                if ($this->onBoot !== null) {
                    ($this->onBoot)();
                }
            }
        };
    };
}

it('finds project root using ProjectFinder', function () {
    $projectRoot = createTempProjectDir();
    // Create a minimal autoload.php
    file_put_contents($projectRoot . '/vendor/autoload.php', '<?php return null;');

    try {
        $projectFinder = new TestProjectFinder();
        $projectFinder->findResult = $projectRoot;

        $kernel = new CliKernel(
            projectFinder: $projectFinder,
            applicationFactory: createMockApplicationFactory(),
        );

        $kernel->run(['marko', 'list']);

        expect($projectFinder->findCallCount)->toBe(1);
    } finally {
        cleanupTempDir($projectRoot);
    }
});

it('throws ProjectNotFoundException when not in project', function () {
    $projectFinder = new TestProjectFinder();
    $projectFinder->findResult = null; // Project not found

    // Create output stream to capture error output
    $outputStream = fopen('php://memory', 'r+');
    $output = new Output($outputStream);

    $kernel = new CliKernel(
        projectFinder: $projectFinder,
        output: $output,
    );

    $exitCode = $kernel->run(['marko', 'list']);

    // Check that the error was written to output
    rewind($outputStream);
    $errorOutput = stream_get_contents($outputStream);

    // Exception should be caught and displayed, returning exit code 1
    expect($exitCode)->toBe(1)
        ->and($errorOutput)->toContain('No Marko project found');

    fclose($outputStream);
});

it('loads project autoloader from vendor/autoload.php', function () {
    $projectRoot = createTempProjectDir();
    // Create an autoload.php that sets a global flag
    file_put_contents(
        $projectRoot . '/vendor/autoload.php',
        '<?php $GLOBALS["marko_autoload_test_loaded"] = true; return null;',
    );

    try {
        $projectFinder = new TestProjectFinder();
        $projectFinder->findResult = $projectRoot;

        $kernel = new CliKernel(
            projectFinder: $projectFinder,
            applicationFactory: createMockApplicationFactory(),
        );

        // Reset the flag
        $GLOBALS['marko_autoload_test_loaded'] = false;

        $kernel->run(['marko', 'list']);

        expect($GLOBALS['marko_autoload_test_loaded'])->toBeTrue();
    } finally {
        cleanupTempDir($projectRoot);
        unset($GLOBALS['marko_autoload_test_loaded']);
    }
});

it('boots project Application', function () {
    $projectRoot = createTempProjectDir();
    file_put_contents($projectRoot . '/vendor/autoload.php', '<?php return null;');

    try {
        $projectFinder = new TestProjectFinder();
        $projectFinder->findResult = $projectRoot;

        // Track boot calls
        $bootCalled = false;
        $onBoot = function () use (&$bootCalled) {
            $bootCalled = true;
        };

        $kernel = new CliKernel(
            projectFinder: $projectFinder,
            applicationFactory: createMockApplicationFactory(onBoot: $onBoot),
        );

        $kernel->run(['marko', 'list']);

        expect($bootCalled)->toBeTrue();
    } finally {
        cleanupTempDir($projectRoot);
    }
});

it('delegates command execution to Application commandRunner', function () {
    $projectRoot = createTempProjectDir();
    file_put_contents($projectRoot . '/vendor/autoload.php', '<?php return null;');

    try {
        $projectFinder = new TestProjectFinder();
        $projectFinder->findResult = $projectRoot;

        $commandExecuted = false;
        $executedCommand = null;

        // Create a mock commandRunner
        $mockCommandRunner = new class ($commandExecuted, $executedCommand)
        {
            public function __construct(
                private bool &$commandExecuted,
                private ?string &$executedCommand,
            ) {}

            public function run(
                string $commandName,
                object $input,
                object $output,
            ): int {
                $this->commandExecuted = true;
                $this->executedCommand = $commandName;

                return 0;
            }
        };

        $applicationFactory = fn (string $root) => new class ($mockCommandRunner)
        {
            public function __construct(
                public object $commandRunner,
            ) {}

            public function initialize(): void {}
        };

        $kernel = new CliKernel(
            projectFinder: $projectFinder,
            applicationFactory: $applicationFactory,
        );

        $kernel->run(['marko', 'cache:clear']);

        expect($commandExecuted)->toBeTrue()
            ->and($executedCommand)->toBe('cache:clear');
    } finally {
        cleanupTempDir($projectRoot);
    }
});

it('returns exit code from command execution', function () {
    $projectRoot = createTempProjectDir();
    file_put_contents($projectRoot . '/vendor/autoload.php', '<?php return null;');

    try {
        $projectFinder = new TestProjectFinder();
        $projectFinder->findResult = $projectRoot;

        // Create a commandRunner that returns a specific exit code
        $mockCommandRunner = new class ()
        {
            public function run(
                string $name,
                object $input,
                object $output,
            ): int {
                return 42;
            }
        };

        $kernel = new CliKernel(
            projectFinder: $projectFinder,
            applicationFactory: createMockApplicationFactory(commandRunner: $mockCommandRunner),
        );

        $exitCode = $kernel->run(['marko', 'test']);

        expect($exitCode)->toBe(42);
    } finally {
        cleanupTempDir($projectRoot);
    }
});

it('shows list command output when no command specified', function () {
    $projectRoot = createTempProjectDir();
    file_put_contents($projectRoot . '/vendor/autoload.php', '<?php return null;');

    try {
        $projectFinder = new TestProjectFinder();
        $projectFinder->findResult = $projectRoot;

        // Use a stdClass to track state
        $tracker = new stdClass();
        $tracker->executedCommand = null;

        $mockCommandRunner = new class ($tracker)
        {
            public function __construct(
                private stdClass $tracker,
            ) {}

            public function run(
                string $name,
                object $input,
                object $output,
            ): int {
                $this->tracker->executedCommand = $name;

                return 0;
            }
        };

        $kernel = new CliKernel(
            projectFinder: $projectFinder,
            applicationFactory: createMockApplicationFactory(commandRunner: $mockCommandRunner),
        );

        // Run with just 'marko' (no command)
        $kernel->run(['marko']);

        expect($tracker->executedCommand)->toBe('list');
    } finally {
        cleanupTempDir($projectRoot);
    }
});

it('catches and displays exceptions with helpful messages', function () {
    $projectRoot = createTempProjectDir();
    file_put_contents($projectRoot . '/vendor/autoload.php', '<?php return null;');

    try {
        $projectFinder = new TestProjectFinder();
        $projectFinder->findResult = $projectRoot;

        // Create output stream to capture error output
        $outputStream = fopen('php://memory', 'r+');
        $output = new Output($outputStream);

        // Create a commandRunner that throws an exception
        $mockCommandRunner = new class ()
        {
            public function run(
                string $name,
                object $input,
                object $output,
            ): int {
                throw new RuntimeException('Something went wrong');
            }
        };

        $kernel = new CliKernel(
            projectFinder: $projectFinder,
            applicationFactory: createMockApplicationFactory(commandRunner: $mockCommandRunner),
            output: $output,
        );

        $exitCode = $kernel->run(['marko', 'test']);

        // Check that the error was written to output
        rewind($outputStream);
        $errorOutput = stream_get_contents($outputStream);

        // Check exit code is non-zero and error message was written
        expect($exitCode)->toBe(1)
            ->and($errorOutput)->toContain('Something went wrong');

        fclose($outputStream);
    } finally {
        cleanupTempDir($projectRoot);
    }
});
