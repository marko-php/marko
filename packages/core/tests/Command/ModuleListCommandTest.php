<?php

declare(strict_types=1);

use Marko\Core\Attributes\Command;
use Marko\Core\Command\CommandInterface;
use Marko\Core\Command\Input;
use Marko\Core\Command\Output;
use Marko\Core\Commands\ModuleListCommand;
use Marko\Core\Module\ModuleManifest;
use Marko\Core\Module\ModuleRepositoryInterface;

/**
 * Create a simple test implementation of ModuleRepositoryInterface.
 *
 * @param array<ModuleManifest> $modules
 */
function createModuleRepository(
    array $modules,
): ModuleRepositoryInterface {
    return new class ($modules) implements ModuleRepositoryInterface
    {
        public function __construct(
            private array $modules,
        ) {}

        public function all(): array
        {
            return $this->modules;
        }
    };
}

it('has Command attribute with name module:list', function (): void {
    $reflection = new ReflectionClass(ModuleListCommand::class);
    $attributes = $reflection->getAttributes(Command::class);

    expect($attributes)->toHaveCount(1);

    $command = $attributes[0]->newInstance();

    expect($command->name)->toBe('module:list');
});

it('has Command attribute with description Show all modules and their status', function (): void {
    $reflection = new ReflectionClass(ModuleListCommand::class);
    $attributes = $reflection->getAttributes(Command::class);

    $command = $attributes[0]->newInstance();

    expect($command->description)->toBe('Show all modules and their status');
});

it('implements CommandInterface', function (): void {
    $reflection = new ReflectionClass(ModuleListCommand::class);

    expect($reflection->implementsInterface(CommandInterface::class))->toBeTrue();
});

it('outputs all discovered modules', function (): void {
    $modules = [
        new ModuleManifest(name: 'marko/core', version: '1.0.0', source: 'vendor'),
        new ModuleManifest(name: 'marko/routing', version: '1.0.0', source: 'vendor'),
        new ModuleManifest(name: 'app/blog', version: '1.0.0', source: 'app'),
    ];

    $command = new ModuleListCommand(createModuleRepository($modules));

    $stream = fopen('php://memory', 'r+');
    $input = new Input([]);
    $output = new Output($stream);

    $command->execute($input, $output);

    rewind($stream);
    $result = stream_get_contents($stream);

    expect($result)->toContain('marko/core')
        ->and($result)->toContain('marko/routing')
        ->and($result)->toContain('app/blog');
});

it('displays module name for each module', function (): void {
    $modules = [
        new ModuleManifest(name: 'marko/core', version: '1.0.0', source: 'vendor'),
        new ModuleManifest(name: 'custom/module', version: '1.0.0', source: 'modules'),
    ];

    $command = new ModuleListCommand(createModuleRepository($modules));

    $stream = fopen('php://memory', 'r+');
    $input = new Input([]);
    $output = new Output($stream);

    $command->execute($input, $output);

    rewind($stream);
    $result = stream_get_contents($stream);

    expect($result)->toContain('NAME')
        ->and($result)->toContain('marko/core')
        ->and($result)->toContain('custom/module');
});

it('displays module source for each module', function (): void {
    $modules = [
        new ModuleManifest(name: 'marko/core', version: '1.0.0', source: 'vendor'),
        new ModuleManifest(name: 'custom/module', version: '1.0.0', source: 'modules'),
        new ModuleManifest(name: 'app/blog', version: '1.0.0', source: 'app'),
    ];

    $command = new ModuleListCommand(createModuleRepository($modules));

    $stream = fopen('php://memory', 'r+');
    $input = new Input([]);
    $output = new Output($stream);

    $command->execute($input, $output);

    rewind($stream);
    $result = stream_get_contents($stream);

    expect($result)->toContain('SOURCE')
        ->and($result)->toContain('vendor')
        ->and($result)->toContain('modules')
        ->and($result)->toContain('app');
});

it('displays enabled status for each module', function (): void {
    $modules = [
        new ModuleManifest(name: 'marko/core', version: '1.0.0', source: 'vendor', enabled: true),
        new ModuleManifest(name: 'disabled/module', version: '1.0.0', source: 'vendor', enabled: false),
    ];

    $command = new ModuleListCommand(createModuleRepository($modules));

    $stream = fopen('php://memory', 'r+');
    $input = new Input([]);
    $output = new Output($stream);

    $command->execute($input, $output);

    rewind($stream);
    $result = stream_get_contents($stream);

    expect($result)->toContain('ENABLED')
        ->and($result)->toContain('yes')
        ->and($result)->toContain('no');
});

it('returns exit code 0 on success', function (): void {
    $modules = [
        new ModuleManifest(name: 'marko/core', version: '1.0.0', source: 'vendor'),
    ];

    $command = new ModuleListCommand(createModuleRepository($modules));

    $stream = fopen('php://memory', 'r+');
    $input = new Input([]);
    $output = new Output($stream);

    $exitCode = $command->execute($input, $output);

    expect($exitCode)->toBe(0);
});

it('formats output with aligned columns', function (): void {
    $modules = [
        new ModuleManifest(name: 'marko/core', version: '1.0.0', source: 'vendor', enabled: true),
        new ModuleManifest(name: 'marko/routing', version: '1.0.0', source: 'vendor', enabled: true),
        new ModuleManifest(name: 'custom/blog', version: '1.0.0', source: 'app', enabled: false),
    ];

    $command = new ModuleListCommand(createModuleRepository($modules));

    $stream = fopen('php://memory', 'r+');
    $input = new Input([]);
    $output = new Output($stream);

    $command->execute($input, $output);

    rewind($stream);
    $result = stream_get_contents($stream);

    $lines = explode("\n", trim($result));

    // Check header line alignment
    expect($lines[0])->toMatch('/^NAME\s+SOURCE\s+ENABLED$/');

    // Check that data rows are aligned (SOURCE column starts at same position)
    $headerSourcePos = strpos($lines[0], 'SOURCE');
    $row1SourcePos = strpos($lines[1], 'vendor');
    $row2SourcePos = strpos($lines[2], 'vendor');
    $row3SourcePos = strpos($lines[3], 'app');

    expect($row1SourcePos)->toBe($headerSourcePos)
        ->and($row2SourcePos)->toBe($headerSourcePos)
        ->and($row3SourcePos)->toBe($headerSourcePos);
});
