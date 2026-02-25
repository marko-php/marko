<?php

declare(strict_types=1);

use Marko\Core\Command\CommandDefinition;
use Marko\Core\Command\CommandDiscovery;
use Marko\Core\Discovery\ClassFileParser;
use Marko\Core\Exceptions\CommandException;
use Marko\Core\Module\ModuleManifest;

it('discovers command classes in module src directories', function (): void {
    // Create a temp directory structure with command files
    $tempDir = sys_get_temp_dir() . '/marko_test_' . uniqid();
    mkdir($tempDir . '/src', 0755, true);

    // Create a command class file with Command attribute
    $commandCode = <<<'PHP'
<?php

declare(strict_types=1);

namespace TestCommandModule;

use Marko\Core\Attributes\Command;
use Marko\Core\Command\CommandInterface;
use Marko\Core\Command\Input;
use Marko\Core\Command\Output;

#[Command(name: 'test:command', description: 'A test command')]
class TestCommand implements CommandInterface
{
    public function execute(
        Input $input,
        Output $output,
    ): int {
        return 0;
    }
}
PHP;
    file_put_contents($tempDir . '/src/TestCommand.php', $commandCode);

    // Create module manifest
    $manifest = new ModuleManifest(
        name: 'test/module',
        version: '1.0.0',
        path: $tempDir,
    );

    $discovery = new CommandDiscovery(new ClassFileParser());
    $commands = $discovery->discover([$manifest]);

    expect($commands)->toHaveCount(1)
        ->and($commands[0]->commandClass)->toBe('TestCommandModule\\TestCommand');

    // Cleanup
    unlink($tempDir . '/src/TestCommand.php');
    rmdir($tempDir . '/src');
    rmdir($tempDir);
});

it('ignores classes without Command attribute', function (): void {
    // Create a temp directory structure
    $tempDir = sys_get_temp_dir() . '/marko_test_' . uniqid();
    mkdir($tempDir . '/src', 0755, true);

    // Create a class without Command attribute
    $classCode = <<<'PHP'
<?php

declare(strict_types=1);

namespace TestCommandModule2;

class NotACommand
{
    public function doSomething(): void {}
}
PHP;
    file_put_contents($tempDir . '/src/NotACommand.php', $classCode);

    $manifest = new ModuleManifest(
        name: 'test/module',
        version: '1.0.0',
        path: $tempDir,
    );

    $discovery = new CommandDiscovery(new ClassFileParser());
    $commands = $discovery->discover([$manifest]);

    expect($commands)->toBeEmpty();

    // Cleanup
    unlink($tempDir . '/src/NotACommand.php');
    rmdir($tempDir . '/src');
    rmdir($tempDir);
});

it('ignores directories without src folder', function (): void {
    // Create a temp directory without src folder
    $tempDir = sys_get_temp_dir() . '/marko_test_' . uniqid();
    mkdir($tempDir, 0755, true);

    $manifest = new ModuleManifest(
        name: 'test/module',
        version: '1.0.0',
        path: $tempDir,
    );

    $discovery = new CommandDiscovery(new ClassFileParser());
    $commands = $discovery->discover([$manifest]);

    expect($commands)->toBeEmpty();

    // Cleanup
    rmdir($tempDir);
});

it('returns array of CommandDefinition objects', function (): void {
    // Create a temp directory structure with command files
    $tempDir = sys_get_temp_dir() . '/marko_test_' . uniqid();
    mkdir($tempDir . '/src', 0755, true);

    $commandCode = <<<'PHP'
<?php

declare(strict_types=1);

namespace TestCommandModule4;

use Marko\Core\Attributes\Command;
use Marko\Core\Command\CommandInterface;
use Marko\Core\Command\Input;
use Marko\Core\Command\Output;

#[Command(name: 'test:definition', description: 'Tests definition')]
class DefinitionCommand implements CommandInterface
{
    public function execute(
        Input $input,
        Output $output,
    ): int {
        return 0;
    }
}
PHP;
    file_put_contents($tempDir . '/src/DefinitionCommand.php', $commandCode);

    $manifest = new ModuleManifest(
        name: 'test/module',
        version: '1.0.0',
        path: $tempDir,
    );

    $discovery = new CommandDiscovery(new ClassFileParser());
    $commands = $discovery->discover([$manifest]);

    expect($commands)->toHaveCount(1)
        ->and($commands[0])->toBeInstanceOf(CommandDefinition::class);

    // Cleanup
    unlink($tempDir . '/src/DefinitionCommand.php');
    rmdir($tempDir . '/src');
    rmdir($tempDir);
});

