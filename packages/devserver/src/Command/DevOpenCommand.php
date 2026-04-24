<?php

declare(strict_types=1);

namespace Marko\DevServer\Command;

use Closure;
use Marko\Core\Attributes\Command;
use Marko\Core\Command\CommandInterface;
use Marko\Core\Command\Input;
use Marko\Core\Command\Output;
use Marko\DevServer\Exceptions\DevServerException;
use Marko\DevServer\Process\PidFile;

/** @noinspection PhpUnused */
#[Command(name: 'dev:open', description: 'Open the running development server in a browser', aliases: ['open'])]
readonly class DevOpenCommand implements CommandInterface
{
    /**
     * @param Closure(string): void $opener
     */
    public function __construct(
        private PidFile $pidFile,
        private Closure $opener,
    ) {}

    public function execute(
        Input $input,
        Output $output,
    ): int {
        $entries = $this->pidFile->read();

        if ($entries === []) {
            throw new DevServerException(
                message: 'No running development environment found.',
                context: 'While trying to open the development server',
                suggestion: "Start the development environment first with 'marko up'.",
            );
        }

        $phpEntry = null;
        foreach ($entries as $entry) {
            if ($entry->name === 'php') {
                $phpEntry = $entry;
                break;
            }
        }

        if ($phpEntry === null || !$this->pidFile->isRunning($phpEntry->pid)) {
            throw new DevServerException(
                message: 'PHP server is not running.',
                context: 'While trying to open the development server',
                suggestion: "Start the development environment with 'marko up'.",
            );
        }

        $url = "http://localhost:$phpEntry->port";
        $output->writeLine("Opening $url");
        ($this->opener)($url);

        return 0;
    }
}
