<?php

declare(strict_types=1);

use Marko\Core\Command\CommandDefinition;
use Marko\Core\Command\CommandInterface;
use Marko\Core\Command\CommandRegistry;
use Marko\Core\Command\CommandRunner;
use Marko\Core\Command\Input;
use Marko\Core\Command\Output;
use Marko\Core\Container\ContainerInterface;
use Marko\Core\Exceptions\CommandException;

it('executes command by name', function (): void {
    $input = new Input(['marko', 'test:greet']);
    $output = new Output(fopen('php://memory', 'w'));

    $command = new class () implements CommandInterface
    {
        public bool $executed = false;

        public function execute(
            Input $input,
            Output $output,
        ): int {
            $this->executed = true;

            return 0;
        }
    };

    $registry = new CommandRegistry();
    $registry->register(new CommandDefinition(
        commandClass: $command::class,
        name: 'test:greet',
        description: 'A test command',
    ));

    $container = $this->createMock(ContainerInterface::class);
    $container->method('get')
        ->with($command::class)
        ->willReturn($command);

    $runner = new CommandRunner($container, $registry);
    $runner->run('test:greet', $input, $output);

    expect($command->executed)->toBeTrue();
});

it('instantiates command class via container', function (): void {
    $input = new Input(['marko', 'test:cmd']);
    $output = new Output(fopen('php://memory', 'w'));

    $command = new class () implements CommandInterface
    {
        public function execute(
            Input $input,
            Output $output,
        ): int {
            return 0;
        }
    };

    $registry = new CommandRegistry();
    $registry->register(new CommandDefinition(
        commandClass: $command::class,
        name: 'test:cmd',
        description: 'Test command',
    ));

    $container = $this->createMock(ContainerInterface::class);
    $container->expects($this->once())
        ->method('get')
        ->with($command::class)
        ->willReturn($command);

    $runner = new CommandRunner($container, $registry);
    $runner->run('test:cmd', $input, $output);
});

it('passes Input to execute method', function (): void {
    $input = new Input(['marko', 'test:echo', 'hello', 'world']);
    $output = new Output(fopen('php://memory', 'w'));

    $receivedInput = null;
    $command = new class ($receivedInput) implements CommandInterface
    {
        public function __construct(
            /** @noinspection PhpPropertyOnlyWrittenInspection - Reference property modifies external variable */
            private ?Input &$receivedInput,
        ) {}

        public function execute(
            Input $input,
            Output $output,
        ): int {
            $this->receivedInput = $input;

            return 0;
        }
    };

    $registry = new CommandRegistry();
    $registry->register(new CommandDefinition(
        commandClass: $command::class,
        name: 'test:echo',
        description: 'Echo command',
    ));

    $container = $this->createMock(ContainerInterface::class);
    $container->method('get')
        ->willReturn($command);

    $runner = new CommandRunner($container, $registry);
    $runner->run('test:echo', $input, $output);

    expect($receivedInput)->toBe($input);
});

it('passes Output to execute method', function (): void {
    $input = new Input(['marko', 'test:out']);
    $output = new Output(fopen('php://memory', 'w'));

    $receivedOutput = null;
    $command = new class ($receivedOutput) implements CommandInterface
    {
        public function __construct(
            /** @noinspection PhpPropertyOnlyWrittenInspection - Reference property modifies external variable */
            private ?Output &$receivedOutput,
        ) {}

        public function execute(
            Input $input,
            Output $output,
        ): int {
            $this->receivedOutput = $output;

            return 0;
        }
    };

    $registry = new CommandRegistry();
    $registry->register(new CommandDefinition(
        commandClass: $command::class,
        name: 'test:out',
        description: 'Output command',
    ));

    $container = $this->createMock(ContainerInterface::class);
    $container->method('get')
        ->willReturn($command);

    $runner = new CommandRunner($container, $registry);
    $runner->run('test:out', $input, $output);

    expect($receivedOutput)->toBe($output);
});

it('returns exit code from command execute method', function (): void {
    $input = new Input(['marko', 'test:exit']);
    $output = new Output(fopen('php://memory', 'w'));

    $command = new class () implements CommandInterface
    {
        public function execute(
            Input $input,
            Output $output,
        ): int {
            return 42;
        }
    };

    $registry = new CommandRegistry();
    $registry->register(new CommandDefinition(
        commandClass: $command::class,
        name: 'test:exit',
        description: 'Exit code command',
    ));

    $container = $this->createMock(ContainerInterface::class);
    $container->method('get')
        ->willReturn($command);

    $runner = new CommandRunner($container, $registry);
    $exitCode = $runner->run('test:exit', $input, $output);

    expect($exitCode)->toBe(42);
});