it('extracts command name from attribute', function (): void {
    $tempDir = sys_get_temp_dir() . '/marko_test_' . uniqid();
    mkdir($tempDir . '/src', 0755, true);

    $commandCode = <<<'PHP'
<?php

declare(strict_types=1);

namespace TestCommandModule5;

use Marko\Core\Attributes\Command;
use Marko\Core\Command\CommandInterface;
use Marko\Core\Command\Input;
use Marko\Core\Command\Output;

#[Command(name: 'app:greet', description: 'Greet someone')]
class GreetCommand implements CommandInterface
{
    public function execute(
        Input $input,
        Output $output,
    ): int {
        return 0;
    }
}
PHP;
    file_put_contents($tempDir . '/src/GreetCommand.php', $commandCode);

    $manifest = new ModuleManifest(
        name: 'test/module',
        version: '1.0.0',
        path: $tempDir,
    );

    $discovery = new CommandDiscovery(new ClassFileParser());
    $commands = $discovery->discover([$manifest]);

    expect($commands)->toHaveCount(1)
        ->and($commands[0]->name)->toBe('app:greet');

    // Cleanup
    unlink($tempDir . '/src/GreetCommand.php');
    rmdir($tempDir . '/src');
    rmdir($tempDir);
});

it('extracts description from attribute', function (): void {
    $tempDir = sys_get_temp_dir() . '/marko_test_' . uniqid();
    mkdir($tempDir . '/src', 0755, true);

    $commandCode = <<<'PHP'
<?php

declare(strict_types=1);

namespace TestCommandModule6;

use Marko\Core\Attributes\Command;
use Marko\Core\Command\CommandInterface;
use Marko\Core\Command\Input;
use Marko\Core\Command\Output;

#[Command(name: 'app:describe', description: 'This is a detailed description')]
class DescribeCommand implements CommandInterface
{
    public function execute(
        Input $input,
        Output $output,
    ): int {
        return 0;
    }
}
PHP;
    file_put_contents($tempDir . '/src/DescribeCommand.php', $commandCode);

    $manifest = new ModuleManifest(
        name: 'test/module',
        version: '1.0.0',
        path: $tempDir,
    );

    $discovery = new CommandDiscovery(new ClassFileParser());
    $commands = $discovery->discover([$manifest]);

    expect($commands)->toHaveCount(1)
        ->and($commands[0]->description)->toBe('This is a detailed description');

    // Cleanup
    unlink($tempDir . '/src/DescribeCommand.php');
    rmdir($tempDir . '/src');
    rmdir($tempDir);
});

it('throws CommandException when command class missing execute method', function (): void {
    $tempDir = sys_get_temp_dir() . '/marko_test_' . uniqid();
    mkdir($tempDir . '/src', 0755, true);

    // Create a command class without execute method (and without interface so it can load)
    $commandCode = <<<'PHP'
<?php

declare(strict_types=1);

namespace TestCommandModule7;

use Marko\Core\Attributes\Command;

#[Command(name: 'broken:command', description: 'Missing execute')]
class MissingExecuteCommand
{
    // Missing execute method!
}
PHP;
    file_put_contents($tempDir . '/src/MissingExecuteCommand.php', $commandCode);

    $manifest = new ModuleManifest(
        name: 'test/module',
        version: '1.0.0',
        path: $tempDir,
    );

    $discovery = new CommandDiscovery(new ClassFileParser());

    expect(fn () => $discovery->discover([$manifest]))
        ->toThrow(CommandException::class, 'must have an execute method');

    // Cleanup
    unlink($tempDir . '/src/MissingExecuteCommand.php');
    rmdir($tempDir . '/src');
    rmdir($tempDir);
});

