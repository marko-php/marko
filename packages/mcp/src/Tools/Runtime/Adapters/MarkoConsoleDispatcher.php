<?php

declare(strict_types=1);

namespace Marko\Mcp\Tools\Runtime\Adapters;

use Marko\Core\Command\CommandRunner;
use Marko\Core\Command\Input;
use Marko\Core\Command\Output;
use Marko\Core\Exceptions\CommandException;
use Marko\Mcp\Tools\Runtime\Contracts\ConsoleDispatcherInterface;
use Throwable;

/**
 * Dispatches a console command through Marko's CommandRunner and captures its
 * stdout/stderr into in-memory streams.
 */
readonly class MarkoConsoleDispatcher implements ConsoleDispatcherInterface
{
    public function __construct(
        private CommandRunner $runner,
    ) {}

    /**
     * @param list<string> $args
     * @return array{exitCode: int, stdout: string, stderr: string}
     */
    public function dispatch(
        string $command,
        array $args = [],
    ): array
    {
        $stdout = fopen('php://memory', 'r+');
        $stderr = fopen('php://memory', 'r+');

        try {
            $exitCode = $this->runner->run(
                $command,
                new Input(['marko', $command, ...$args]),
                new Output($stdout),
            );
        } catch (CommandException $e) {
            fwrite($stderr, $e->getMessage() . "\n");

            return ['exitCode' => 1, 'stdout' => '', 'stderr' => $e->getMessage()];
        } catch (Throwable $e) {
            fwrite($stderr, $e->getMessage() . "\n");

            return ['exitCode' => 1, 'stdout' => self::readStream($stdout), 'stderr' => $e->getMessage()];
        }

        return [
            'exitCode' => $exitCode,
            'stdout' => self::readStream($stdout),
            'stderr' => self::readStream($stderr),
        ];
    }

    private static function readStream(mixed $stream): string
    {
        rewind($stream);

        return (string) stream_get_contents($stream);
    }
}
