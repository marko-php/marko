<?php

declare(strict_types=1);

use Marko\Core\Attributes\Command;
use Marko\Core\Command\Input;
use Marko\Core\Command\Output;
use Marko\DevServer\Command\DevDownCommand;
use Marko\DevServer\Detection\DockerDetector;
use Marko\DevServer\Process\PidFile;
use Marko\DevServer\Process\ProcessEntry;
use Marko\Testing\Fake\FakeConfigRepository;

function devDownTmpDir(): string
{
    $tmpDir = sys_get_temp_dir() . '/dev-down-test-' . uniqid();
    mkdir($tmpDir, 0755, true);

    return $tmpDir;
}

function devDownRemoveDir(string $dir): void
{
    if (!is_dir($dir)) {
        return;
    }
    foreach (scandir($dir) as $file) {
        if ($file === '.' || $file === '..') {
            continue;
        }
        $path = $dir . '/' . $file;
        is_dir($path) ? devDownRemoveDir($path) : unlink($path);
    }
    rmdir($dir);
}

/**
 * @param array<string, mixed> $config
 * @return array{command: DevDownCommand, pidFile: PidFile, tmpDir: string}
 */
function createDevDownCommand(array $config = [], ?string $tmpDir = null): array
{
    $dir = $tmpDir ?? devDownTmpDir();

    $configDefaults = [
        'dev.docker' => false,
    ];
    $fakeConfig = new FakeConfigRepository(array_merge($configDefaults, $config));

    $dockerDetector = new DockerDetector($dir);
    $pidFile = new PidFile($dir);

    $command = new DevDownCommand(
        config: $fakeConfig,
        dockerDetector: $dockerDetector,
        pidFile: $pidFile,
    );

    return ['command' => $command, 'pidFile' => $pidFile, 'tmpDir' => $dir];
}

it('has Command attribute with name dev:down and alias down', function (): void {
    $reflection = new ReflectionClass(DevDownCommand::class);
    $attributes = $reflection->getAttributes(Command::class);

    expect($attributes)->toHaveCount(1);

    $attr = $attributes[0]->newInstance();

    expect($attr->name)->toBe('dev:down')
        ->and($attr->aliases)->toContain('down');
});

it('reads process list from PID file', function (): void {
    ['command' => $command, 'pidFile' => $pidFile, 'tmpDir' => $tmpDir] = createDevDownCommand();
    $pidFile->write([
        new ProcessEntry('php', 99999, 'php -S localhost:8000 -t public/', 8000, date('c')),
    ]);

    $stream = fopen('php://memory', 'r+');
    $output = new Output($stream);

    $command->execute(new Input([]), $output);

    rewind($stream);
    $content = stream_get_contents($stream);

    expect($content)->toContain('php');

    devDownRemoveDir($tmpDir);
});

