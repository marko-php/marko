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

it('returns positional arguments after command name without option tokens', function (): void {
    $input = new Input(['marko', 'route:list', 'extra', '--verbose']);

    expect($input->getArguments())->toBe(['extra']);
});

it('checks if argument exists by index', function (): void {
    $input = new Input(['marko', 'route:list', 'first', '--verbose']);

    expect($input->hasArgument(0))->toBeTrue()
        ->and($input->hasArgument(1))->toBeFalse();
});

it('returns null for missing argument', function (): void {
    $input = new Input(['marko', 'route:list', 'first', '--verbose']);

    expect($input->getArgument(0))->toBe('first')
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

it('checks if short option flag exists', function (): void {
    $input = new Input(['marko', 'dev:up', '-d']);

    expect($input->hasOption('d'))->toBeTrue()
        ->and($input->hasOption('x'))->toBeFalse();
});

it('returns true for short boolean flag', function (): void {
    $input = new Input(['marko', 'dev:up', '-d']);

    expect($input->getOption('d'))->toBe('true');
});

it('returns short option value with equals syntax', function (): void {
    $input = new Input(['marko', 'dev:up', '-p=8000']);

    expect($input->hasOption('p'))->toBeTrue()
        ->and($input->getOption('p'))->toBe('8000');
});

it('returns short option value with space syntax', function (): void {
    $input = new Input(['marko', 'dev:up', '-p', '8000']);

    expect($input->hasOption('p'))->toBeTrue()
        ->and($input->getOption('p'))->toBe('8000');
});

it('does not match short options for multi-char names', function (): void {
    $input = new Input(['marko', 'dev:up', '-d']);

    expect($input->hasOption('detach'))->toBeFalse();
});

it('does not match long options for single-char names', function (): void {
    $input = new Input(['marko', 'dev:up', '--detach']);

    expect($input->hasOption('d'))->toBeFalse();
});

describe('long option values', function (): void {
    it('returns the value of a long option given as --queue emails', function (): void {
        $input = new Input(['marko', 'queue:work', '--queue', 'emails']);

        expect($input->hasOption('queue'))->toBeTrue()
            ->and($input->getOption('queue'))->toBe('emails')
            ->and($input->getArguments())->toBe([]);
    });

    it('returns the value of a long option given with an equals sign', function (): void {
        $input = new Input(['marko', 'queue:work', '--queue=emails']);

        expect($input->getOption('queue'))->toBe('emails');
    });

    it('returns true for a long option at the end of input', function (): void {
        $input = new Input(['marko', 'queue:work', '--queue']);

        expect($input->getOption('queue'))->toBe('true');
    });

    it('returns true for a long option followed by another option', function (): void {
        $input = new Input(['marko', 'queue:work', '--queue', '--verbose']);

        expect($input->getOption('queue'))->toBe('true')
            ->and($input->getOption('verbose'))->toBe('true');
    });

    it('returns the last value and all values for a repeated option', function (): void {
        $input = new Input(['marko', 'queue:work', '--queue', 'a', '--queue=b', '--queue', 'c']);

        expect($input->getOption('queue'))->toBe('c')
            ->and($input->getOptionValues('queue'))->toBe(['a', 'b', 'c']);
    });

    it('returns an empty list of values for a missing option', function (): void {
        $input = new Input(['marko', 'queue:work']);

        expect($input->getOptionValues('queue'))->toBe([]);
    });

    it('returns all values for a repeated short option', function (): void {
        $input = new Input(['marko', 'dev:up', '-p', '8000', '-p=9000']);

        expect($input->getOption('p'))->toBe('9000')
            ->and($input->getOptionValues('p'))->toBe(['8000', '9000']);
    });

    it('treats every token after -- as a positional', function (): void {
        $input = new Input(['marko', 'run', 'first', '--verbose', '--', '--force', '-x', 'last']);

        expect($input->getArguments())->toBe(['first', '--force', '-x', 'last'])
            ->and($input->hasOption('verbose'))->toBeTrue()
            ->and($input->hasOption('force'))->toBeFalse()
            ->and($input->hasOption('x'))->toBeFalse();
    });

    it('excludes option tokens from positionals in any order when the flag is declared', function (): void {
        $before = new Input(['marko', 'queue:retry', '--force', '5'], ['force']);
        $after = new Input(['marko', 'queue:retry', '5', '--force'], ['force']);

        expect($before->getArgument(0))->toBe('5')
            ->and($before->getOption('force'))->toBe('true')
            ->and($after->getArgument(0))->toBe('5')
            ->and($after->getOption('force'))->toBe('true');
    });

    it('never lets a declared short flag consume the next token', function (): void {
        $input = new Input(['marko', 'dev:up', '-d', 'extra'], ['d']);

        expect($input->getOption('d'))->toBe('true')
            ->and($input->getArguments())->toBe(['extra']);
    });

    it('lets an undeclared bare long option consume the following non-dash token', function (): void {
        $input = new Input(['marko', 'queue:retry', '--force', '5']);

        expect($input->getOption('force'))->toBe('5')
            ->and($input->getArguments())->toBe([]);
    });

    it('lets an undeclared short option consume the following non-dash token', function (): void {
        $input = new Input(['marko', 'dev:up', '-p', '8000', 'extra']);

        expect($input->getOption('p'))->toBe('8000')
            ->and($input->getArguments())->toBe(['extra']);
    });

    it('treats a single dash as a positional', function (): void {
        $input = new Input(['marko', 'import', '-']);

        expect($input->getArguments())->toBe(['-']);
    });

    it('applies declared flags through withFlags without mutating the original input', function (): void {
        $original = new Input(['marko', 'queue:retry', '--force', '5']);
        $flagged = $original->withFlags(['force']);

        expect($flagged->getArgument(0))->toBe('5')
            ->and($flagged->getOption('force'))->toBe('true')
            ->and($flagged->getCommand())->toBe('queue:retry')
            ->and($original->getOption('force'))->toBe('5')
            ->and($original->getArgument(0))->toBeNull();
    });
});

describe('interactivity', function (): void {
    it('reports interactive when --no-interaction is not passed', function (): void {
        $input = new Input(['marko', 'db:migrate']);

        expect($input->isInteractive())->toBeTrue();
    });

    it('reports non-interactive when --no-interaction is passed', function (): void {
        $input = new Input(['marko', 'db:migrate', '--no-interaction']);

        expect($input->isInteractive())->toBeFalse();
    });

    it(
        'declares no-interaction as a flag for every command so it never consumes the next argument',
        function (): void {
            $input = new Input(['marko', 'queue:retry', '--no-interaction', '5']);
            $flagged = $input->withFlags(['force']);

            expect($input->getArgument(0))->toBe('5')
                ->and($input->isInteractive())->toBeFalse()
                ->and($flagged->getArgument(0))->toBe('5')
                ->and($flagged->isInteractive())->toBeFalse();
        },
    );
});

describe('option names', function (): void {
    it('lists the given options by the names hasOption takes, in first-seen order', function (): void {
        $input = new Input(['marko', 'queue:work', '--queue', 'emails', '-d', 'x', '--sleep=3', '--queue', 'sms']);

        expect($input->optionNames())->toBe(['queue', 'd', 'sleep']);
    });

    it('returns an empty list when no options were given', function (): void {
        $input = new Input(['marko', 'db:seed', 'UserSeeder', '--', '--not-an-option']);

        expect($input->optionNames())->toBe([]);
    });

    it('keeps the prefix on option tokens hasOption cannot address', function (): void {
        $input = new Input(['marko', 'cmd', '--x', '-ab']);

        expect($input->optionNames())->toBe(['--x', '-ab']);
    });

    it('lists a numeric short option as a string name', function (): void {
        $input = new Input(['marko', 'db:rollback', '-1']);

        expect($input->optionNames())->toBe(['1'])
            ->and($input->undeclaredOptions([]))->toBe(['-1']);
    });

    it('lists the options missing from the accepted names, as they were typed', function (): void {
        $input = new Input(['marko', 'cmd', '--help', '--force', '-d', '--x', '--dry-run=1']);

        expect($input->undeclaredOptions(['force', 'x', 'dry-run']))->toBe(['--help', '-d', '--x']);
    });

    it('treats --help as a global flag that never consumes the next argument', function (): void {
        $input = new Input(['marko', 'db:seed', '--help', 'UserSeeder']);

        expect($input->hasOption(Input::HELP))->toBeTrue()
            ->and($input->getArgument(0))->toBe('UserSeeder');
    });
});
