<?php

declare(strict_types=1);

use Marko\Amphp\Command\PubSubListenCommand;
use Marko\Amphp\EventLoopRunner;
use Marko\Core\Attributes\Command;
use Marko\Core\Command\CommandInterface;
use Marko\Core\Command\Input;
use Marko\Core\Command\Output;

it('has Command attribute with name pubsub:listen and description', function (): void {
    $reflection = new ReflectionClass(PubSubListenCommand::class);
    $attributes = $reflection->getAttributes(Command::class);

    expect($attributes)->toHaveCount(1)
        ->and($attributes[0]->newInstance()->name)->toBe('pubsub:listen')
        ->and($attributes[0]->newInstance()->description)->not->toBeEmpty();
});

it('implements CommandInterface', function (): void {
    $reflection = new ReflectionClass(PubSubListenCommand::class);

    expect($reflection->implementsInterface(CommandInterface::class))->toBeTrue();
});

it('starts the event loop via EventLoopRunner when executed', function (): void {
    $runCalled = false;

    $runner = new class ($runCalled) extends EventLoopRunner
    {
        public function __construct(
            /** @noinspection PhpPropertyOnlyWrittenInspection - Reference property modifies external variable */
            private bool &$runCalled,
        ) {}

        protected function doRun(): void
        {
            $this->runCalled = true;
        }
    };

    $stream = fopen('php://memory', 'r+');
    $output = new Output($stream);
    $input = new Input(['marko', 'pubsub:listen']);

    $command = new PubSubListenCommand($runner);
    $command->execute($input, $output);

    expect($runCalled)->toBeTrue();
});

it('outputs startup message to Output', function (): void {
    $runner = new class () extends EventLoopRunner
    {
        protected function doRun(): void {}
    };

    $stream = fopen('php://memory', 'r+');
    $output = new Output($stream);
    $input = new Input(['marko', 'pubsub:listen']);

    $command = new PubSubListenCommand($runner);
    $command->execute($input, $output);

    rewind($stream);
    $result = stream_get_contents($stream);

    expect($result)->toContain('Starting pub/sub listener...');
});

it('returns 0 on successful completion', function (): void {
    $runner = new class () extends EventLoopRunner
    {
        protected function doRun(): void {}
    };

    $stream = fopen('php://memory', 'r+');
    $output = new Output($stream);
    $input = new Input(['marko', 'pubsub:listen']);

    $command = new PubSubListenCommand($runner);
    $exitCode = $command->execute($input, $output);

    expect($exitCode)->toBe(0);
});