it('stops processes recorded in PID file', function (): void {
    ['command' => $command, 'pidFile' => $pidFile, 'tmpDir' => $tmpDir] = createDevDownCommand();

    // Start a real short-lived process and record its PID
    $proc = proc_open('sleep 60', [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    $status = proc_get_status($proc);
    $pid = $status['pid'];

    $pidFile->write([
        new ProcessEntry('sleep', $pid, 'sleep 60', 0, date('c')),
    ]);

    $stream = fopen('php://memory', 'r+');
    $output = new Output($stream);
    $command->execute(new Input([]), $output);

    rewind($stream);
    $content = stream_get_contents($stream);

    expect($content)->toContain('Stopping sleep');

    proc_close($proc);
    devDownRemoveDir($tmpDir);
});

it('cleans up PID file after stopping', function (): void {
    ['command' => $command, 'pidFile' => $pidFile, 'tmpDir' => $tmpDir] = createDevDownCommand();
    $pidFile->write([
        new ProcessEntry('php', 99999, 'php -S localhost:8000 -t public/', 8000, date('c')),
    ]);

    $stream = fopen('php://memory', 'r+');
    $output = new Output($stream);
    $command->execute(new Input([]), $output);

    expect($pidFile->read())->toBeEmpty();

    devDownRemoveDir($tmpDir);
});

it('outputs message when no services are running and no docker configured', function (): void {
    ['command' => $command, 'tmpDir' => $tmpDir] = createDevDownCommand();

    $stream = fopen('php://memory', 'r+');
    $output = new Output($stream);
    $command->execute(new Input([]), $output);

    rewind($stream);
    $content = stream_get_contents($stream);

    expect($content)->toContain('No development services running.');

    devDownRemoveDir($tmpDir);
});

it('handles already-dead processes gracefully', function (): void {
    ['command' => $command, 'pidFile' => $pidFile, 'tmpDir' => $tmpDir] = createDevDownCommand();
    $pidFile->write([
        new ProcessEntry('php', 99999, 'php -S localhost:8000 -t public/', 8000, date('c')),
    ]);

    $stream = fopen('php://memory', 'r+');
    $output = new Output($stream);

    // Should not throw
    $exitCode = $command->execute(new Input([]), $output);

    rewind($stream);
    $content = stream_get_contents($stream);

    expect($exitCode)->toBe(0)
        ->and($content)->toContain('already stopped');

    devDownRemoveDir($tmpDir);
});

it('runs docker compose down using detector when compose file exists', function (): void {
    $tmpDir = devDownTmpDir();
    file_put_contents($tmpDir . '/compose.yaml', "version: '3'\nservices:\n  app:\n    image: nginx\n");

    ['command' => $command, 'pidFile' => $pidFile] = createDevDownCommand(
        config: ['dev.docker' => true],
        tmpDir: $tmpDir,
    );
    $pidFile->write([
        new ProcessEntry('docker', 0, 'docker compose -f compose.yaml up -d', 0, date('c')),
    ]);

    $stream = fopen('php://memory', 'r+');
    $output = new Output($stream);
    $command->execute(new Input([]), $output);

    rewind($stream);
    $content = stream_get_contents($stream);

    expect($content)->toContain('Stopping Docker: docker compose -f compose.yaml down');

    devDownRemoveDir($tmpDir);
});

it('falls back to stored command when detector finds nothing', function (): void {
    ['command' => $command, 'pidFile' => $pidFile, 'tmpDir' => $tmpDir] = createDevDownCommand(
        config: ['dev.docker' => true],
    );
    // No compose file in tmpDir — detector returns null
    $pidFile->write([
        new ProcessEntry('docker', 0, 'docker compose -f compose.yaml up -d', 0, date('c')),
    ]);

    $stream = fopen('php://memory', 'r+');
    $output = new Output($stream);
    $command->execute(new Input([]), $output);

    rewind($stream);
    $content = stream_get_contents($stream);

    expect($content)->toContain('Stopping Docker: docker compose -f compose.yaml down');

    devDownRemoveDir($tmpDir);
});

it('uses config string to derive docker down command', function (): void {
    ['command' => $command, 'pidFile' => $pidFile, 'tmpDir' => $tmpDir] = createDevDownCommand(
        config: ['dev.docker' => 'docker compose -f docker-compose.dev.yaml up'],
    );
    $pidFile->write([
        new ProcessEntry('docker', 0, 'docker compose -f docker-compose.dev.yaml up -d', 0, date('c')),
    ]);

    $stream = fopen('php://memory', 'r+');
    $output = new Output($stream);
    $command->execute(new Input([]), $output);

    rewind($stream);
    $content = stream_get_contents($stream);

    expect($content)->toContain('Stopping Docker: docker compose -f docker-compose.dev.yaml down');

    devDownRemoveDir($tmpDir);
});

it('stops docker via config detection even without PID file', function (): void {
    $tmpDir = devDownTmpDir();
    file_put_contents($tmpDir . '/compose.yaml', "version: '3'\nservices:\n  app:\n    image: nginx\n");

    ['command' => $command] = createDevDownCommand(
        config: ['dev.docker' => true],
        tmpDir: $tmpDir,
    );
    // No PID file written — simulates foreground mode or lost state

    $stream = fopen('php://memory', 'r+');
    $output = new Output($stream);
    $command->execute(new Input([]), $output);

    rewind($stream);
    $content = stream_get_contents($stream);

    expect($content)->toContain('Stopping Docker: docker compose -f compose.yaml down')
        ->and($content)->not->toContain('No development services running.');

    devDownRemoveDir($tmpDir);
});

it('stops docker via config even when PID file has no docker entry', function (): void {
    $tmpDir = devDownTmpDir();
    file_put_contents($tmpDir . '/compose.yaml', "version: '3'\nservices:\n  app:\n    image: nginx\n");

    ['command' => $command, 'pidFile' => $pidFile] = createDevDownCommand(
        config: ['dev.docker' => true],
        tmpDir: $tmpDir,
    );
    // PID file has only PHP, not docker
    $pidFile->write([
        new ProcessEntry('php', 99999, 'php -S localhost:8000 -t public/', 8000, date('c')),
    ]);

    $stream = fopen('php://memory', 'r+');
    $output = new Output($stream);
    $command->execute(new Input([]), $output);

    rewind($stream);
    $content = stream_get_contents($stream);

    expect($content)->toContain('Stopping Docker: docker compose -f compose.yaml down');

    devDownRemoveDir($tmpDir);
});

it('does not stop docker when config is false', function (): void {
    ['command' => $command, 'pidFile' => $pidFile, 'tmpDir' => $tmpDir] = createDevDownCommand(
        config: ['dev.docker' => false],
    );
    $pidFile->write([
        new ProcessEntry('php', 99999, 'php -S localhost:8000 -t public/', 8000, date('c')),
    ]);

    $stream = fopen('php://memory', 'r+');
    $output = new Output($stream);
    $command->execute(new Input([]), $output);

    rewind($stream);
    $content = stream_get_contents($stream);

    expect($content)->not->toContain('Docker');

    devDownRemoveDir($tmpDir);
});