it('throws CommandException when command class does not implement CommandInterface', function (): void {
    $tempDir = sys_get_temp_dir() . '/marko_test_' . uniqid();
    mkdir($tempDir . '/src', 0755, true);

    // Create a command class with execute method but not implementing interface
    $commandCode = <<<'PHP'
<?php

declare(strict_types=1);

namespace TestCommandModule8;

use Marko\Core\Attributes\Command;

#[Command(name: 'no:interface', description: 'Does not implement interface')]
class NoInterfaceCommand
{
    public function execute(): int
    {
        return 0;
    }
}
PHP;
    file_put_contents($tempDir . '/src/NoInterfaceCommand.php', $commandCode);

    $manifest = new ModuleManifest(
        name: 'test/module',
        version: '1.0.0',
        path: $tempDir,
    );

    $discovery = new CommandDiscovery(new ClassFileParser());

    expect(fn () => $discovery->discover([$manifest]))
        ->toThrow(CommandException::class, 'must implement CommandInterface');

    // Cleanup
    unlink($tempDir . '/src/NoInterfaceCommand.php');
    rmdir($tempDir . '/src');
    rmdir($tempDir);
});

it('discovers aliases from Command attribute', function (): void {
    $tempDir = sys_get_temp_dir() . '/marko_test_' . uniqid();
    mkdir($tempDir . '/src', 0755, true);

    $commandCode = <<<'PHP'
<?php

declare(strict_types=1);

namespace TestCommandModuleAliases1;

use Marko\Core\Attributes\Command;
use Marko\Core\Command\CommandInterface;
use Marko\Core\Command\Input;
use Marko\Core\Command\Output;

#[Command(name: 'app:run', description: 'Run the app', aliases: ['run', 'r'])]
class AliasedCommand implements CommandInterface
{
    public function execute(
        Input $input,
        Output $output,
    ): int {
        return 0;
    }
}
PHP;
    file_put_contents($tempDir . '/src/AliasedCommand.php', $commandCode);

    $manifest = new ModuleManifest(
        name: 'test/module',
        version: '1.0.0',
        path: $tempDir,
    );

    $discovery = new CommandDiscovery(new ClassFileParser());
    $commands = $discovery->discover([$manifest]);

    expect($commands)->toHaveCount(1)
        ->and($commands[0]->aliases)->toBe(['run', 'r']);

    // Cleanup
    unlink($tempDir . '/src/AliasedCommand.php');
    rmdir($tempDir . '/src');
    rmdir($tempDir);
});

it('creates CommandDefinition with empty aliases when none specified', function (): void {
    $tempDir = sys_get_temp_dir() . '/marko_test_' . uniqid();
    mkdir($tempDir . '/src', 0755, true);

    $commandCode = <<<'PHP'
<?php

declare(strict_types=1);

namespace TestCommandModuleAliases2;

use Marko\Core\Attributes\Command;
use Marko\Core\Command\CommandInterface;
use Marko\Core\Command\Input;
use Marko\Core\Command\Output;

#[Command(name: 'app:no-alias', description: 'No aliases here')]
class NoAliasCommand implements CommandInterface
{
    public function execute(
        Input $input,
        Output $output,
    ): int {
        return 0;
    }
}
PHP;
    file_put_contents($tempDir . '/src/NoAliasCommand.php', $commandCode);

    $manifest = new ModuleManifest(
        name: 'test/module',
        version: '1.0.0',
        path: $tempDir,
    );

    $discovery = new CommandDiscovery(new ClassFileParser());
    $commands = $discovery->discover([$manifest]);

    expect($commands)->toHaveCount(1)
        ->and($commands[0]->aliases)->toBeEmpty();

    // Cleanup
    unlink($tempDir . '/src/NoAliasCommand.php');
    rmdir($tempDir . '/src');
    rmdir($tempDir);
});

