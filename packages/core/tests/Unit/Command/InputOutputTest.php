<?php

declare(strict_types=1);

use Marko\Core\Command\Input;
use Marko\Core\Command\Output;

it('creates Input from array of arguments', function (): void {
    $input = new Input(['marko', 'route:list', '--verbose']);

    expect($input)->toBeInstanceOf(Input::class);
});

it('returns command name as first argument', function (): void {
    $input = new Input(['marko', 'route:list', '--verbose']);

    expect($input->getCommand())->toBe('route:list');
});

it('returns remaining arguments after command name', function (): void {
    $input = new Input(['marko', 'route:list', '--verbose', 'extra']);

    expect($input->getArguments())->toBe(['--verbose', 'extra']);
});

it('checks if argument exists by index', function (): void {
    $input = new Input(['marko', 'route:list', '--verbose']);

    expect($input->hasArgument(0))->toBeTrue()
        ->and($input->hasArgument(1))->toBeFalse();
});

it('returns null for missing argument', function (): void {
    $input = new Input(['marko', 'route:list', '--verbose']);

    expect($input->getArgument(0))->toBe('--verbose')
        ->and($input->getArgument(99))->toBeNull();
});

it('creates Output that writes to stream', function (): void {
    $stream = fopen('php://memory', 'r+');
    $output = new Output($stream);

    $output->write('Hello');

    rewind($stream);
    expect(stream_get_contents($stream))->toBe('Hello');
    fclose($stream);
});

it('writes line with newline character', function (): void {
    $stream = fopen('php://memory', 'r+');
    $output = new Output($stream);

    $output->writeLine('Hello World');

    rewind($stream);
    expect(stream_get_contents($stream))->toBe("Hello World\n");
    fclose($stream);
});

it('writes text without newline character', function (): void {
    $stream = fopen('php://memory', 'r+');
    $output = new Output($stream);

    $output->write('Hello');
    $output->write(' World');

    rewind($stream);
    expect(stream_get_contents($stream))->toBe('Hello World');
    fclose($stream);
});

it('writes empty line', function (): void {
    $stream = fopen('php://memory', 'r+');
    $output = new Output($stream);

    $output->writeLine('');

    rewind($stream);
    expect(stream_get_contents($stream))->toBe("\n");
    fclose($stream);
});

it('defaults Output to STDOUT', function (): void {
    $output = new Output();

    // Use reflection to access the private stream property
    $reflection = new ReflectionClass($output);
    $streamProperty = $reflection->getProperty('stream');

    expect($streamProperty->getValue($output))->toBe(STDOUT);
});

it('checks if option exists', function (): void {
    $input = new Input(['marko', 'queue:clear', '--queue=emails']);

    expect($input->hasOption('queue'))->toBeTrue()
        ->and($input->hasOption('verbose'))->toBeFalse();
});

it('returns option value with equals syntax', function (): void {
    $input = new Input(['marko', 'queue:clear', '--queue=emails']);

    expect($input->getOption('queue'))->toBe('emails');
});

it('returns null for missing option', function (): void {
    $input = new Input(['marko', 'queue:clear']);

    expect($input->getOption('queue'))->toBeNull();
});

it('returns true for boolean flag option', function (): void {
    $input = new Input(['marko', 'queue:work', '--verbose']);

    expect($input->hasOption('verbose'))->toBeTrue()
        ->and($input->getOption('verbose'))->toBe('true');
});
