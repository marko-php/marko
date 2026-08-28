<?php

declare(strict_types=1);

namespace Marko\Roadrunner\Tests\Command;

use Marko\Core\Command\Output;
use Marko\Core\Path\ProjectPaths;
use Marko\Roadrunner\Binary\BinaryLocatorInterface;
use Marko\Roadrunner\Command\ServeCommand;
use Marko\Roadrunner\Process\ProcessRunnerInterface;

/**
 * Command test helpers.
 */
final class Helpers
{
    public static function output(): Output
    {
        return new Output(fopen('php://memory', 'r+'));
    }

    /**
     * @return array{stream: resource, output: Output}
     */
    public static function outputStream(): array
    {
        $stream = fopen('php://memory', 'r+');

        return [
            'stream' => $stream,
            'output' => new Output($stream),
        ];
    }

    public static function tempProjectDir(): string
    {
        $dir = sys_get_temp_dir() . '/marko_rr_serve_' . bin2hex(random_bytes(8));
        mkdir($dir, 0755, true);

        return $dir;
    }

    public static function removeTempProjectDir(string $dir): void
    {
        foreach (scandir($dir) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            unlink($dir . '/' . $entry);
        }

        rmdir($dir);
    }

    public static function serveCommand(
        ?BinaryLocatorInterface $binaryLocator = null,
        ?ProcessRunnerInterface $processRunner = null,
        ?ProjectPaths $paths = null,
    ): ServeCommand {
        return new ServeCommand(
            binaryLocator: $binaryLocator ?? new FakeBinaryLocator('/usr/local/bin/rr'),
            processRunner: $processRunner ?? new FakeProcessRunner(),
            paths: $paths ?? new ProjectPaths(self::tempProjectDir()),
        );
    }
}