it('creates CommandDefinition with aliases when specified in attribute', function (): void {
    $tempDir = sys_get_temp_dir() . '/marko_test_' . uniqid();
    mkdir($tempDir . '/src', 0755, true);

    $commandCode = <<<'PHP'
<?php

declare(strict_types=1);

namespace TestCommandModuleAliases3;

use Marko\Core\Attributes\Command;
use Marko\Core\Command\CommandInterface;
use Marko\Core\Command\Input;
use Marko\Core\Command\Output;

#[Command(name: 'db:migrate', description: 'Run migrations', aliases: ['migrate', 'migration:run'])]
class MigrateCommand implements CommandInterface
{
    public function execute(
        Input $input,
        Output $output,
    ): int {
        return 0;
    }
}
PHP;
    file_put_contents($tempDir . '/src/MigrateCommand.php', $commandCode);

    $manifest = new ModuleManifest(
        name: 'test/module',
        version: '1.0.0',
        path: $tempDir,
    );

    $discovery = new CommandDiscovery(new ClassFileParser());
    $commands = $discovery->discover([$manifest]);

    expect($commands)->toHaveCount(1)
        ->and($commands[0]->aliases)->toBe(['migrate', 'migration:run']);

    // Cleanup
    unlink($tempDir . '/src/MigrateCommand.php');
    rmdir($tempDir . '/src');
    rmdir($tempDir);
});

it('discovers commands from multiple modules', function (): void {
    // Create first module
    $tempDir1 = sys_get_temp_dir() . '/marko_test_' . uniqid();
    mkdir($tempDir1 . '/src', 0755, true);

    $commandCode1 = <<<'PHP'
<?php

declare(strict_types=1);

namespace TestCommandModuleA;

use Marko\Core\Attributes\Command;
use Marko\Core\Command\CommandInterface;
use Marko\Core\Command\Input;
use Marko\Core\Command\Output;

#[Command(name: 'module-a:command', description: 'Command from module A')]
class ModuleACommand implements CommandInterface
{
    public function execute(
        Input $input,
        Output $output,
    ): int {
        return 0;
    }
}
PHP;
    file_put_contents($tempDir1 . '/src/ModuleACommand.php', $commandCode1);

    // Create second module
    $tempDir2 = sys_get_temp_dir() . '/marko_test_' . uniqid();
    mkdir($tempDir2 . '/src', 0755, true);

    $commandCode2 = <<<'PHP'
<?php

declare(strict_types=1);

namespace TestCommandModuleB;

use Marko\Core\Attributes\Command;
use Marko\Core\Command\CommandInterface;
use Marko\Core\Command\Input;
use Marko\Core\Command\Output;

#[Command(name: 'module-b:command', description: 'Command from module B')]
class ModuleBCommand implements CommandInterface
{
    public function execute(
        Input $input,
        Output $output,
    ): int {
        return 0;
    }
}
PHP;
    file_put_contents($tempDir2 . '/src/ModuleBCommand.php', $commandCode2);

    // Create module manifests
    $manifest1 = new ModuleManifest(
        name: 'test/module-a',
        version: '1.0.0',
        path: $tempDir1,
    );

    $manifest2 = new ModuleManifest(
        name: 'test/module-b',
        version: '1.0.0',
        path: $tempDir2,
    );

    $discovery = new CommandDiscovery(new ClassFileParser());
    $commands = $discovery->discover([$manifest1, $manifest2]);

    expect($commands)->toHaveCount(2);

    $names = array_map(fn ($cmd) => $cmd->name, $commands);
    expect($names)->toContain('module-a:command')
        ->and($names)->toContain('module-b:command');

    // Cleanup
    unlink($tempDir1 . '/src/ModuleACommand.php');
    rmdir($tempDir1 . '/src');
    rmdir($tempDir1);
    unlink($tempDir2 . '/src/ModuleBCommand.php');
    rmdir($tempDir2 . '/src');
    rmdir($tempDir2);
});
