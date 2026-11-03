<?php

declare(strict_types=1);

use Marko\Core\Command\CommandDefinition;
use Marko\Core\Command\CommandInterface;
use Marko\Core\Command\CommandRegistry;
use Marko\Core\Command\CommandRunner;
use Marko\Core\Command\Input;
use Marko\Core\Command\Output;
use Marko\Core\Container\Container;
use Marko\Core\Container\ContainerInterface;
use Marko\Core\Exceptions\CommandException;

it('registers the running command input and output in the container before resolving the command', function (): void {
    $input = new Input(['marko', 'test:cmd', '--force', '5']);
    $output = new Output(fopen('php://memory', 'w'));

    $container = new Container();

    $command = new class () implements CommandInterface
    {
        public ?Input $executedWith = null;

        public function execute(
            Input $input,
            Output $output,
        ): int {
            $this->executedWith = $input;

            return 0;
        }
    };

    $registry = new CommandRegistry();
    $registry->register(new CommandDefinition(
        commandClass: $command::class,
        name: 'test:cmd',
        description: 'A test command',
        flags: ['force'],
    ));

    // The command class is bound to a closure that reads the container at resolution time
    $resolvedWith = null;
    $container->bind(
        $command::class,
        function () use ($container, $command, &$resolvedWith): CommandInterface {
            $resolvedWith = $container->get(Input::class);

            return $command;
        },
    );

    $runner = new CommandRunner($container, $registry);
    $runner->run('test:cmd', $input, $output);

    $registeredInput = $container->get(Input::class);

    expect($resolvedWith)->toBe($registeredInput)
        ->and($command->executedWith)->toBe($registeredInput)
        ->and($registeredInput->getArgument(0))->toBe('5')
        ->and($container->get(Output::class))->toBe($output);
});

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

    $container = $this->createStub(ContainerInterface::class);
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

    $container = $this->createStub(ContainerInterface::class);
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

    $container = $this->createStub(ContainerInterface::class);
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
    $container = $this->createStub(ContainerInterface::class);

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

    $container = $this->createStub(ContainerInterface::class);
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

    $container = $this->createStub(ContainerInterface::class);
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

    $container = $this->createStub(ContainerInterface::class);
    $container->method('get')
        ->willReturn($command);

    $runner = new CommandRunner($container, $registry);
    $exitCode = $runner->run('tc', $input, $output);

    expect($exitCode)->toBe(42);
});

it('passes declared flags into the input given to the command', function (): void {
    $input = new Input(['marko', 'queue:retry', '--all', '5']);
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
        name: 'queue:retry',
        flags: ['all'],
    ));

    $container = $this->createStub(ContainerInterface::class);
    $container->method('get')
        ->willReturn($command);

    $runner = new CommandRunner($container, $registry);
    $runner->run('queue:retry', $input, $output);

    expect($receivedInput->getOption('all'))->toBe('true')
        ->and($receivedInput->getArgument(0))->toBe('5');
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

    $container = $this->createStub(ContainerInterface::class);
    $container->method('get')
        ->willReturn($command);

    $runner = new CommandRunner($container, $registry);
    $runner->run('tc', $input, $output);

    expect($receivedInput)->toBe($input)
        ->and($receivedOutput)->toBe($output);
});

/**
 * A runner with one 'account:purge' command that records whether it executed.
 *
 * @return array{runner: CommandRunner, command: object{executed: bool}}
 */
function purgeCommandRunner(): array
{
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
        name: 'account:purge',
        description: 'Delete test accounts',
        aliases: ['purge'],
        flags: ['dry-run', 'f'],
        destructive: true,
        options: ['older-than'],
    ));

    $container = new Container();
    $container->instance($command::class, $command);

    return ['runner' => new CommandRunner($container, $registry), 'command' => $command];
}

/**
 * @param resource $stream
 */
function readOutputStream(
    mixed $stream,
): string {
    rewind($stream);

    return (string) stream_get_contents($stream);
}

it('fails loudly on an undeclared option before the command executes', function (): void {
    ['runner' => $runner, 'command' => $command] = purgeCommandRunner();
    $input = new Input(['marko', 'account:purge', '--dryrun', '-x', '5']);

    try {
        $runner->run('account:purge', $input, new Output(fopen('php://memory', 'w')));
        $thrown = null;
    } catch (CommandException $e) {
        $thrown = $e;
    }

    expect($thrown)->toBeInstanceOf(CommandException::class)
        ->and($thrown->getMessage())->toBe("Command 'account:purge' does not accept the options '--dryrun', '-x'")
        ->and($thrown->getSuggestion())->toContain('--older-than=<value>, --dry-run, -f, --no-interaction, --help')
        ->and($thrown->getSuggestion())->toContain('marko account:purge --help')
        ->and($command->executed)->toBeFalse();
});

it('names a single undeclared option in the singular', function (): void {
    ['runner' => $runner] = purgeCommandRunner();

    expect(fn () => $runner->run(
        'purge',
        new Input(['marko', 'purge', '--all']),
        new Output(fopen('php://memory', 'w')),
    ))->toThrow(CommandException::class, "Command 'account:purge' does not accept the option '--all'");
});

it('runs a command given only declared options, flags and global flags', function (): void {
    ['runner' => $runner, 'command' => $command] = purgeCommandRunner();
    $input = new Input(['marko', 'account:purge', '--older-than=30', '--dry-run', '-f', '--no-interaction']);

    $exitCode = $runner->run('account:purge', $input, new Output(fopen('php://memory', 'w')));

    expect($exitCode)->toBe(0)
        ->and($command->executed)->toBeTrue();
});

it('prints the command help for --help instead of executing the command', function (): void {
    ['runner' => $runner, 'command' => $command] = purgeCommandRunner();
    $stream = fopen('php://memory', 'w+');

    $exitCode = $runner->run('purge', new Input(['marko', 'purge', '--help', '--unknown']), new Output($stream));

    expect($exitCode)->toBe(0)
        ->and($command->executed)->toBeFalse()
        ->and(readOutputStream($stream))->toBe(
            <<<'TEXT'
            account:purge (purge)
              Delete test accounts
              Changes or deletes stored state.

            Usage:
              marko account:purge [options] [arguments]

            Options:
              --older-than=<value>
              --dry-run
              -f
              --no-interaction
              --help

            TEXT,
        );
});
