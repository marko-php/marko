<?php

declare(strict_types=1);

namespace Marko\Roadrunner\Tests\Command;

use Marko\Core\Attributes\Command;
use Marko\Core\Command\CommandInterface;
use Marko\Core\Command\Input;
use Marko\Core\Path\ProjectPaths;
use Marko\Roadrunner\Command\ServeCommand;
use Marko\Roadrunner\Exceptions\RoadRunnerException;
use ReflectionClass;

describe('ServeCommand', function (): void {
    it('registers an rr serve command', function (): void {
        $reflection = new ReflectionClass(ServeCommand::class);
        $attributes = $reflection->getAttributes(Command::class);

        expect($attributes)->toHaveCount(1)
            ->and($attributes[0]->newInstance()->name)->toBe('rr:serve')
            ->and($reflection->implementsInterface(CommandInterface::class))->toBeTrue();
    });

    it('fails with installation guidance when the roadrunner binary is missing', function (): void {
        $tempDir = Helpers::tempProjectDir();
        $command = Helpers::serveCommand(
            binaryLocator: new FakeBinaryLocator(null),
            paths: new ProjectPaths($tempDir),
        );

        expect(fn () => $command->execute(new Input(['marko', 'rr:serve']), Helpers::output()))
            ->toThrow(RoadRunnerException::class, 'RoadRunner binary not found.');

        $exception = RoadRunnerException::binaryNotFound();

        expect($exception->getSuggestion())->toContain('roadrunner.dev');

        Helpers::removeTempProjectDir($tempDir);
    });

    it('passes a custom config path through to roadrunner when given one', function (): void {
        $tempDir = Helpers::tempProjectDir();
        $processRunner = new FakeProcessRunner();
        $command = Helpers::serveCommand(
            processRunner: $processRunner,
            paths: new ProjectPaths($tempDir),
        );

        $command->execute(new Input(['marko', 'rr:serve', '--config=custom.rr.yaml']), Helpers::output());

        expect($processRunner->lastCommand)->toContain('custom.rr.yaml')
            ->and(file_exists($tempDir . '/custom.rr.yaml'))->toBeTrue()
            ->and(file_exists($tempDir . '/.rr.yaml'))->toBeFalse();

        Helpers::removeTempProjectDir($tempDir);
    });

    it('refuses to overwrite an existing rr yaml', function (): void {
        $tempDir = Helpers::tempProjectDir();
        $existingContents = "# hand-tuned config, do not touch\n";
        file_put_contents($tempDir . '/.rr.yaml', $existingContents);
        ['stream' => $stream, 'output' => $output] = Helpers::outputStream();
        $command = Helpers::serveCommand(paths: new ProjectPaths($tempDir));

        $command->execute(new Input(['marko', 'rr:serve']), $output);
        rewind($stream);

        expect(file_get_contents($tempDir . '/.rr.yaml'))->toBe($existingContents)
            ->and(stream_get_contents($stream))->toContain("Using existing config: $tempDir/.rr.yaml");

        Helpers::removeTempProjectDir($tempDir);
    });
});
