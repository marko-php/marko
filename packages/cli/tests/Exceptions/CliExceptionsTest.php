<?php

declare(strict_types=1);

use Marko\Cli\Exceptions\BootstrapException;
use Marko\Cli\Exceptions\CliException;
use Marko\Cli\Exceptions\CommandNotFoundException;
use Marko\Cli\Exceptions\ProjectNotFoundException;

describe('ProjectNotFoundException', function (): void {
    it('creates ProjectNotFoundException with helpful message', function (): void {
        $exception = ProjectNotFoundException::fromDirectory('/some/path');

        expect($exception)->toBeInstanceOf(ProjectNotFoundException::class)
            ->and($exception->getMessage())->toContain('No Marko project found');
    });

    it('includes current directory in ProjectNotFoundException message', function (): void {
        $directory = '/home/user/my-project';
        $exception = ProjectNotFoundException::fromDirectory($directory);

        expect($exception->getMessage())->toContain($directory)
            ->and($exception->getContext())->toContain($directory);
    });

    it('suggests running from project directory in ProjectNotFoundException', function (): void {
        $exception = ProjectNotFoundException::fromDirectory('/some/path');

        expect($exception->getSuggestion())->toContain('Marko project')
            ->and($exception->getSuggestion())->toContain('vendor/marko/core');
    });
});

describe('BootstrapException', function (): void {
    it('creates BootstrapException for boot failures', function (): void {
        $cause = new RuntimeException('Failed to load config');
        $exception = BootstrapException::fromCause($cause);

        expect($exception)->toBeInstanceOf(BootstrapException::class)
            ->and($exception->getMessage())->toContain('Failed to boot Marko application');
    });

    it('includes cause in BootstrapException message', function (): void {
        $causeMessage = 'Module loader failed';
        $cause = new RuntimeException($causeMessage);
        $exception = BootstrapException::fromCause($cause);

        expect($exception->getMessage())->toContain($causeMessage)
            ->and($exception->getPrevious())->toBe($cause);
    });
});

describe('CommandNotFoundException', function (): void {
    it('creates CommandNotFoundException with helpful message', function (): void {
        $exception = CommandNotFoundException::forCommand('foo');

        expect($exception)->toBeInstanceOf(CommandNotFoundException::class)
            ->and($exception->getMessage())->toContain("Command 'foo' not found");
    });

    it('suggests running list command in CommandNotFoundException', function (): void {
        $exception = CommandNotFoundException::forCommand('foo');

        expect($exception->getSuggestion())->toContain('marko list');
    });
});

describe('CliException', function (): void {
    it('all exceptions extend base CliException', function (): void {
        $projectNotFound = ProjectNotFoundException::fromDirectory('/path');
        $bootstrap = BootstrapException::fromCause(new RuntimeException('test'));
        $commandNotFound = CommandNotFoundException::forCommand('test');

        expect($projectNotFound)->toBeInstanceOf(CliException::class)
            ->and($bootstrap)->toBeInstanceOf(CliException::class)
            ->and($commandNotFound)->toBeInstanceOf(CliException::class);
    });
});