it('throws CommandException when command not found', function (): void {
    $input = new Input(['marko', 'nonexistent:command']);
    $output = new Output(fopen('php://memory', 'w'));

    $registry = new CommandRegistry();
    $container = $this->createMock(ContainerInterface::class);

    $runner = new CommandRunner($container, $registry);

    expect(fn () => $runner->run('nonexistent:command', $input, $output))
        ->toThrow(CommandException::class, "Command 'nonexistent:command' not found");
});

it('returns exit code 0 on successful execution', function (): void {
    $input = new Input(['marko', 'test:success']);
    $output = new Output(fopen('php://memory', 'w'));

    $command = new class () implements CommandInterface
    {
        public function execute(
            Input $input,
            Output $output,
        ): int {
            return 0;
        }
    };

    $registry = new CommandRegistry();
    $registry->register(new CommandDefinition(
        commandClass: $command::class,
        name: 'test:success',
        description: 'Successful command',
    ));

    $container = $this->createMock(ContainerInterface::class);
    $container->method('get')
        ->willReturn($command);

    $runner = new CommandRunner($container, $registry);
    $exitCode = $runner->run('test:success', $input, $output);

    expect($exitCode)->toBe(0);
});

it('returns non-zero exit code on command failure', function (): void {
    $input = new Input(['marko', 'test:fail']);
    $output = new Output(fopen('php://memory', 'w'));

    $command = new class () implements CommandInterface
    {
        public function execute(
            Input $input,
            Output $output,
        ): int {
            return 1;
        }
    };

    $registry = new CommandRegistry();
    $registry->register(new CommandDefinition(
        commandClass: $command::class,
        name: 'test:fail',
        description: 'Failing command',
    ));

    $container = $this->createMock(ContainerInterface::class);
    $container->method('get')
        ->willReturn($command);

    $runner = new CommandRunner($container, $registry);
    $exitCode = $runner->run('test:fail', $input, $output);

    expect($exitCode)->not->toBe(0)
        ->and($exitCode)->toBe(1);
});

it('executes command via alias name', function (): void {
    $input = new Input(['marko', 'tc']);
    $output = new Output(fopen('php://memory', 'w'));

    $command = new class () implements CommandInterface
    {
        public bool $executed = false;

        public function execute(
            Input $input,
            Output $output,
        ): int {
            $this->executed = true;

            return 0;
        }
    };

    $registry = new CommandRegistry();
    $registry->register(new CommandDefinition(
        commandClass: $command::class,
        name: 'test:cmd',
        description: 'A test command',
        aliases: ['tc'],
    ));

    $container = $this->createMock(ContainerInterface::class);
    $container->method('get')
        ->with($command::class)
        ->willReturn($command);

    $runner = new CommandRunner($container, $registry);
    $runner->run('tc', $input, $output);

    expect($command->executed)->toBeTrue();
});

it('returns correct exit code when invoked via alias', function (): void {
    $input = new Input(['marko', 'tc']);
    $output = new Output(fopen('php://memory', 'w'));

    $command = new class () implements CommandInterface
    {
        public function execute(
            Input $input,
            Output $output,
        ): int {
            return 42;
        }
    };

    $registry = new CommandRegistry();
    $registry->register(new CommandDefinition(
        commandClass: $command::class,
        name: 'test:cmd',
        description: 'A test command',
        aliases: ['tc'],
    ));

    $container = $this->createMock(ContainerInterface::class);
    $container->method('get')
        ->willReturn($command);

    $runner = new CommandRunner($container, $registry);
    $exitCode = $runner->run('tc', $input, $output);

    expect($exitCode)->toBe(42);
});

it('passes Input and Output when invoked via alias', function (): void {
    $input = new Input(['marko', 'tc', 'hello', 'world']);
    $output = new Output(fopen('php://memory', 'w'));

    $receivedInput = null;
    $receivedOutput = null;
    $command = new class ($receivedInput, $receivedOutput) implements CommandInterface
    {
        public function __construct(
            /** @noinspection PhpPropertyOnlyWrittenInspection - Reference property modifies external variable */
            private ?Input &$receivedInput,
            /** @noinspection PhpPropertyOnlyWrittenInspection - Reference property modifies external variable */
            private ?Output &$receivedOutput,
        ) {}

        public function execute(
            Input $input,
            Output $output,
        ): int {
            $this->receivedInput = $input;
            $this->receivedOutput = $output;

            return 0;
        }
    };

    $registry = new CommandRegistry();
    $registry->register(new CommandDefinition(
        commandClass: $command::class,
        name: 'test:cmd',
        description: 'A test command',
        aliases: ['tc'],
    ));

    $container = $this->createMock(ContainerInterface::class);
    $container->method('get')
        ->willReturn($command);

    $runner = new CommandRunner($container, $registry);
    $runner->run('tc', $input, $output);

    expect($receivedInput)->toBe($input)
        ->and($receivedOutput)->toBe($output);
});
